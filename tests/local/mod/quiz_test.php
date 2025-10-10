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
 * Tests for quiz class.
 *
 * @package    local_submissionrestrict
 * @copyright  2022 Catalyst IT
 * @author     Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_submissionrestrict\local\mod\quiz
 */
final class quiz_test extends \advanced_testcase {
    /**
     * Test getting name.
     */
    public function test_get_name(): void {
        $mod = new quiz();

        $this->assertSame('quiz', $mod->get_name());
    }

    /**
     * Test build config name.
     */
    public function test_build_config_name(): void {
        $mod = new quiz();

        $this->assertSame('quiz_test', $mod->build_config_name('test'));
        $this->assertSame('quiz_', $mod->build_config_name(''));
    }

    /**
     * Test can check is restore reset is enabled.
     */
    public function test_is_restore_reset_enabled(): void {
        $this->resetAfterTest();

        $mod = new quiz();
        $this->assertFalse($mod->is_restore_reset_enabled());

        set_config('quiz_restore_enabled', 1, 'local_submissionrestrict');
        $this->assertTrue($mod->is_restore_reset_enabled());
    }

    /**
     * Test getting restore time.
     */
    public function test_get_restore_time(): void {
        $this->resetAfterTest();

        $quiz = new quiz();

        $time = $quiz->get_restore_time();
        $this->assertSame(0, $time->get_hour());
        $this->assertSame(0, $time->get_minute());

        set_config('quiz_restore_hour', 10, 'local_submissionrestrict');
        set_config('quiz_restore_minute', 15, 'local_submissionrestrict');

        $time = $quiz->get_restore_time();
        $this->assertSame(10, $time->get_hour());
        $this->assertSame(15, $time->get_minute());
    }

    /**
     * Test resetting submission dates.
     */
    public function test_reset_submission_dates_by_grade_item(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('quiz_restore_hour', 10, 'local_submissionrestrict');
        set_config('quiz_restore_minute', 15, 'local_submissionrestrict');
        $quiz = new quiz();

        $time = new \DateTime('12.11.2021 13:00', \core_date::get_user_timezone_object());
        $time->setTime(15, 00);
        $date = $time->getTimestamp();

        $course = $this->getDataGenerator()->create_course();

        $record = ['timeopen' => $date, 'timeclose' => $date, 'course' => $course->id];
        $activity1 = $this->getDataGenerator()->create_module('quiz', $record);
        $activity2 = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $event1 = $DB->get_record('event', ['modulename' => 'quiz', 'instance' => $activity1->id, 'eventtype' => 'close']);
        $event2 = $DB->get_record('event', ['modulename' => 'quiz', 'instance' => $activity2->id, 'eventtype' => 'close']);

        $this->assertNotEmpty($event1);
        $this->assertEquals($activity1->timeclose, $event1->timestart);
        $this->assertEquals('close', $event1->eventtype);
        $this->assertEmpty($event2);

        $gradeitem1 = \grade_item::fetch(['courseid' => $course->id, 'iteminstance' => $activity1->id]);
        $gradeitem2 = \grade_item::fetch(['courseid' => $course->id, 'iteminstance' => $activity2->id]);

        $quiz->reset_submission_dates_by_grade_item($gradeitem1);
        $quiz->reset_submission_dates_by_grade_item($gradeitem2);

        $activity1record = $DB->get_record('quiz', ['course' => $course->id, 'id' => $activity1->id]);
        $activity2record = $DB->get_record('quiz', ['course' => $course->id, 'id' => $activity2->id]);

        $time->setTime(10, 15);

        // Should reset as submission limit is enabled.
        $this->assertEquals($activity1->timeopen, $activity1record->timeopen);
        $this->assertEquals($time->getTimestamp(), $activity1record->timeclose);

        // Should not reset as submission limit is not enabled.
        $this->assertEquals(0, $activity2record->timeopen);
        $this->assertEquals(0, $activity2record->timeclose);

        $event1 = $DB->get_record('event', ['modulename' => 'quiz', 'instance' => $activity1->id, 'eventtype' => 'close']);
        $event2 = $DB->get_record('event', ['modulename' => 'quiz', 'instance' => $activity2->id, 'eventtype' => 'close']);

        $this->assertNotEmpty($event1);
        $this->assertEquals($activity1record->timeclose, $event1->timestart);
        $this->assertEquals('close', $event1->eventtype);
        $this->assertEmpty($event2);
    }

    /**
     * Test getting restriction record.
     */
    public function test_get_restriction_record(): void {
        $this->resetAfterTest();

        $quiz = new quiz();

        $this->assertFalse($quiz->get_restriction_record(10));

        $restrictrecord = new restrict();
        $restrictrecord->set('cmid', 10);
        $restrictrecord->set('newdate', time());
        $restrictrecord->set('modname', $quiz->get_name());
        $restrictrecord->set('reason', 'Test reason');
        $restrictrecord->save();

        $actual = $quiz->get_restriction_record(10);
        $this->assertEquals(10, $actual->get('cmid'));
        $this->assertEquals('quiz', $actual->get('modname'));
        $this->assertEquals('Test reason', $actual->get('reason'));
    }

    /**
     * Test checking override permissions.
     */
    public function test_has_override_permissions(): void {
        global $DB, $COURSE;

        $this->resetAfterTest();

        $quiz = new quiz();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();

        $this->setAdminUser();
        $this->assertTrue($quiz->has_override_permissions());
        $this->assertTrue($quiz->has_override_permissions($coursecontext));

        $this->setUser($user);
        $this->assertFalse($quiz->has_override_permissions());
        $this->assertFalse($quiz->has_override_permissions($coursecontext));

        $role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        assign_capability('local/submissionrestrict:override', CAP_ALLOW, $role->id, $coursecontext);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role->id);

        $this->assertFalse($quiz->has_override_permissions());
        $this->assertTrue($quiz->has_override_permissions($coursecontext));

        $COURSE = $course;
        $this->assertTrue($quiz->has_override_permissions());
        $this->assertTrue($quiz->has_override_permissions($coursecontext));
    }

    /**
     * Test delete hook.
     */
    public function test_pre_course_module_delete(): void {
        $this->resetAfterTest();

        set_config('quiz_timeslots', '9:30', 'local_submissionrestrict');
        set_config('quiz_reasons', 'Test reason', 'local_submissionrestrict');

        $quiz = new quiz();

        $course = $this->getDataGenerator()->create_course();
        $activity1 = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $activity2 = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $this->assertFalse($quiz->get_restriction_record($activity1->cmid));
        $this->assertFalse($quiz->get_restriction_record($activity2->cmid));

        $restrict1 = new restrict();
        $restrict1->set('cmid', $activity1->cmid);
        $restrict1->set('newdate', time());
        $restrict1->set('modname', $quiz->get_name());
        $restrict1->set('reason', 'Test reason');
        $restrict1->save();

        $restrict2 = new restrict();
        $restrict2->set('cmid', $activity2->cmid);
        $restrict2->set('newdate', time());
        $restrict2->set('modname', $quiz->get_name());
        $restrict2->set('reason', 'Test reason');
        $restrict2->save();

        $this->assertEquals($activity1->cmid, $quiz->get_restriction_record($activity1->cmid)->get('cmid'));
        $this->assertEquals($activity2->cmid, $quiz->get_restriction_record($activity2->cmid)->get('cmid'));

        course_delete_module($activity1->cmid);
        $this->assertFalse($quiz->get_restriction_record($activity1->cmid));
        $this->assertEquals($activity2->cmid, $quiz->get_restriction_record($activity2->cmid)->get('cmid'));

        delete_course($course->id, false);
        $this->assertFalse($quiz->get_restriction_record($activity1->cmid));
        $this->assertFalse($quiz->get_restriction_record($activity2->cmid));
    }

    /**
     * Test if extension is functional.
     */
    public function test_is_functional(): void {
        $this->resetAfterTest(true);

        $quiz = new quiz();

        $this->assertFalse($quiz->is_functional());

        set_config('quiz_timeslots', '9:30', 'local_submissionrestrict');
        set_config('quiz_reasons', 'Test reason', 'local_submissionrestrict');

        $this->assertTrue($quiz->is_functional());
    }
}
