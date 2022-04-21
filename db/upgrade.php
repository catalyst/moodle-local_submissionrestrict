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

/**
 * Upgrade hooks.
 *
 * @package     local_submissionrestrict
 * @copyright   2022 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to upgrade local_submissionrestrict.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_local_submissionrestrict_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 202111003) {

        $table = new xmldb_table('local_submissionrestrict');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('mod', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('newdate', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('reason', XMLDB_TYPE_TEXT);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['cmid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 202111003, 'local', 'submissionrestrict');
    }

    if ($oldversion < 202111005) {
        $table = new xmldb_table('local_submissionrestrict');

        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('mod', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, 'modname');
            }
        }

        upgrade_plugin_savepoint(true, 202111005, 'local', 'submissionrestrict');
    }

    if ($oldversion < 2022031800) {
        $table = new xmldb_table('local_submissionrestrict');

        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('newdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
            }

            $field = new xmldb_field('reason', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2022031800, 'local', 'submissionrestrict');
    }

    if ($oldversion < 2022032303) {
        // Getting all assignments with missing or incorrect events where a due date is in
        // the future (we don't really care if due date already passed as it won't give users any values).
        $sql = "SELECT a.id
                  FROM {assign} a
             LEFT JOIN {event} e ON (a.id = e.instance AND e.modulename = 'assign' AND eventtype = 'due')
                 WHERE (e.id is NULL AND a.duedate > ?)
                    OR (a.duedate > ? AND e.timestart <> a.duedate AND e.groupid = 0 AND e.courseid <> 0)";

        $assignments = $DB->get_records_sql($sql, [time(), time()]);

        foreach ($assignments as $assign) {
            // Generate an adhoc task to unlock previews that were incorrectly locked.
            $record = new \stdClass();
            $record->classname = '\local_submissionrestrict\task\update_assign_calendar';
            $record->customdata = json_encode($assign->id);
            $record->nextruntime = time() - 1;
            $DB->insert_record('task_adhoc', $record);
        }

        upgrade_plugin_savepoint(true, 2022032303, 'local', 'submissionrestrict');
    }

    return true;
}
