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
 * CLI script for resetting submission times.
 *
 * @package    local_submissionrestict
 * @copyright  2021 Catalyst IT
 * @author     Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/submissionrestict/lib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'run' => false,
        'search' => false,
        'hour' => false,
        'minute' => false,
    ],
    [
        'r' => 'run',
        's' => 'search',
        'h' => 'hour',
        'm' => 'minute',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = <<<EOT

Reset submissions time for Assignment activities only.

Options:
 --help              Print out this help
  -r, --run          Executes reset, otherwise will be run in a dry run mode.
  -s, --search       A string to search courses by. It will be used in "like '%{search}%'" SQL statement.
  -h, --hour         A hour to reset submission time to (e.g. 23).
  -m, --minute       A minutes to reset submission time to (e.g. 55).
 
Example:
\$sudo -u www-data /usr/bin/php local/integrity/cli/reset.php --help

EOT;
    cli_writeln($help);
    exit(0);
}


if (empty($options['search'])) {
    cli_writeln("You have to specify correct 'search' option. Please use --help to see all required options.");
    exit(0);
}

if (empty((int)$options['hour'])) {
    cli_writeln("You have to specify correct 'hour' option. Please use --help to see all required options.");
    exit(0);
}

if (empty((int)$options['minute'])) {
    cli_writeln("You have to specify correct 'minute' option. Please use --help to see all required options.");
    exit(0);
}

// Getting all assignments where either due date or cut off date is set and the course name is what we're looking for.
$sql = "SELECT a.id, a.name, c.fullname, a.duedate, a.cutoffdate
          FROM {assign} a
          JOIN {course} c ON a.course = c.id
         WHERE " . $DB->sql_like('fullname', ':fullname', false) . " AND (a.duedate > 0 OR a.cutoffdate > 0)";

$params['fullname'] = '%' . $DB->sql_like_escape($options['search']) . '%';

$records = $DB->get_records_sql($sql, $params);

if (empty($records)) {
    cli_writeln("No assignments with enabled Due or Cut-off date found based on your search request");
    exit(0);
}

$calendartype = \core_calendar\type_factory::get_calendar_instance();
$updated = 0;

foreach ($records as $record) {
    $needupdate = false;
    $update = new stdClass();
    $update->id = $record->id;

    if ($record->duedate > 0) {
        if ($newdate = local_submissionrestict_calculate_new_time($record->duedate, $options['hour'], $options['minute'])) {
            $update->duedate = $newdate;
            $needupdate = true;
        }
    }

    if ($record->cutoffdate > 0) {
        if ($newdate = local_submissionrestict_calculate_new_time($record->cutoffdate, $options['hour'], $options['minute'])) {
            $update->cutoffdate = $newdate;
            $needupdate = true;
        }
    }

    if ($needupdate) {
        $updated ++;
        if ($options['run']) {
            $DB->update_record('assign', $update);
            cli_writeln("Assignment $record->name with ID $record->id updated");
        } else {
            cli_writeln("Assignment $record->name with ID $record->id will be updated");
        }
    }
}

if ($options['run']) {
    cli_writeln("$updated assignments updated");
} else {
    cli_writeln("$updated assignments will be updated");
}

exit(0);
