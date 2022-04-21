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

namespace local_submissionrestrict\task;

use core\task\adhoc_task;


/**
 * Ahdoc to update assignment calendar.
 *
 * @package    local_submissionrestrict
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_assign_calendar extends adhoc_task {

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $assignid = (int)$this->get_custom_data();

        list ($course, $cm) = get_course_and_cm_from_instance($assignid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);
        $assign->update_calendar($cm->id);
        mtrace("Successfully updated calendar events for assignment CMID {$cm->id}");
    }

}
