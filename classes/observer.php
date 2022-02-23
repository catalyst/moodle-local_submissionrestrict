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

use core\event\grade_item_created;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/submissionrestict/lib.php');

/**
 * Observer class.
 *
 * @package    local_submissionrestict
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle grade item created event.
     *
     * @param grade_item_created $event
     */
    public static function handle_grade_item_created(grade_item_created $event) {
        global $PAGE;

        if ($PAGE->requestorigin == 'restore' && $mod = self::get_mod_from_event($event)) {
            $mods = mod_manager::get_mods();

            if (array_key_exists($mod, $mods) && $mods[$mod]->is_restore_reset_enabled()) {
                $gradeitem = \grade_item::fetch([
                    'courseid' => $event->courseid,
                    'id' => $event->objectid,
                ]);

                if (!empty($gradeitem)) {
                    $mods[$mod]->reset_submission_dates_by_grade_item($gradeitem);
                }
            }
        }
    }

    /**
     * Get mod type from provided event.
     *
     * @param \core\event\grade_item_created $event Event.
     * @return string
     */
    protected static function get_mod_from_event(grade_item_created $event): ?string {
        $activitytype = null;

        if ($event->other['itemtype'] == 'mod' && !empty($event->other['itemmodule'])) {
            $activitytype = $event->other['itemmodule'];
        }

        return $activitytype;
    }

}
