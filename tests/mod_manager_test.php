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

use local_submissionrestrict\local\mod\assign;

/**
 * Tests for mod_manager class.
 * @package    local_submissionrestrict
 * @copyright  2022 Catalyst IT
 * @author     Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_manager_test extends \advanced_testcase {

    /**
     * Test get list of all supported mods.
     */
    public function test_get_mods() {
        $mods = mod_manager::get_mods();

        $this->assertArrayHasKey('assign', $mods);
        $this->assertInstanceOf(assign::class, $mods['assign']);
    }

    /**
     * Test get list of functional mods.
     */
    public function test_get_functional_mods() {
        $this->resetAfterTest(true);

        $mods = mod_manager::get_functional_mods();
        $this->assertEmpty($mods);

        set_config('assign_timeslots', '9:30', 'local_submissionrestrict');
        set_config('assign_reasons', 'Test reason', 'local_submissionrestrict');

        $mods = mod_manager::get_functional_mods();

        $this->assertArrayHasKey('assign', $mods);
        $this->assertInstanceOf(assign::class, $mods['assign']);
    }

}
