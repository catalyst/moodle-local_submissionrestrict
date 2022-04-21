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
 * Post database install script for local_submissionrestrict.
 *
 * @package     local_submissionrestrict
 * @copyright   2022 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post install hook for local_submissionrestrict.
 */
function xmldb_local_submissionrestrict_install() {
    global $DB;

    $dbman = $DB->get_manager();

    if ((!defined('PHPUNIT_TEST') || !PHPUNIT_TEST)) {

        // Migrate all configs.
        $configs = ['assign_restore_enabled', 'assign_restore_hour', 'assign_restore_minute', 'assign_timeslots', 'assign_reasons'];
        foreach ($configs as $config) {
            $value = get_config('local_submissionrestict', $config);
            if (!empty($value)) {
                set_config($config, $value, 'local_submissionrestrict');
            }
        }

        // Migrate all records.
        if ($dbman->table_exists('local_submissionrestict')) {
            $records = $DB->get_records('local_submissionrestict');
            if (!empty($records)) {
                foreach ($records as $record) {
                    $DB->insert_record('local_submissionrestrict', $record);
                }
            }
        }

        // Fix capabilities.
        $sql = "UPDATE {role_capabilities} SET capability = ? WHERE capability = ?";
        $DB->execute($sql, ['local/submissionrestrict:override', 'local/submissionrestict:override']);

        $sql = "UPDATE {role_capabilities} SET capability = ? WHERE capability = ?";
        $DB->execute($sql, ['local/submissionrestrict:overridereport', 'local/submissionrestict:overridereport']);
    }
}
