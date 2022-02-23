<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_submissionrestict\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for local_submissionrestict.
 *
 * @package     local_submissionrestict
 * @copyright   2022 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Retrieve the user metadata stored by plugin.
     *
     * @param collection $collection Collection of metadata.
     * @return collection Collection of metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_submissionrestict',
            [
                'usermodified' => 'privacy:metadata:local_submissionrestict:usermodified',
            ],
            'privacy:metadata:local_submissionrestict'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist A list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $params = [
            'userid' => $userid,
            'contextlevel' => CONTEXT_MODULE,
        ];

        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {local_submissionrestict} ls ON ls.cmid = cm.id
                 WHERE ls.usermodified = :userid";

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $contextids = [];
        foreach ($contextlist->get_contexts() as $context) {
            $contextids[] = $context->id;
        }

        if (empty($contextids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $params['usermodified'] = $contextlist->get_user()->id;

        $sql = "SELECT c.id, ls.usermodified, ls.timecreated, ls.timemodified
                  FROM {local_submissionrestict} ls
            INNER JOIN {course_modules} cm ON cm.id = ls.cmid
            INNER JOIN {context} c ON cm.id = c.instanceid
                 WHERE usermodified = :usermodified AND c.id " . $insql;

        $restricts = $DB->get_records_sql($sql, $params);

        $index = 0;
        foreach ($restricts as $restrict) {
            // Data export is organised in: {Context}/{Plugin Name}/{Table name}/{index}/data.json.
            $index++;
            $subcontext = [
                get_string('pluginname', 'local_submissionrestict'),
                'local_submissionrestict',
                $index
            ];

            $data = (object) [
                'contextid' => $restrict->id,
                'usermodified' => $restrict->usermodified,
                'timecreated' => transform::datetime($restrict->timecreated),
                'timemodified' => transform::datetime($restrict->timemodified)
            ];

            $context = \context::instance_by_id($restrict->contextid);
            writer::with_context($context)->export_data($subcontext, $data);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        // We don't want to delete records. Just anonymise the users.
        $DB->set_field('local_submissionrestict', 'usermodified', 0, ['cmid' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $contexts = $contextlist->get_contexts();
        if (count($contexts) == 0) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cmids[] = $context->instanceid;
        }

        if (empty($cmids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $params['usermodified'] = $contextlist->get_user()->id;

        // We don't want to delete records. Just anonymise the users.
        $DB->set_field_select('local_submissionrestict', 'usermodified', 0, "usermodified = :usermodified AND $insql", $params);
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT usermodified AS userid
                  FROM {local_submissionrestict}
                 WHERE cmid = :cmid";

        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // We don't want to delete records. Just anonymise the users.
        $DB->set_field_select('local_submissionrestict', 'usermodified', 0, "usermodified {$insql}", $inparams);
    }

}
