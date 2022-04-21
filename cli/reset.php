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
 * @package    local_submissionrestrict
 * @copyright  2021 Catalyst IT
 * @author     Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \local_submissionrestrict\time;
use \local_submissionrestrict\helper;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'run' => false,
        'search' => false,
        'fullname' => false,
        'hour' => false,
        'minute' => false,
        'ignore' => false,

    ],
    [
        'r' => 'run',
        's' => 'search',
        'f' => 'fullname',
        'h' => 'hour',
        'm' => 'minute',
        'i' => 'ignore',
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
  -f, --fullname     A full name to search courses by. It will exact match full name in SQL.
  -h, --hour         A hour to reset submission time to (e.g. 23).
  -m, --minute       A minutes to reset submission time to (e.g. 55).
  -i, --ignore       A list of times to ignore (e.g 9:30,16:30,23:55). Those submission time will be ignored.

Example:
\$sudo -u www-data /usr/bin/php local/integrity/cli/reset.php --help

EOT;
    cli_writeln($help);
    exit(0);
}


if (empty($options['search']) && empty($options['fullname'])) {
    cli_writeln("You have to specify 'search' or 'fullname' option. Please use --help to see all required options.");
    exit(0);
}

if (!empty($options['search']) && !empty($options['fullname'])) {
    cli_writeln("You have to specify 'search' or 'fullname' option. Please use --help to see all required options.");
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

$ignore = [];
if (!empty($options['ignore'])) {
    $timestrings = explode(",", str_replace(" ", "", $options['ignore']));
    foreach ($timestrings as $timestring) {
        $parts = explode(':', $timestring);
        if (count($parts) === 2) {
            $ignore[] = new \local_submissionrestrict\time($parts[0], $parts[1]);
        }
    }
}

$params = [];
$search = '';

if (!empty($options['search'])) {
    $search = $DB->sql_like('fullname', ':fullname', false);
    $params['fullname'] = '%' . $DB->sql_like_escape($options['search']) . '%';
}

if (!empty($options['fullname'])) {
    $search = 'fullname = :fullname';
    $params['fullname'] = $options['fullname'];
}

// Getting all assignments where either due date or cut off date is set and the course name is what we're looking for.
$sql = "SELECT a.id, a.name, c.fullname, a.duedate, a.cutoffdate
          FROM {assign} a
          JOIN {course} c ON a.course = c.id
         WHERE (a.duedate > 0 OR a.cutoffdate > 0) AND " . $search;

$records = $DB->get_records_sql($sql, $params);

if (empty($records)) {
    cli_writeln("No assignments with enabled Due or Cut-off date found based on your search request");
    exit(0);
}

$updated = 0;

foreach ($records as $record) {
    $needupdate = false;
    $update = new stdClass();
    $update->id = $record->id;
    $newtime = new time($options['hour'], $options['minute']);

    if ($record->duedate > 0) {
        if ($date = helper::calculate_new_time($record->duedate, $newtime, $ignore)) {
            $update->duedate = $date;
            $needupdate = true;
        }
    }

    if ($record->cutoffdate > 0) {
        if ($date = helper::calculate_new_time($record->cutoffdate, $newtime, $ignore)) {
            $update->cutoffdate = $date;
            $needupdate = true;
        }
    }

    if ($needupdate) {
        $updated ++;
        if ($options['run']) {
            $DB->update_record('assign', $update);

            list ($course, $cm) = get_course_and_cm_from_instance($record->id, 'assign');
            $context = \context_module::instance($cm->id);
            $assign = new \assign($context, $cm, $course);
            $assign->update_calendar($cm->id);

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
