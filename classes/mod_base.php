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

use admin_settingpage;
use admin_setting_heading;
use admin_setting_configcheckbox;
use admin_setting_configtime;
use moodleform_mod;
use MoodleQuickForm;
use stdClass;

/**
 * Base mod restriction class.
 *
 * @package     local_submissionrestrict
 * @copyright   2021 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class mod_base {

    /**
     * Returns name of the activity restriction instance.
     * @return string
     */
    public function get_name(): string {
        return substr(strrchr(get_class($this), '\\'), 1);
    }

    /**
     * Build a config name for the current mod.
     *
     * @param string $configname A name of the config.
     * @return string
     */
    final public function build_config_name(string $configname): string {
        return "{$this->get_name()}_{$configname}";
    }

    /**
     * Add sub plugin settings to the admin setting page for the plugin.
     *
     * @param \admin_settingpage $settings
     */
    final public function add_settings(admin_settingpage $settings): void {
        $settings->add(new admin_setting_heading(
            "local_submissionrestrict/{$this->build_config_name('header')}",
            ucfirst($this->get_name()),
            '')
        );

        $settings->add(new admin_setting_configcheckbox(
            "local_submissionrestrict/{$this->build_config_name('restore_enabled')}",
            get_string('settings:restore_enabled', 'local_submissionrestrict'),
            get_string('settings:restore_enabled_desc', 'local_submissionrestrict'),
            0)
        );

        $settings->add(new admin_setting_configtime(
            "local_submissionrestrict/{$this->build_config_name('restore_hour')}",
            "{$this->build_config_name('restore_minute')}",
            get_string('settings:restore', 'local_submissionrestrict'),
            get_string('settings:restore_desc', 'local_submissionrestrict'),
            ['h' => 0, 'm' => 0])
        );

        $this->add_extra_settings($settings);
    }

    /**
     * Add extra settings if required.
     *
     * @param \admin_settingpage $settings
     */
    protected function add_extra_settings(admin_settingpage $settings): void {

    }

    /**
     * Extend course module form.
     *
     * @param \moodleform_mod $modform Mod form instance.
     * @param \MoodleQuickForm $form Form instance.
     */
    abstract public function coursemodule_standard_elements(moodleform_mod $modform, MoodleQuickForm $form): void;

    /**
     * Extend course module form submission.
     *
     * @param stdClass $moduleinfo Module info data.
     * @param stdClass $course Course instance.
     *
     * @return stdClass Mutated module info data.
     */
    abstract public function coursemodule_edit_post_actions(stdClass $moduleinfo, stdClass $course): stdClass;

    /**
     * Extend course mod form validation.
     *
     * @param \moodleform_mod $modform Mod form instance.
     * @param array $data Submitted data.
     *
     * @return array
     */
    abstract public function coursemodule_validation(moodleform_mod $modform, array $data): array;

    /**
     * Extend course module form after data is already set.
     *
     * @param \moodleform_mod $modform Mod form instance.
     * @param \MoodleQuickForm $form Form instance.
     */
    abstract public function coursemodule_definition_after_data(moodleform_mod $modform, MoodleQuickForm $form): void;

    /**
     * Reset submission dates by provided grade item.
     *
     * @param \grade_item $gradeitem
     */
    abstract public function reset_submission_dates_by_grade_item(\grade_item $gradeitem): void;

    /**
     * Course module delete hook.
     *
     * @param \cm_info $cm Course module.
     */
    public function pre_course_module_delete(\cm_info $cm): void {
        if ($record = $this->get_restriction_record($cm->id)) {
            $record->delete();
        }
    }

    /**
     * Check if restore reset is enabled for the given mod.
     * @return bool
     */
    final public function is_restore_reset_enabled(): bool {
        return (bool)get_config('local_submissionrestrict', $this->build_config_name('restore_enabled'));
    }

    /**
     * Get a new time to force restored assignemnts to.
     * @return \local_submissionrestrict\time
     */
    final public function get_restore_time(): time {
        return new time(
            (int)get_config('local_submissionrestrict', $this->build_config_name('restore_hour')),
            (int)get_config('local_submissionrestrict', $this->build_config_name('restore_minute'))
        );
    }

    /**
     * Get restriction record by cmid.
     *
     * @param int $cmid Course module ID.
     * @return false|\local_submissionrestrict\restrict
     */
    public function get_restriction_record(int $cmid) {
        return restrict::get_record(['cmid' => $cmid]);
    }

    /**
     * Check if a user can override standard times.
     *
     * @param \context|null $context Context to check permissions for.
     * @return bool
     */
    public function has_override_permissions(\context $context = null): bool {
        global $COURSE;

        if (is_null($context)) {
            $context = \context_course::instance($COURSE->id);
        }

        return has_capability('local/submissionrestrict:override', $context);
    }

    /**
     * Check if the mod is functional.
     * @return bool
     */
    public function is_functional(): bool {
        return true;
    }

}
