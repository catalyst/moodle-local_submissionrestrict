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

use admin_settingpage;
use admin_setting_heading;
use admin_setting_configcheckbox;
use admin_setting_configtime;

/**
 * Base mod restriction class.
 *
 * @package     local_submissionrestict
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
            "local_submissionrestict/{$this->build_config_name('header')}",
            ucfirst($this->get_name()),
            '')
        );

        $settings->add(new admin_setting_configcheckbox(
            "local_submissionrestict/{$this->build_config_name('restore_enabled')}",
            get_string('settings:restore_enabled', 'local_submissionrestict'),
            get_string('settings:restore_enabled_desc', 'local_submissionrestict'),
            0)
        );

        $settings->add(new admin_setting_configtime(
            "local_submissionrestict/{$this->build_config_name('restore_hour')}",
            "{$this->build_config_name('restore_minute')}",
            get_string('settings:restore', 'local_submissionrestict'),
            get_string('settings:restore_desc', 'local_submissionrestict'),
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
     * Reset submission dates by provided grade item.
     *
     * @param \grade_item $gradeitem
     */
    abstract public function reset_submission_dates_by_grade_item(\grade_item $gradeitem): void;

    /**
     * Check if restore reset is enabled for the given mod.
     * @return bool
     */
    final public function is_restore_reset_enabled(): bool {
        return (bool)get_config('local_submissionrestict', $this->build_config_name('restore_enabled'));
    }

    /**
     * Get a new time to force restored assignemnts to.
     * @return \local_submissionrestict\time
     */
    final public function get_restore_time(): time {
        return new time(
            (int)get_config('local_submissionrestict', $this->build_config_name('restore_hour')),
            (int)get_config('local_submissionrestict', $this->build_config_name('restore_minute'))
        );
    }

}
