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

namespace local_submissionrestrict;

/**
 * Tests for helper class.
 *
 * @package    local_submissionrestrict
 * @copyright  2021 Catalyst IT
 * @author     Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper_test extends \advanced_testcase {

    /**
     * Test calculating a new time.
     */
    public function test_local_submissionrestrict_calculate_new_time() {
        $now = '12.11.2021 13:00';

        // Generate expected timestamp for the time we are going to calculate for.
        $time = new \DateTime($now, \core_date::get_user_timezone_object());
        $time->setTime(23, 55);
        $expected = $time->getTimestamp();

        // Should return null if new date is the same as old date.
        $time = new \DateTime($now, \core_date::get_user_timezone_object());
        $time->setTime(23, 55);
        $date = $time->getTimestamp();
        $this->assertNull(helper::calculate_new_time($date, new time(23, 55)));

        // Should reset to the same day, but different time.
        $time = new \DateTime($now, \core_date::get_user_timezone_object());
        $time->setTime(13, 55);
        $date = $time->getTimestamp();
        $this->assertSame($expected, helper::calculate_new_time($date, new time(23, 55)));

        // Should return null if new date is the same as old date.
        $time = new \DateTime($now, \core_date::get_user_timezone_object());
        $time->setTime(18, 30);
        $date = $time->getTimestamp();
        $this->assertNull(helper::calculate_new_time($date, new time(23, 55), [new time(18, 30)]));
    }

}
