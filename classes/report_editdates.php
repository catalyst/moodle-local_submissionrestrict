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

namespace local_submissionrestrict;

/**
 * Trait for helping in support of report edit dates.
 *
 * @package     local_submissionrestrict
 * @copyright   2025 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait report_editdates {
    /**
     * Get date field name from element name.
     *
     * Fields in report edit dates built as date_mod_{cmid}_{datefieldname}.
     *
     * @param string $elementname Name of the element.
     * @return string|null
     */
    protected function report_get_date_field_name_from_element_name(string $elementname): ?string {
        $parts = explode('_', $elementname);

        if (count($parts) != 4) {
            return null;
        }

        if (!isset($parts['1']) || $parts['1'] !== 'mod' || !isset($parts['2']) || !isset($parts['3'])) {
            return null;
        }

        if (is_integer($parts['2'])) {
            return null;
        }

        return $parts['3'];
    }

    /**
     * Get course module id from element name.
     *
     * Fields in report edit dates built as date_mod_{cmid}_{datefieldname}.
     *
     * @param string|null $elementname Name of the element.
     * @return string|null
     */
    protected function report_get_cmid_from_element_name(?string $elementname): ?string {
        if (empty($elementname)) {
            return null;
        }

        $parts = explode('_', $elementname);

        if (count($parts) != 4) {
            return null;
        }

        if (!isset($parts['1']) || $parts['1'] !== 'mod' || !isset($parts['2']) || !isset($parts['3'])) {
            return null;
        }

        if (is_integer($parts['2'])) {
            return null;
        }

        return $parts['2'];
    }


    /**
     * Build prefix for fields based on cmid.
     *
     * @param string $cmid Course module id.
     * @param string $newdatename Name of new date field.
     * @return string
     */
    protected function build_field_prefix(string $cmid, string $newdatename): string {
        return $this->build_new_element_name($cmid, $newdatename) . '_';
    }

    /**
     * Build new element name based on cmid.
     *
     * @param string $cmid Course module id.
     * @param string $newdatename Name of new date field.
     * @return string
     */
    protected function build_new_element_name(string $cmid, string $newdatename): string {
        return $newdatename . '_' . $cmid . '_' . $this->get_name();
    }
}
