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
 * Lib functions.
 *
 * @package    local_submissionrestict
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \core_calendar\type_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Calculates a new time based on provided hour and minute.
 *
 * It will return null if provided date doesn't need to be modified.
 *
 * @param int $date A unix time stamp date to calculate a new time for.
 * @param int $hour A new hour to set.
 * @param int $minute a new minute to set.
 *
 * @return int|null New unix time stamp.
 */
function local_submissionrestict_calculate_new_time(int $date, int $hour, int $minute): ?int {
    $newdate = null;

    $calendartype = type_factory::get_calendar_instance();

    $currentdate = $calendartype->timestamp_to_date_array($date);
    $currentdate['minutes'] -= $currentdate['minutes'] % 5;

    if ($currentdate['hours'] <> $hour || $currentdate['minutes'] <> $minute) {
        $currentdate['hour'] = $hour;
        $currentdate['minute'] = $minute;

        $gregoriandate = $calendartype->convert_to_gregorian(
            $currentdate['year'],
            $currentdate['mon'],
            $currentdate['mday'],
            $currentdate['hour'],
            $currentdate['minute']);

        $newdate = make_timestamp($gregoriandate['year'],
            $gregoriandate['month'],
            $gregoriandate['day'],
            $gregoriandate['hour'],
            $gregoriandate['minute']
        );
    }

    return $newdate;
}