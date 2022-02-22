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

namespace local_submissionrestict;

use core\persistent;

/**
 * Class containing restrictions for activities.
 *
 * @package     local_submissionrestict
 * @copyright   2021 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restrict extends persistent {

    /**
     * Table name.
     */
    const TABLE = 'local_submissionrestict';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'cmid' => [
                'type' => PARAM_INT,
            ],
            'modname' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'newdate' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'reason' => [
                'type' => PARAM_RAW,
            ],
        ];
    }

}
