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

/**
 * Tests for assign class.
 *
 * @package    local_submissionrestict
 * @copyright  2022 Catalyst IT
 * @author     Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_test extends \advanced_testcase {

    /**
     * Test calculating a new time.
     */
    public function test_get_name() {
        $assign = new assign();

        $this->assertSame('assign', $assign->get_name());
    }

    /**
     * Test build config name.
     */
    public function test_build_config_name() {
        $assign = new assign();

        $this->assertSame('assign_test', $assign->build_config_name('test'));
        $this->assertSame('assign_', $assign->build_config_name(''));
    }

    /**
     * Test can check is restore reset is enabled.
     */
    public function test_is_restore_reset_enabled() {
        $this->resetAfterTest();

        $assign = new assign();
        $this->assertFalse($assign->is_restore_reset_enabled());

        set_config('assign_restore_enabled', 1, 'local_submissionrestict');
        $this->assertTrue($assign->is_restore_reset_enabled());
    }

    /**
     * Test getting restore time.
     */
    public function test_get_restore_time() {
        $this->resetAfterTest();

        $assign = new assign();

        $time = $assign->get_restore_time();
        $this->assertSame(0, $time->get_hour());
        $this->assertSame(0, $time->get_minute());

        set_config('assign_restore_hour', 10, 'local_submissionrestict');
        set_config('assign_restore_minute', 15, 'local_submissionrestict');

        $time = $assign->get_restore_time();
        $this->assertSame(10, $time->get_hour());
        $this->assertSame(15, $time->get_minute());
    }

    /**
     * Test resetting submission dates.
     */
    public function test_reset_submission_dates_by_grade_item() {
        global $DB;

        $this->resetAfterTest();

        set_config('assign_restore_hour', 10, 'local_submissionrestict');
        set_config('assign_restore_minute', 15, 'local_submissionrestict');
        $assign = new assign();

        $time = new \DateTime('12.11.2021 13:00', \core_date::get_user_timezone_object());
        $time->setTime(15, 00);
        $date = $time->getTimestamp();

        $course = $this->getDataGenerator()->create_course();

        $record = ['cutoffdate' => $date, 'duedate' => $date, 'course' => $course->id];
        $assignactivity1 = $this->getDataGenerator()->create_module('assign', $record);
        $assignactivity2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $gradeitem1 = \grade_item::fetch(['courseid' => $course->id, 'iteminstance' => $assignactivity1->id]);
        $gradeitem2 = \grade_item::fetch(['courseid' => $course->id, 'iteminstance' => $assignactivity2->id]);

        $assign->reset_submission_dates_by_grade_item($gradeitem1);
        $assign->reset_submission_dates_by_grade_item($gradeitem2);

        $assign1record = $DB->get_record('assign', ['course' => $course->id, 'id' => $assignactivity1->id]);
        $assign2record = $DB->get_record('assign', ['course' => $course->id, 'id' => $assignactivity2->id]);

        $time->setTime(10, 15);

        // Should reset as submission limit is enabled.
        $this->assertEquals($time->getTimestamp(), $assign1record->duedate);
        $this->assertEquals($time->getTimestamp(), $assign1record->cutoffdate);

        // Should not reset as submission limit is not enabled.
        $this->assertEquals(0, $assign2record->duedate);
        $this->assertEquals(0, $assign2record->cutoffdate);
    }
}
