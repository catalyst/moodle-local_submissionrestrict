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

namespace local_submissionrestrict\local\mod;

use local_submissionrestrict\restrict;

/**
 * Tests for assign class.
 * @package    local_submissionrestrict
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

        set_config('assign_restore_enabled', 1, 'local_submissionrestrict');
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

        set_config('assign_restore_hour', 10, 'local_submissionrestrict');
        set_config('assign_restore_minute', 15, 'local_submissionrestrict');

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

        set_config('assign_restore_hour', 10, 'local_submissionrestrict');
        set_config('assign_restore_minute', 15, 'local_submissionrestrict');
        $assign = new assign();

        $time = new \DateTime('12.11.2021 13:00', \core_date::get_user_timezone_object());
        $time->setTime(15, 00);
        $date = $time->getTimestamp();

        $course = $this->getDataGenerator()->create_course();

        $record = ['cutoffdate' => $date, 'duedate' => $date, 'course' => $course->id];
        $assignactivity1 = $this->getDataGenerator()->create_module('assign', $record);
        $assignactivity2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $event1 = $DB->get_record('event', ['modulename' => 'assign', 'instance' => $assignactivity1->id]);
        $event2 = $DB->get_record('event', ['modulename' => 'assign', 'instance' => $assignactivity2->id]);

        $this->assertNotEmpty($event1);
        $this->assertEquals($assignactivity1->duedate, $event1->timestart);
        $this->assertEquals('due', $event1->eventtype);

        $this->assertEmpty($event2);

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

        $event1 = $DB->get_record('event', ['modulename' => 'assign', 'instance' => $assignactivity1->id]);
        $event2 = $DB->get_record('event', ['modulename' => 'assign', 'instance' => $assignactivity2->id]);

        $this->assertNotEmpty($event1);
        $this->assertEquals($assign1record->duedate, $event1->timestart);
        $this->assertEquals('due', $event1->eventtype);
        $this->assertEmpty($event2);
    }

    /**
     * Test getting restriction record.
     */
    public function test_get_restriction_record() {
        $this->resetAfterTest();

        $assign = new assign();

        $this->assertFalse($assign->get_restriction_record(10));

        $restrictrecord = new restrict();
        $restrictrecord->set('cmid', 10);
        $restrictrecord->set('newdate', time());
        $restrictrecord->set('modname', $assign->get_name());
        $restrictrecord->set('reason', 'Test reason');
        $restrictrecord->save();

        $actual = $assign->get_restriction_record(10);
        $this->assertEquals(10, $actual->get('cmid'));
        $this->assertEquals('assign', $actual->get('modname'));
        $this->assertEquals('Test reason', $actual->get('reason'));
    }

    /**
     * Test checking override permissions.
     */
    public function test_has_override_permissions() {
        global $DB, $COURSE;

        $this->resetAfterTest();

        $assign = new assign();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();

        $this->setAdminUser();
        $this->assertTrue($assign->has_override_permissions());
        $this->assertTrue($assign->has_override_permissions($coursecontext));

        $this->setUser($user);
        $this->assertFalse($assign->has_override_permissions());
        $this->assertFalse($assign->has_override_permissions($coursecontext));

        $role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        assign_capability('local/submissionrestrict:override', CAP_ALLOW, $role->id, $coursecontext);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role->id);

        $this->assertFalse($assign->has_override_permissions());
        $this->assertTrue($assign->has_override_permissions($coursecontext));

        $COURSE = $course;
        $this->assertTrue($assign->has_override_permissions());
        $this->assertTrue($assign->has_override_permissions($coursecontext));
    }

    /**
     * Test delete hook for assign.
     */
    public function test_pre_course_module_delete() {
        $this->resetAfterTest();

        set_config('assign_timeslots', '9:30', 'local_submissionrestrict');
        set_config('assign_reasons', 'Test reason', 'local_submissionrestrict');

        $assign = new assign();

        $course = $this->getDataGenerator()->create_course();
        $assignment1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $assignment2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $this->assertFalse($assign->get_restriction_record($assignment1->cmid));
        $this->assertFalse($assign->get_restriction_record($assignment2->cmid));

        $restrict1 = new restrict();
        $restrict1->set('cmid', $assignment1->cmid);
        $restrict1->set('newdate', time());
        $restrict1->set('modname', $assign->get_name());
        $restrict1->set('reason', 'Test reason');
        $restrict1->save();

        $restrict2 = new restrict();
        $restrict2->set('cmid', $assignment2->cmid);
        $restrict2->set('newdate', time());
        $restrict2->set('modname', $assign->get_name());
        $restrict2->set('reason', 'Test reason');
        $restrict2->save();

        $this->assertEquals($assignment1->cmid, $assign->get_restriction_record($assignment1->cmid)->get('cmid'));
        $this->assertEquals($assignment2->cmid, $assign->get_restriction_record($assignment2->cmid)->get('cmid'));

        course_delete_module($assignment1->cmid);
        $this->assertFalse($assign->get_restriction_record($assignment1->cmid));
        $this->assertEquals($assignment2->cmid, $assign->get_restriction_record($assignment2->cmid)->get('cmid'));

        delete_course($course->id, false);
        $this->assertFalse($assign->get_restriction_record($assignment1->cmid));
        $this->assertFalse($assign->get_restriction_record($assignment2->cmid));
    }

    /**
     * Test if assign extension is functional.
     */
    public function test_is_functional() {
        $this->resetAfterTest(true);

        $assign = new assign();

        $this->assertFalse($assign->is_functional());

        set_config('assign_timeslots', '9:30', 'local_submissionrestrict');
        set_config('assign_reasons', 'Test reason', 'local_submissionrestrict');

        $this->assertTrue($assign->is_functional());
    }

}
