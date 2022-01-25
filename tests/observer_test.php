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

/**
 * Tests for observer.
 *
 * @package    local_submissionrestict
 * @copyright  2022 Catalyst IT
 * @author     Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer_test extends \advanced_testcase {

    /**
     * Test handling grade_item_created event.
     */
    public function test_handle_grade_item_created_for_assign() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Config default restore time.
        set_config('assign_restore_hour', 23, 'local_submissionrestict');
        set_config('assign_restore_minute', 55, 'local_submissionrestict');

        // Set initial date and time.
        $now = '12.11.2021 13:00';
        $initialtime = new \DateTime($now, \core_date::get_user_timezone_object());
        $initialtime->setTime(15, 00);
        $date = $initialtime->getTimestamp();

        $course = $this->getDataGenerator()->create_course();

        // Assign with dates configured.
        $record = ['cutoffdate' => $date, 'duedate' => $date, 'course' => $course->id];
        $assign1 = $this->getDataGenerator()->create_module('assign', $record);
        // Assign without dates configured.
        $assign2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        // Random activity to make sure we don't explode on them.
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $assign1record = $DB->get_record('assign', ['course' => $course->id, 'id' => $assign1->id]);
        $assign2record = $DB->get_record('assign', ['course' => $course->id, 'id' => $assign2->id]);

        // Check initial dates.
        $this->assertEquals($initialtime->getTimestamp(), $assign1record->duedate);
        $this->assertEquals($initialtime->getTimestamp(), $assign1record->cutoffdate);
        $this->assertEquals(0, $assign2record->duedate);
        $this->assertEquals(0, $assign2record->cutoffdate);

        // Backup and restore activities.
        $newcm1 = duplicate_module($course, get_fast_modinfo($course)->get_cm($assign1->cmid));
        $newcm2 = duplicate_module($course, get_fast_modinfo($course)->get_cm($assign2->cmid));
        $newcm3 = duplicate_module($course, get_fast_modinfo($course)->get_cm($forum->cmid));

        // Check dates stay the same for new restored activities.
        $newassign1record = $DB->get_record('assign', ['course' => $course->id, 'id' => $newcm1->instance]);
        $newassign2record = $DB->get_record('assign', ['course' => $course->id, 'id' => $newcm2->instance]);
        $this->assertEquals($initialtime->getTimestamp(), $newassign1record->duedate);
        $this->assertEquals($initialtime->getTimestamp(), $newassign1record->cutoffdate);
        $this->assertEquals(0, $newassign2record->duedate);
        $this->assertEquals(0, $newassign2record->cutoffdate);

        // Enable restore reset feature.
        set_config('assign_restore_enabled', 1, 'local_submissionrestict');

        // Backup and restore activities.
        $newcm1 = duplicate_module($course, get_fast_modinfo($course)->get_cm($assign1->cmid));
        $newcm2 = duplicate_module($course, get_fast_modinfo($course)->get_cm($assign2->cmid));
        $newcm3 = duplicate_module($course, get_fast_modinfo($course)->get_cm($forum->cmid));

        // Check dates changed for new restored activities.
        $newassign1record = $DB->get_record('assign', ['course' => $course->id, 'id' => $newcm1->instance]);
        $newassign2record = $DB->get_record('assign', ['course' => $course->id, 'id' => $newcm2->instance]);
        $initialtime->setTime(23, 55);
        $this->assertEquals($initialtime->getTimestamp(), $newassign1record->duedate);
        $this->assertEquals($initialtime->getTimestamp(), $newassign1record->cutoffdate);
        $this->assertEquals(0, $newassign2record->duedate);
        $this->assertEquals(0, $newassign2record->cutoffdate);
    }

}
