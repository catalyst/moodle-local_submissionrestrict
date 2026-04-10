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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_submissionrestrict;

use cm_info;
use local_submissionrestrict\mod_manager;
use local_submissionrestrict\restrict;

/**
 * Helper for constructing and injecting activity notifications.
 *
 * @package    local_submissionrestrict
 * @copyright  2026 Catalyst IT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_notification_helper {
    /**
     * Check whether the provided page is a module view page.
     *
     * @return bool
     */
    public function is_mod_view_page(): bool {
        global $PAGE;

        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return false;
        }

        if (empty($PAGE->cm->id)) {
            return false;
        }

        $segments = explode('-', $PAGE->pagetype);
        return count($segments) === 3 && $segments[0] === 'mod' && $segments[2] === 'view';
    }

    /**
     * Determine if the current user should see a notification and return the formatted text.
     *
     * @return bool
     */
    public function should_show_notification(): bool {
        global $PAGE, $USER;

        if (!$this->is_mod_view_page()) {
            return false;
        }

        $mods = mod_manager::get_functional_mods();
        if (!isset($mods[$PAGE->cm->modname])) {
            return false;
        }

        $mod = $mods[$PAGE->cm->modname];
        if ($mod->has_user_or_group_override($PAGE->cm->id, $USER->id)) {
            return false;
        }

        return true;
    }

    /**
     * Injects the notification for client-side insertion.
     */
    public function inject_notification(): void {
        global $PAGE, $OUTPUT;

        $mods = mod_manager::get_functional_mods();
        if (!isset($mods[$PAGE->cm->modname])) {
            return;
        }

        $restrict = restrict::get_record(['cmid' => $PAGE->cm->id]);
        if (!$restrict || empty($restrict->get('reason'))) {
            return;
        }

        $mod = $mods[$PAGE->cm->modname];
        $description = $mod->get_reason_description($restrict->get('reason'));
        if (empty($description)) {
            return;
        }

        $notification = new \core\output\notification(
            format_text($description, FORMAT_PLAIN),
            \core\output\notification::NOTIFY_INFO,
            true,
            get_string('activitynotificationtitle', 'local_submissionrestrict'),
        );
        $html = $PAGE->get_renderer('core')->render($notification);

        $encoded = rawurlencode($html);
        $js = "document.getElementById('user-notifications').insertAdjacentHTML('afterbegin', decodeURIComponent('{$encoded}'))";
        $PAGE->requires->js_init_call($js);
    }
}
