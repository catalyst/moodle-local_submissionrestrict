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

namespace local_submissionrestrict\local\mod;

use admin_settingpage;
use admin_setting_configtextarea;
use core_calendar\type_factory;
use local_submissionrestrict\datetime_limited;
use local_submissionrestrict\helper;
use local_submissionrestrict\mod_base;
use grade_item;
use local_submissionrestrict\restrict;
use local_submissionrestrict\time;
use moodleform_mod;
use MoodleQuickForm;
use stdClass;

/**
 * Submission restriction for assign.
 *
 * @package     local_submissionrestrict
 * @copyright   2021 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign extends mod_base {

    /**
     * Custom due date field name.
     */
    const NEW_DUEDATE_FORM_FIELD = 'newdate';

    /**
     * Add extra settings if required.
     *
     * @param \admin_settingpage $settings
     */
    protected function add_extra_settings(admin_settingpage $settings): void {
        parent::add_extra_settings($settings);

        $settings->add(new admin_setting_configtextarea(
            "local_submissionrestrict/{$this->build_config_name('timeslots')}",
            get_string('settings:timeslots', 'local_submissionrestrict'),
            get_string('settings:timeslots_desc', 'local_submissionrestrict'),
            '')
        );

        $settings->add(new admin_setting_configtextarea(
            "local_submissionrestrict/{$this->build_config_name('reasons')}",
            get_string('settings:reasons', 'local_submissionrestrict'),
            get_string('settings:reasons_desc', 'local_submissionrestrict'),
            '')
        );
    }

    /**
     * Check if new due date is overridden. AKA Other option is selected.
     *
     * @param array $newdatevalue New date element values.
     * @param array $submittedvalues Module info data.
     *
     * @return bool
     */
    private function is_new_date_overridden(array $newdatevalue, array $submittedvalues): bool {
        if (!empty($newdatevalue['overridden'])) {
            return isset($submittedvalues['hour']) && isset($submittedvalues['minute']) && isset($submittedvalues['reason']);
        }

        return false;
    }

    /**
     * Returns a list of configured available times.
     * @return string[]
     */
    protected function get_available_time_slots(): array {
        $timeslots = [];

        $config = get_config('local_submissionrestrict', $this->build_config_name('timeslots'));

        if (!empty($config)) {
            $items = explode("\n", str_replace("\r\n", "\n", $config));

            foreach ($items as $item) {
                $data = explode(':', $item);
                if (count($data) == 2 && !empty(trim($data[0])) && !empty(trim($data[1]))) {
                    $timeslots[trim($item)] = trim($item);
                }
            }
        }

        return $timeslots;
    }

    /**
     * Returns a list of configured reasons for selecting Other option.
     * @return string[]
     */
    protected function get_reason_options(): array {
        $reasons = [];
        $reasons[0] = get_string('reason', 'local_submissionrestrict');

        $config = get_config('local_submissionrestrict', $this->build_config_name('reasons'));

        if (!empty($config)) {
            $items = explode("\n", str_replace("\r\n", "\n", $config));

            foreach ($items as $item) {
                if (!empty(trim($item))) {
                    $reasons[trim($item)] = trim($item);
                }
            }
        }

        return $reasons;
    }

    /**
     * Reset due dates.
     *
     * @param \grade_item $gradeitem
     */
    public function reset_submission_dates_by_grade_item(grade_item $gradeitem): void {
        global $DB;

        if ($record = $DB->get_record($this->get_name(), ['id' => $gradeitem->iteminstance])) {
            $needupdate = false;

            if ($record->duedate > 0) {
                if ($newdate = helper::calculate_new_time($record->duedate, $this->get_restore_time())) {
                    $record->duedate = $newdate;
                    $needupdate = true;
                }
            }

            if ($record->cutoffdate > 0) {
                if ($newdate = helper::calculate_new_time($record->cutoffdate, $this->get_restore_time())) {
                    $record->cutoffdate = $newdate;
                    $needupdate = true;
                }
            }

            if ($needupdate) {
                $DB->update_record($this->get_name(), $record);
                $this->update_calendar($gradeitem->iteminstance);
            }
        }
    }

    /**
     * Extend course module form.
     *
     * @param \moodleform_mod $modform Mod form instance.
     * @param \MoodleQuickForm $form Form instance.
     */
    public function coursemodule_standard_elements(moodleform_mod $modform, MoodleQuickForm $form): void {
        global $CFG;

        MoodleQuickForm::registerElementType(
            'datetimelimited',
            $CFG->dirroot . '/local/submissionrestrict/classes/datetime_limited.php',
            'local_submissionrestrict\datetime_limited'
        );

        // Make due date element hidden.
        $form->removeElement('duedate');
        $form->addElement('hidden', 'duedate');
        $form->setType('duedate', PARAM_INT);

        // Add a custom element to actually replace old due date element.
        $element = $form->createElement('datetimelimited', self::NEW_DUEDATE_FORM_FIELD, get_string('duedate', 'assign'), [
            'optional' => true,
            'timeslots' => $this->get_available_time_slots(),
            'override' => $this->has_override_permissions()
        ]);
        $form->insertElementBefore($element, 'cutoffdate');
        $form->addHelpButton(self::NEW_DUEDATE_FORM_FIELD, 'duedate', 'assign');

        // If due date is set, then set ut as a default to a new replacement element.
        if (!empty($modform->get_current()->duedate)) {
            $form->setDefault(self::NEW_DUEDATE_FORM_FIELD, $modform->get_current()->duedate);
        }

        // If a user can use Other option, then let's add fields to be able to override time.
        if ($this->has_override_permissions()) {
            $hours = [];
            $minutes = [];
            for ($i = 0; $i <= 23; $i++) {
                $hours[$i] = sprintf("%02d", $i);
            }
            for ($i = 0; $i < 60; $i += 5) {
                $minutes[$i] = sprintf("%02d", $i);
            }

            $overridengroup = [];
            $overridengroup[] = $form->createElement('select', 'hour', get_string('hour', 'form'), $hours);
            $overridengroup[] = $form->createElement('select', 'minute', get_string('minute', 'form'), $minutes);
            $overridengroup[] = $form->createElement('select', 'reason', '', $this->get_reason_options());
            $overridengr = $form->createElement('group', 'overridengr', '', $overridengroup, ['&nbsp;'], false);
            $form->insertElementBefore($overridengr, 'cutoffdate');
            $form->addHelpButton('overridengr', 'reasons', 'local_submissionrestrict');

            // Disable fields if a new due date is not enabled.
            $fieldenabled = self::NEW_DUEDATE_FORM_FIELD  . '[enabled]';
            $form->disabledIf('hour', $fieldenabled);
            $form->disabledIf('minute', $fieldenabled);
            $form->disabledIf('reason', $fieldenabled);

            // Hide overridden time until Other option is selected.
            $fieldtime = self::NEW_DUEDATE_FORM_FIELD  . '[time]';
            $form->hideIf('overridengr', $fieldtime, 'neq', datetime_limited::OTHER_VALUE);
        }

        // We would like to apply default values from a new overridden date (option Other is selected)
        // to all required fields.
        // However, if a user doesn't have permissions to use Other option, we will render an overridden date
        // as a text to avoid users without permissions to chnage overridden values.
        if ($cm = $modform->get_coursemodule()) {
            if ($restrictrecord = $this->get_restriction_record($cm->id)) {
                // Getting overridden new date from DB.
                $newdate = $restrictrecord->get('newdate');

                if ($this->has_override_permissions()) {
                    // If we can use Other option, then let's set defaults of all fields, based on new date value.
                    $calendartype = type_factory::get_calendar_instance();
                    $currentdate = $calendartype->timestamp_to_date_array($newdate);

                    $midnight = helper::calculate_new_time($newdate, new time(0, 0));

                    if (is_null($midnight)) {
                        $midnight = $newdate;
                    }

                    $form->setDefault(self::NEW_DUEDATE_FORM_FIELD, $midnight);
                    $form->setDefault('hour', $currentdate['hours']);
                    $form->setDefault('minute', $currentdate['minutes']);
                    $form->setDefault('reason', $restrictrecord->get('reason'));

                } else {
                    // If we can't use Other option, then replace a form element with a text.
                    $form->removeElement(self::NEW_DUEDATE_FORM_FIELD);

                    $date = userdate($newdate) . \html_writer::empty_tag('br')
                        . get_string('reasonforvariation', 'local_submissionrestrict') . ': '
                        . $restrictrecord->get('reason');

                    $element = $form->createElement('static', 'date', get_string('duedate', 'assign'),  $date);
                    $form->insertElementBefore($element, 'cutoffdate');
                    $form->addHelpButton('date', 'duedate', 'assign');
                }
            }
        }
    }

    /**
     * Extend course module form after data is already set.
     *
     * @param \moodleform_mod $modform Mod form instance.
     * @param \MoodleQuickForm $form Form instance.
     */
    public function coursemodule_definition_after_data(moodleform_mod $modform, MoodleQuickForm $form): void {
        // Apply default global settings if creating a new activity.
        if (!$this->is_updating($modform)) {
            $config = get_config('assign');
            if (!empty($config->duedate_enabled)) {
                $form->setDefault(self::NEW_DUEDATE_FORM_FIELD, time() + $config->duedate);
            } else {
                $form->setDefault(self::NEW_DUEDATE_FORM_FIELD, 0);
            }
        }

        if ($form->isSubmitted() && $form->elementExists(self::NEW_DUEDATE_FORM_FIELD)) {
            $element = $form->getElement(self::NEW_DUEDATE_FORM_FIELD);
            $submittedvalue = $form->getSubmitValue(self::NEW_DUEDATE_FORM_FIELD);
            $exportedvalue = $element->exportValue($submittedvalue);
            $values = $form->getSubmitValues();

            $newduedate = $form->getSubmitValue('duedate');

            if (empty($exportedvalue)) {
                $newduedate = 0;
            } else if ($this->is_new_date_overridden($exportedvalue, $values)) {
                $newduedate = helper::calculate_new_time($exportedvalue['time'], new time($values['hour'], $values['minute']));
                $newduedate = is_null($newduedate) ? $exportedvalue['time'] : $newduedate;
            } else if (!empty($exportedvalue['time'])) {
                $newduedate = $exportedvalue['time'];
            }

            $values['duedate'] = $newduedate;
            $form->updateSubmission($values, $form->_submitFiles);
        }
    }

    /**
     * Extend course module form submission.
     *
     * @param stdClass $moduleinfo Module info data.
     * @param stdClass $course Course instance.
     *
     * @return stdClass Mutated module info data.
     */
    public function coursemodule_edit_post_actions(stdClass $moduleinfo, stdClass $course): stdClass {
        $restrictrecord = $this->get_restriction_record($moduleinfo->coursemodule);

        if (!empty($moduleinfo->{self::NEW_DUEDATE_FORM_FIELD})) {
            if ($this->is_new_date_overridden($moduleinfo->{self::NEW_DUEDATE_FORM_FIELD}, (array)$moduleinfo)) {
                if ($restrictrecord) {
                    $restrictrecord->set('newdate', $moduleinfo->duedate);
                } else {
                    $restrictrecord = new restrict();
                    $restrictrecord->set('cmid', $moduleinfo->coursemodule);
                    $restrictrecord->set('newdate', $moduleinfo->duedate);
                    $restrictrecord->set('modname', $this->get_name());
                }
                $restrictrecord->set('reason', $moduleinfo->reason);
                $restrictrecord->save();

            } else {
                // A new due date is set to one of the standard option. We need to clean up and delete overridden record if exists.
                if ($restrictrecord) {
                    $restrictrecord->delete();
                }
            }
        } else {
            if ($restrictrecord && isset($moduleinfo->{self::NEW_DUEDATE_FORM_FIELD})) {
                $restrictrecord->delete();
            }
        }

        return $moduleinfo;
    }

    /**
     * Extend course mod form validation.
     *
     * @param \moodleform_mod $modform Mod form instance.
     * @param array $data Submitted data.
     *
     * @return array
     */
    public function coursemodule_validation(moodleform_mod $modform, array $data): array {
        $errors = [];

        if (isset($data['reason']) && empty($data['reason'])) {
            $errors['overridengr'] = get_string('error:reasonrequired', 'local_submissionrestrict');
        }

        // Cover a scenario when due date is set to 0 as a new overridden due date is taking advantage.
        if (!empty($data['allowsubmissionsfromdate']) && isset($data[self::NEW_DUEDATE_FORM_FIELD]['time'])) {
            if (!empty($data[self::NEW_DUEDATE_FORM_FIELD]['overridden'])) {
                $field = 'overridengr';
                $time = new time($data['hour'], $data['minute']);
                $newdate = helper::calculate_new_time($data[self::NEW_DUEDATE_FORM_FIELD]['time'], $time);

                if (is_null($newdate)) {
                    $newdate = $data[self::NEW_DUEDATE_FORM_FIELD]['time'];
                }
            } else {
                $field = self::NEW_DUEDATE_FORM_FIELD;
                $newdate = $data[self::NEW_DUEDATE_FORM_FIELD]['time'];
            }

            if ($newdate < $data['allowsubmissionsfromdate']) {
                $errors[$field] = get_string('duedatevalidation', 'assign');
            }
        }

        // Cover a scenario when overridden due date is displayed as a text, but the actual due date is hidden,
        // so we can't display an error against due date field.
        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['duedate'])) {
            if ($data['duedate'] < $data['allowsubmissionsfromdate']) {
                $errors['allowsubmissionsfromdate'] = get_string('duedatevalidation', 'assign');
            }
        }

        return $errors;
    }

    /**
     * Check if the form being used for updating an existing instance.
     * @param \moodleform_mod $modform
     *
     * @return bool
     */
    protected function is_updating(moodleform_mod $modform): bool {
        return !empty($modform->get_coursemodule());
    }

    /**
     * Update calendar events for provided assignment.
     *
     * @param int $assignid Assignment instance id.
     */
    protected function update_calendar(int $assignid): void {
        list ($course, $cm) = get_course_and_cm_from_instance($assignid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);
        $assign->update_calendar($cm->id);
    }

    /**
     * Check if the mod is functional.
     * @return bool
     */
    public function is_functional(): bool {
        if (empty($this->get_available_time_slots())) {
            return false;
        }

        if (count($this->get_reason_options()) <= 1) {
            return false;

        }
        return true;
    }

}
