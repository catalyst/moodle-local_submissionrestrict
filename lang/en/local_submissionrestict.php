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

/**
 * Plugin strings are defined here.
 *
 * @package     local_submissionrestict
 * @category    string
 * @copyright   2021 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Submission restrictions';
$string['error:reasonrequired'] = 'You have to provide a reason for variation';
$string['privacy:metadata:local_submissionrestict'] = 'Details of Submission restriction data.';
$string['privacy:metadata:local_submissionrestict:usermodified'] = 'ID of user who last created or modified the restrictions.';
$string['reason'] = 'Choose reason for variation...';
$string['reasons'] = 'Variation reasons';
$string['reasons_help'] = 'There are may be reasons to set non standard submission times.';
$string['report:coursename'] = 'Unit name';
$string['report:modname'] = 'Assignment type';
$string['report:modulename'] = 'Assignment name';
$string['report:date'] = 'Due date';
$string['report:time'] = 'New time';
$string['report:reason'] = 'Reason for override';
$string['report:category'] = 'Category';
$string['report:heading'] = 'Submission overrides';
$string['report:title'] = 'Submission overrides report';
$string['report:filters'] = 'Filters';
$string['submissionrestict:override'] = 'Override configured submission times';
$string['submissionrestict:overridereport'] = 'Access submission override report';
$string['settings:reasons'] = 'Reasons for overriding';
$string['settings:reasons_desc'] = 'A list of reasons for overriding available timeslots. One reason per line.';
$string['settings:restore'] = 'Default time after restore';
$string['settings:restore_desc'] = 'Choose time submissions due date will be default to after an activity is restored.';
$string['settings:restore_enabled'] = 'Reset time after restore';
$string['settings:restore_enabled_desc'] = 'If enabled, due dates will be reset to a default time after supported activities are restored.';
$string['settings:timeslots'] = 'Available timeslots';
$string['settings:timeslots_desc'] = 'A list of available timeslots for due date. One timeslot per line.';
