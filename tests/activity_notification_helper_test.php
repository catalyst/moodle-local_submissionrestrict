<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_submissionrestrict;

/**
 * Tests for the activity notification helper.
 *
 * @package    local_submissionrestrict
 * @copyright  2026 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_submissionrestrict\activity_notification_helper
 */
final class activity_notification_helper_test extends \advanced_testcase {
    /**
     * Test that module view pages are detected correctly.
     */
    public function test_is_mod_view_page(): void {
        global $PAGE;

        $this->resetAfterTest(true);

        $helper = new activity_notification_helper();

        $PAGE->set_pagetype('mod-assign-view');

        $this->assertFalse(
            $helper->is_mod_view_page(),
            'Null course module should not be considered a module view.'
        );

        $setup = $this->create_assign_activity_with_reason('reason-one', 'notice');
        $PAGE->set_cm($setup['cminfo']);

        $this->assertTrue(
            $helper->is_mod_view_page(),
            'Expected the helper to recognise a module view page.'
        );

        $PAGE->set_pagetype('mod-assign-edit');

        $this->assertFalse(
            $helper->is_mod_view_page($setup['cminfo'], 'mod-assign-edit'),
            'Should return false for other pagetypes.'
        );
    }

    /**
     * Test whether a notification show be shown to the user or not.
     */
    public function test_should_show_notification(): void {
        global $DB, $PAGE;

        $this->resetAfterTest(true);

        $helper = new activity_notification_helper();
        $setup = $this->create_assign_activity_with_reason('reason-one', 'Displayed reason');
        $this->setUser($setup['user']);
        $PAGE->set_cm($setup['cminfo']);
        $PAGE->set_pagetype('mod-assign-view');

        $this->assertFalse($helper->should_show_notification());

        // Also set the timeslots so the assign module is treated as a functional mod.
        set_config('assign_timeslots', '9:30', 'local_submissionrestrict');
        $this->assertTrue($helper->should_show_notification());

        // Create an assignment override for the user.
        $DB->insert_record('assign_overrides', [
            'assignid' => $setup['assign']->id,
            'userid' => $setup['user']->id,
        ]);
        $this->assertFalse($helper->should_show_notification());
    }

    /**
     * Helper to set up an assign activity with a configured reason.
     *
     * @param string $reason Reason label.
     * @param string $description Reason description.
     * @return array Contains course, user, assign, cmrecord and cm_info.
     */
    private function create_assign_activity_with_reason(string $reason, string $description): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance(['course' => $course->id]);
        $cmrecord = get_coursemodule_from_instance('assign', $assign->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cmrecord->id);

        set_config('assign_reasons', "{$reason}::{$description}", 'local_submissionrestrict');

        $restrict = new restrict();
        $restrict->set('cmid', $cminfo->id);
        $restrict->set('modname', 'assign');
        $restrict->set('reason', $reason);
        $restrict->save();

        return [
            'course' => $course,
            'user' => $user,
            'assign' => $assign,
            'cmrecord' => $cmrecord,
            'cminfo' => $cminfo,
        ];
    }
}
