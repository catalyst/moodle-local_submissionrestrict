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

namespace local_submissionrestrict\local\admin;

use admin_setting_configtextarea;
use local_submissionrestrict\mod_base;

/**
 * Admin setting for submission restriction reasons.
 *
 * @package     local_submissionrestrict
 * @copyright   2026 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_configreasons extends admin_setting_configtextarea {
    /**
     * Validate the configuration data for restriction reasons.
     *
     * Parses each non-empty line to ensure it contains a label and,
     * when the delimiter is present, a non-empty description.
     *
     * @param string $data Raw textarea value with one reason per line.
     * @return true|string True if valid, otherwise an error message.
     */
    public function validate($data) {
        if (empty(trim($data))) {
            return true;
        }

        $errors = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $data)) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $pos = strpos($line, mod_base::REASONS_DELIMITER);
            $label = $pos !== false ? trim(substr($line, 0, $pos)) : $line;
            $description = $pos !== false ? trim(substr($line, $pos + strlen(mod_base::REASONS_DELIMITER))) : '';

            if (empty($label)) {
                $errors[] = $line;
                continue;
            }
            if ($pos !== false && empty($description)) {
                // The double colon '::' is present but nothing after it.
                $errors[] = $line;
            }
        }

        if ($errors) {
            return get_string(
                'settings:reasons_validate_error',
                'local_submissionrestrict',
                implode(', ', $errors)
            );
        }
        return true;
    }
}
