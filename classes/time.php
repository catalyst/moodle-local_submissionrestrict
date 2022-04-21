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
 * Time class.
 *
 * @package    local_submissionrestrict
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class time {

    /**
     * Hour.
     * @var int
     */
    private $hour;

    /**
     * Minute.
     * @var int
     */
    private $minute;

    /**
     * Time constructor.
     *
     * @param int $hour Hour.
     * @param int $minute Minute.
     */
    public function __construct(int $hour, int $minute) {
        $this->hour = $hour;
        $this->minute = $minute;
    }

    /**
     * Returns hour.
     *
     * @return int
     */
    public function get_hour(): int {
        return $this->hour;
    }

    /**
     * Returns minutes.
     *
     * @return int
     */
    public function get_minute(): int {
        return $this->minute;
    }

}
