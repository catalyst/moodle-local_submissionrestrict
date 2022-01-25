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

namespace local_submissionrestict\local\mod;

use local_submissionrestict\helper;
use local_submissionrestict\mod_base;
use grade_item;

/**
 * Submission restriction for assign.
 *
 * @package     local_submissionrestict
 * @copyright   2021 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign extends mod_base {

    /**
     * Reset due dates.
     *
     * @param \grade_item $gradeitem
     */
    public function reset_submission_dates_by_grade_item(grade_item $gradeitem): void {
        global $DB;

        if ($record = $DB->get_record($this->get_name(), ['id' => $gradeitem->iteminstance])) {
            $needupdate = false;

            if ($record->duedate > 0) {
                if ($newdate = helper::calculate_new_time($record->duedate, $this->get_restore_time())) {
                    $record->duedate = $newdate;
                    $needupdate = true;
                }
            }

            if ($record->cutoffdate > 0) {
                if ($newdate = helper::calculate_new_time($record->cutoffdate, $this->get_restore_time())) {
                    $record->cutoffdate = $newdate;
                    $needupdate = true;
                }
            }

            if ($needupdate) {
                $DB->update_record($this->get_name(), $record);
            }
        }
    }

}
