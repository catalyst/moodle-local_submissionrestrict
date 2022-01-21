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

namespace local_submissionrestict;

use core\event\grade_item_created;

require_once($CFG->dirroot . '/local/submissionrestict/lib.php');

/**
 * Observer class.
 *
 * @package    local_submissionrestict
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle grade item created event.
     *
     * @param grade_item_created $event
     */
    public static function handle_grade_item_created(grade_item_created $event) {
        global $PAGE, $DB;

        if ($PAGE->requestorigin == 'restore' && self::is_assignment_related($event)) {
            $gradeitem = \grade_item::fetch([
                'courseid' => $event->courseid,
                'id' => $event->objectid,
            ]);

            if ($record = $DB->get_record('assign', ['id' => $gradeitem->iteminstance])) {
                $needupdate = false;

                if ($record->duedate > 0) {
                    if ($newdate = local_submissionrestict_calculate_new_time($record->duedate, self::get_assign_new_time())) {
                        $record->duedate = $newdate;
                        $needupdate = true;
                    }
                }

                if ($record->cutoffdate > 0) {
                    if ($newdate = local_submissionrestict_calculate_new_time($record->cutoffdate, self::get_assign_new_time())) {
                        $record->cutoffdate = $newdate;
                        $needupdate = true;
                    }
                }

                if ($needupdate) {
                    $DB->update_record('assign', $record);
                }
            }
        }
    }

    /**
     * Check if provided event is assignment related event.
     *
     * @param \core\event\grade_item_created $event
     * @return bool
     */
    protected static function is_assignment_related(grade_item_created $event): bool {
        return $event->other['itemtype'] == 'mod' && $event->other['itemmodule'] == 'assign';
    }

    /**
     * Get a new time to force restored assignemnts to.
     * @return \local_submissionrestict\time
     */
    protected static function get_assign_new_time(): time {
        return new time(
            (int)get_config('local_submissionrestict', 'restore_hour'),
            (int)get_config('local_submissionrestict', 'restore_minute')
        );
    }

}
