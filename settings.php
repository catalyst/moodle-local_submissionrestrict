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

/**
 * Settings for local_submissionrestrict plugin.
 *
 * @package     local_submissionrestrict
 * @copyright   2022 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_submissionrestrict\mod_manager;

if ($hassiteconfig && $ADMIN->locate('localplugins')) {

    $settings = new admin_settingpage(
        'local_submissionrestrict_settings',
        get_string('pluginname', 'local_submissionrestrict')
    );

    foreach (mod_manager::get_mods() as $mod) {
        $mod->add_settings($settings);
    }

    $ADMIN->add('localplugins', $settings);
}

if ($hassiteconfig && $ADMIN->locate('reports')) {
    $ADMIN->add('reports',
        new admin_externalpage('local_submissionrestrict_report',
            new lang_string('report:title', 'local_submissionrestrict'),
            $CFG->wwwroot . '/local/submissionrestrict/report.php?pagecontextid= ' . context_system::instance()->id,
            ['local/submissionrestrict:overridereport'])
    );
}
