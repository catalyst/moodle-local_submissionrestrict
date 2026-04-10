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
    /** @var array{0: mixed, 1: string}|null|false False means uncached, null means no notification. */
    private array|null|false $notificationdata = false;

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

        if (!isset($PAGE->cm->id)) {
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

        $data = $this->get_notification_data();
        if ($data === null) {
            return false;
        }

        [$mod] = $data;
        return !$mod->has_user_or_group_override($PAGE->cm->id, $USER->id);
    }

    /**
     * Injects the notification for client-side insertion.
     */
    public function inject_notification(): void {
        global $PAGE;

        $data = $this->get_notification_data();
        if ($data === null) {
            return;
        }

        [$mod, $description] = $data;

        $notification = new \core\output\notification(
            format_text($description, FORMAT_PLAIN),
            \core\output\notification::NOTIFY_INFO,
            true,
            get_string('activitynotificationtitle', 'local_submissionrestrict'),
        );
        $html = $PAGE->get_renderer('core')->render($notification);

        // We use inline JS to insert the notification banner rather than an AMD module or
        // AJAX/web service, for two reasons:
        //
        // 1. core\output\notification only honours the custom title parameter when rendered
        // directly. It cannot be set through the standard notification APIs. Rendering to HTML
        // ourselves is the only way to preserve it.
        //
        // 2. The description text can be arbitrarily long, making it unsuitable to pass as
        // a module argument. A dedicated AMD module would also be disproportionate for a
        // single insertAdjacentHTML call with no other dependencies.
        //
        // The rendered HTML is URL-encoded so it can be safely embedded as an inline string literal.
        $encoded = rawurlencode($html);
        $js = "var notifications = document.getElementById('user-notifications');" .
              "if (notifications) {" .
              "notifications.insertAdjacentHTML('afterbegin', decodeURIComponent('{$encoded}'));" .
              "}";
        $PAGE->requires->js_init_code($js, true);
    }

    /**
     * Returns the mod and restriction description required to display the notification,
     * or null if any prerequisite condition is not met.
     *
     * @return array{0: mixed, 1: string}|null Tuple of [$mod, $description], or null if notification should not be shown.
     */
    private function get_notification_data(): ?array {
        global $PAGE;

        if ($this->notificationdata !== false) {
            return $this->notificationdata;
        }

        if (!$this->is_mod_view_page()) {
            return $this->notificationdata = null;
        }

        $mods = mod_manager::get_functional_mods();
        if (!isset($mods[$PAGE->cm->modname])) {
            return $this->notificationdata = null;
        }

        $restrict = restrict::get_record(['cmid' => $PAGE->cm->id]);
        if (!$restrict || empty($restrict->get('reason'))) {
            return $this->notificationdata = null;
        }

        $mod = $mods[$PAGE->cm->modname];
        $description = $mod->get_reason_description($restrict->get('reason'));
        if (empty($description)) {
            return $this->notificationdata = null;
        }

        return $this->notificationdata = [$mod, $description];
    }
}
