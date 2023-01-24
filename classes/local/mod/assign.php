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
     * @param string $prefix A prefix to use for custom fields.
     *
     * @return bool
     */
    private function is_new_date_overridden(array $newdatevalue, array $submittedvalues, string $prefix = ''): bool {
        if (!empty($newdatevalue['overridden'])) {
            $hour = $prefix . 'hour';
            $minute = $prefix . 'minute';
            $reason = $prefix . 'reason';
            return isset($submittedvalues[$hour]) && isset($submittedvalues[$minute]) && isset($submittedvalues[$reason]);
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

                rebuild_course_cache($gradeitem->courseid, false, true);
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
        $cmid = 0;
        if ($cm = $modform->get_coursemodule()) {
            $cmid = $cm->id;
        }

        $this->replace_date_field(
            $form,
            $cmid,
            'duedate',
            self::NEW_DUEDATE_FORM_FIELD,
            'overridengr',
            'cutoffdate'
        );
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

        // This is a very hacky way of making sure that duedate field is set to a new value based on data in the different field.
        // Replace a value of the current duedate field (field should be set hidden in coursemodule_standard_elements)
        // with a new data if actual submit button pressed (ignoring unlock completion button).
        if ($form->isSubmitted() && !$modform->no_submit_button_pressed() && $form->elementExists(self::NEW_DUEDATE_FORM_FIELD) ) {
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

            // Hack detected.
            // We are setting duedate  with a freshly calculated value and then resubmitting all values in the form.
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
        $this->form_post_actions(
            $moduleinfo,
            $moduleinfo->coursemodule,
            self::NEW_DUEDATE_FORM_FIELD,
            $moduleinfo->duedate
        );

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
        return $this->validate_dates_fields(
            $data,
            'duedate',
            self::NEW_DUEDATE_FORM_FIELD,
            'overridengr',
            'allowsubmissionsfromdate'
        );
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

    /**
     * Replace a  date field with a custom field.
     * This will replace due date field in the form with custom date and time limited field.
     *
     * @param \MoodleQuickForm $form
     * @param string $cmid  Course module id.
     * @param string $oldfield Old field name.
     * @param string $newfield New field name.
     * @param string $overridefield Override field name.
     * @param string $addbeforefield Field name to add a new field before.
     * @param string $prefix A prefix to use for custom fields.
     */
    private function replace_date_field(MoodleQuickForm $form, string $cmid, string $oldfield, string $newfield,
                                        string $overridefield, string $addbeforefield, string $prefix = '') {
        global $CFG;

        MoodleQuickForm::registerElementType(
            'datetimelimited',
            $CFG->dirroot . '/local/submissionrestrict/classes/datetime_limited.php',
            'local_submissionrestrict\datetime_limited'
        );

        // Make due date element hidden.
        // We need date field in the form to make sure that we save it to DB when the form is getting processed later on.
        // We will update the value of date in definition_after_data method, so we can set whatever is set in our new field.
        $form->removeElement($oldfield);
        $form->addElement('hidden', $oldfield);
        $form->setType($oldfield, PARAM_INT);

        // Add a custom element to actually replace old date element.
        $newelement = $form->createElement('datetimelimited', $newfield, get_string('duedate', 'assign'), [
            'optional' => true,
            'timeslots' => $this->get_available_time_slots(),
            'override' => $this->has_override_permissions()
        ]);
        $form->insertElementBefore($newelement, $addbeforefield);
        $form->addHelpButton($newfield, 'duedate', 'assign');

        $form->setDefault($newfield, $form->getElementValue($oldfield));
        // Need to unset, as we use this method in a loop, but it's passed by a reference further in the forms API.
        unset($newelement);

        $newelementhour = $prefix . 'hour';
        $newelementminute = $prefix . 'minute';
        $newelementreason = $prefix . 'reason';
        $newelementstatic = $prefix . 'static';

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
            $overridengroup[] = $form->createElement('select', $newelementhour, get_string('hour', 'form'), $hours);
            $overridengroup[] = $form->createElement('select', $newelementminute, get_string('minute', 'form'), $minutes);
            $overridengroup[] = $form->createElement('select', $newelementreason, '', $this->get_reason_options());
            $overridengr = $form->createElement('group', $overridefield, '', $overridengroup, ['&nbsp;'], false);
            $form->insertElementBefore($overridengr, $addbeforefield);
            $form->addHelpButton($overridefield, 'reasons', 'local_submissionrestrict');

            unset($overridengr);

            // Disable fields if a new due date is not enabled.
            $fieldenabled = $newfield  . '[enabled]';
            $form->disabledIf($newelementhour, $fieldenabled);
            $form->disabledIf($newelementminute, $fieldenabled);
            $form->disabledIf($newelementreason, $fieldenabled);

            // Hide overridden time until Other option is selected.
            $fieldtime = $newfield  . '[time]';
            $form->hideIf($overridefield, $fieldtime, 'neq', datetime_limited::OTHER_VALUE);
        }

        // We would like to apply default values from a new overridden date (option Other is selected)
        // to all required fields.
        // However, if a user doesn't have permissions to use Other option, we will render an overridden date
        // as a text to avoid users without permissions to chnage overridden values.
        if ($cmid) {
            if ($restrictrecord = $this->get_restriction_record($cmid)) {
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

                    $form->setDefault($newfield, $midnight);
                    $form->setDefault($newelementhour, $currentdate['hours']);
                    $form->setDefault($newelementminute, $currentdate['minutes']);
                    $form->setDefault($newelementreason, $restrictrecord->get('reason'));

                } else {
                    // If we can't use Other option, then replace a form element with a text.
                    $form->removeElement($newfield);

                    $date = userdate($newdate) . \html_writer::empty_tag('br')
                        . get_string('reasonforvariation', 'local_submissionrestrict') . ': '
                        . $restrictrecord->get('reason');

                    $staticelement = $form->createElement('static', $newelementstatic, get_string('duedate', 'assign'),  $date);
                    $form->insertElementBefore($staticelement, $addbeforefield);
                    $form->addHelpButton($newelementstatic, 'duedate', 'assign');
                    // Need to unset, as we use this method in a loop, but it's passed by a reference further in the forms API.
                    unset($staticelement);
                }
            }
        }
    }

    /**
     * Validate assign dates form submission.
     *
     * @param array $data Data to validate.
     * @param string $oldfield Old field name.
     * @param string $newfield New field name.
     * @param string $overridefield Override field name.
     * @param string $allowsubmissionsfield Field for allow submission from field. It's used in validation.
     * @param string $prefix A prefix to use for custom fields.
     *
     * @return array
     */
    private function validate_dates_fields(array $data, string $oldfield, string $newfield,
                                           string $overridefield, string $allowsubmissionsfield, string $prefix = ''): array {
        $errors = [];

        $elementhour = $prefix . 'hour';
        $elementminute = $prefix . 'minute';
        $elementreason = $prefix . 'reason';

        if (isset($data[$elementreason]) && empty($data[$elementreason])) {
            $errors[$overridefield] = get_string('error:reasonrequired', 'local_submissionrestrict');
        }

        // Cover a scenario when due date is set to 0 as a new overridden due date is taking advantage.
        if (!empty($data[$allowsubmissionsfield]) && isset($data[$newfield]['time'])) {
            if (!empty($data[$newfield]['overridden'])) {
                $field = $overridefield;
                $time = new time($data[$elementhour], $data[$elementminute]);
                $newdate = helper::calculate_new_time($data[$newfield]['time'], $time);

                if (is_null($newdate)) {
                    $newdate = $data[$newfield]['time'];
                }
            } else {
                $field = $newfield;
                $newdate = $data[$newfield]['time'];
            }

            if ($newdate < $data[$allowsubmissionsfield]) {
                $errors[$field] = get_string('duedatevalidation', 'assign');
            }
        }

        // Cover a scenario when overridden due date is displayed as a text, but the actual due date is hidden,
        // so we can't display an error against due date field.
        if (!empty($data[$allowsubmissionsfield]) && !empty($data[$oldfield])) {
            if ($data[$oldfield] < $data[$allowsubmissionsfield]) {
                $errors[$allowsubmissionsfield] = get_string('duedatevalidation', 'assign');
            }
        }

        return $errors;
    }

    /**
     * Form post actions.
     *
     * In this method we just save submitted value if it's overridden or delete it from custom tables if not.
     * Actual value setting is happening in definition_after_data method.
     *
     * @param \stdClass $data
     * @param string $cmid Course module id.
     * @param string $newfield New field name.
     * @param string $value Submitted value.
     * @param string $prefix A prefix to use for custom fields.
     *
     * @throws \coding_exception
     */
    private function form_post_actions(stdClass $data, string $cmid, string $newfield, string $value, string $prefix = '') {
        $restrictrecord = $this->get_restriction_record($cmid);
        $newelementreason = $prefix . 'reason';

        if (!empty($data->{$newfield})) {
            if ($this->is_new_date_overridden($data->{$newfield}, (array)$data, $prefix)) {
                if ($restrictrecord) {
                    $restrictrecord->set('newdate', $value);
                } else {
                    $restrictrecord = new restrict();
                    $restrictrecord->set('cmid', $cmid);
                    $restrictrecord->set('newdate', $value);
                    $restrictrecord->set('modname', $this->get_name());
                }
                $restrictrecord->set('reason', $data->{$newelementreason});
                $restrictrecord->save();
            } else {
                // A new due date is set to one of the standard option.
                // We need to clean up and delete overridden record if exists.
                if ($restrictrecord) {
                    $restrictrecord->delete();
                }
            }
        } else {
            if ($restrictrecord && isset($data->{$newfield})) {
                $restrictrecord->delete();
            }
        }
    }

    /**
     * Modify report edit dates form.
     *
     * @param \report_editdates_form $dform  Report form instance.
     * @param \MoodleQuickForm $form Actual form instance.
     */
    public function report_editdates_form_elements($dform, MoodleQuickForm $form): void {
        foreach ($form->_elements as $element) {
            $elementname = $element->getName();

            $cmid = $this->get_cmid_from_element_name($elementname);
            if (empty($cmid)) {
                continue;
            }

            $cminfo = $dform->get_modinfo()->get_cm($cmid);
            if ($cminfo->modname != 'assign') {
                continue;
            }

            if ($this->get_date_field_name_from_element_name($elementname) == 'duedate') {

                $overridengrelementname = 'overridengr_' . $cmid . '_' . 'assign';
                $cutoffdateelementname = str_replace('duedate', 'cutoffdate', $elementname);

                $this->replace_date_field(
                    $form,
                    $cmid,
                    $elementname,
                    $this->build_new_element_name($cmid),
                    $overridengrelementname,
                    $cutoffdateelementname,
                    $this->build_field_prefix($cmid)
                );
            }
        }
    }

    /**
     * Validate report edit dates form.
     *
     * @param \report_editdates_form $dform  Report form instance.
     * @param array $data Submitted values.
     *
     * @return array
     */
    public function report_editdates_form_validation($dform, array $data): array {
        $errors = [];

        foreach ($data as $elementname => $value) {

            $cmid = $this->get_cmid_from_element_name($elementname);
            if (empty($cmid)) {
                continue;
            }

            $cminfo = $dform->get_modinfo()->get_cm($cmid);
            if ($cminfo->modname != 'assign') {
                continue;
            }

            if ($this->get_date_field_name_from_element_name($elementname) == 'duedate') {
                $overridengrelementname = 'overridengr_' . $cmid . '_' . 'assign';
                $allowsubmissionsfromdatefield = str_replace('duedate', 'allowsubmissionsfromdate', $elementname);

                $errors = array_merge($errors, $this->validate_dates_fields(
                    $data,
                    $elementname,
                    $this->build_new_element_name($cmid),
                    $overridengrelementname,
                    $allowsubmissionsfromdatefield,
                    $this->build_field_prefix($cmid)
                ));
            }
        }

        return $errors;
    }

    /**
     * Extend report edit form after data is already set.
     *
     * @param \report_editdates_form $dform  Report form instance.
     * @param MoodleQuickForm $form Form instance.
     */
    public function report_editdates_form_definition_after_data($dform, MoodleQuickForm $form): void {
        // This is a very hacky way of making sure that a date field is set to a new value based on data in the custom field.
        if ($form->isSubmitted()) {
            $resubmit = false; // We will need to resubmit all values later. Maybe.
            $values = $form->getSubmitValues();

            foreach ($form->_elements as $element) {
                $elementname = $element->getName();

                $cmid = $this->get_cmid_from_element_name($elementname);
                if (empty($cmid)) {
                    continue;
                }

                $cminfo = $dform->get_modinfo()->get_cm($cmid);
                if ($cminfo->modname != 'assign') {
                    continue;
                }

                if ($this->get_date_field_name_from_element_name($elementname) == 'duedate') {

                    $newelementname = $this->build_new_element_name($cmid);
                    $newelementhour = $this->build_field_prefix($cmid) . 'hour';
                    $newelementminute = $this->build_field_prefix($cmid) . 'minute';

                    if (!$form->elementExists($newelementname)) {
                        continue;
                    }

                    $customelement = $form->getElement($newelementname);
                    $submittedvalue = $form->getSubmitValue($newelementname);
                    $exportedvalue = $customelement->exportValue($submittedvalue);

                    $newduedate = $form->getSubmitValue($elementname);

                    if (empty($exportedvalue)) {
                        $newduedate = 0;
                    } else if ($this->is_new_date_overridden($exportedvalue, $values, $this->build_field_prefix($cmid))) {
                        $newduedate = helper::calculate_new_time(
                            $exportedvalue['time'],
                            new time($values[$newelementhour], $values[$newelementminute])
                        );
                        $newduedate = is_null($newduedate) ? $exportedvalue['time'] : $newduedate;
                    } else if (!empty($exportedvalue['time'])) {
                        $newduedate = $exportedvalue['time'];
                    }

                    // Hack detected.
                    // We are setting duedate  with a freshly calculated value and then resubmitting all values in the form.
                    $values[$elementname] = $newduedate;
                    // We need to resubmit all values as we need to set a new date.
                    $resubmit = true;
                }

                if ($resubmit) {
                    $form->updateSubmission($values, $form->_submitFiles);
                }
            }
        }
    }

    /**
     * POst submission actions.
     *
     * @param \stdClass $data Submitted data.
     * @param \stdClass $course Course.
     *
     * @return \stdClass
     */
    public function report_editdates_form_post_actions(stdClass $data, stdClass $course): stdClass {
        $modinfo = get_fast_modinfo($course);

        foreach ($data as $elementname => $elementvalue) {
            $cmid = $this->get_cmid_from_element_name($elementname);

            if (empty($cmid)) {
                continue;
            }

            $cminfo = $modinfo->get_cm($cmid);

            if ($cminfo->modname != 'assign') {
                continue;
            }

            if ($this->get_date_field_name_from_element_name($elementname) == 'duedate') {

                $this->form_post_actions(
                    $data,
                    $cmid,
                    $this->build_new_element_name($cmid),
                    $elementvalue,
                    $this->build_field_prefix($cmid)
                );
            }
        }

        return $data;
    }

    /**
     * Build prefix for fields based on cmid.
     *
     * @param string $cmid Course module id.
     * @return string
     */
    private function build_field_prefix(string $cmid): string {
        return $this->build_new_element_name($cmid) . '_';
    }

    /**
     * Build new element name based on cmid.
     *
     * @param string $cmid Course module id.
     * @return string
     */
    private function build_new_element_name($cmid): string {
        return self::NEW_DUEDATE_FORM_FIELD . '_' . $cmid . '_' . 'assign';;
    }

    /**
     * Get course module id from element name.
     *
     * Fields in report edit dates built as date_mod_{cmid}_{datefieldname}.
     *
     * @param string|null $elementname Name of the element.
     * @return string|null
     */
    private function get_cmid_from_element_name(?string $elementname): ?string {
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
     * Get date field name from element name.
     *
     * Fields in report edit dates built as date_mod_{cmid}_{datefieldname}.
     *
     * @param string|null $elementname Name of the element.
     * @return string|null
     */
    private function get_date_field_name_from_element_name(string $elementname): ?string {
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

}
