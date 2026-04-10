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
use local_submissionrestrict\report_editdates;
use local_submissionrestrict\restrict;
use local_submissionrestrict\time;
use local_submissionrestrict\local\admin\admin_setting_configreasons;
use mod_quiz\local\quiz_overrides_cache_manager;
use moodleform_mod;
use MoodleQuickForm;
use stdClass;

/**
 * Submission restriction for quiz activity.
 *
 * @package     local_submissionrestrict
 * @copyright   2025 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz extends mod_base {
    use report_editdates;

    /**
     * Custom date field name.
     */
    const NEW_TIME_CLOSE_FIELD = 'newtimeclose';


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
            ''
        ));

        $settings->add(new admin_setting_configreasons(
            "local_submissionrestrict/{$this->build_config_name('reasons')}",
            get_string('settings:reasons', 'local_submissionrestrict'),
            get_string('settings:reasons_desc', 'local_submissionrestrict'),
            ''
        ));
    }

    /**
     * Check if new date is overridden. AKA Other option is selected.
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
        $parsedreasons = admin_setting_configreasons::parse_reason_config($config);

        foreach ($parsedreasons as $label => $description) {
            $reasons[$label] = $label;
        }

        return $reasons;
    }

    /**
     * Returns the description for a stored reason label, or empty string if none.
     *
     * @return string
     */
    public function get_reason_description(string $reason): string {
        $config = get_config('local_submissionrestrict', $this->build_config_name('reasons'));
        $parsedreasons = admin_setting_configreasons::parse_reason_config($config);
        return $parsedreasons[$reason] ?? '';
    }

    /**
     * Returns true if the given user has a user-level or group-level override for the given course module.
     *
     * @param int $cmid The course module ID.
     * @param int $userid The user ID.
     * @return bool
     */
    public function has_user_or_group_override(int $cmid, int $userid): bool {
        global $DB;

        $sql = "SELECT q.id, q.course
                  FROM {quiz} q
                  JOIN {course_modules} cm ON cm.instance = q.id
                 WHERE cm.id = :cmid";
        $quiz = $DB->get_record_sql($sql, ['cmid' => $cmid], MUST_EXIST);

        if ($DB->record_exists('quiz_overrides', ['quiz' => $quiz->id, 'userid' => $userid])) {
            return true;
        }

        $groups = groups_get_user_groups($quiz->course, $userid);
        if (empty($groups[0])) {
            return false;
        }

        [$sql, $params] = $DB->get_in_or_equal(array_values($groups[0]));
        $params[] = $quiz->id;

        return $DB->record_exists_sql("SELECT 1 FROM {quiz_overrides} WHERE groupid $sql AND quiz = ?", $params);
    }

    /**
     * Reset dates.
     *
     * @param \grade_item $gradeitem
     */
    public function reset_submission_dates_by_grade_item(grade_item $gradeitem): void {
        global $DB;

        if ($record = $DB->get_record($this->get_name(), ['id' => $gradeitem->iteminstance])) {
            $needupdate = false;

            if ($record->timeclose > 0) {
                if ($newdate = helper::calculate_new_time($record->timeclose, $this->get_restore_time())) {
                    $record->timeclose = $newdate;
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
            'timeclose',
            self::NEW_TIME_CLOSE_FIELD,
            'overridengr',
            'timelimit'
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
            $form->setDefault(self::NEW_TIME_CLOSE_FIELD, 0);
        }

        // This is a very hacky way of making sure that time field is set to a new value based on data in the different field.
        // Replace a value of the current time field (field should be set hidden in coursemodule_standard_elements)
        // with a new data if actual submit button pressed (ignoring unlock completion button).
        if ($form->isSubmitted() && !$modform->no_submit_button_pressed() && $form->elementExists(self::NEW_TIME_CLOSE_FIELD)) {
            $element = $form->getElement(self::NEW_TIME_CLOSE_FIELD);
            $submittedvalue = $form->getSubmitValue(self::NEW_TIME_CLOSE_FIELD);
            $exportedvalue = $element->exportValue($submittedvalue);
            $values = $form->getSubmitValues();

            $newtime = $form->getSubmitValue('timeclose');

            if (empty($exportedvalue)) {
                $newtime = 0;
            } else if ($this->is_new_date_overridden($exportedvalue, $values)) {
                $newtime = helper::calculate_new_time($exportedvalue['time'], new time($values['hour'], $values['minute']));
                $newtime = is_null($newtime) ? $exportedvalue['time'] : $newtime;
            } else if (!empty($exportedvalue['time'])) {
                $newtime = $exportedvalue['time'];
            }

            // Hack detected.
            // We are setting time with a freshly calculated value and then resubmitting all values in the form.
            $values['timeclose'] = $newtime;
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
            self::NEW_TIME_CLOSE_FIELD,
            $moduleinfo->timeclose
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
            'timeclose',
            self::NEW_TIME_CLOSE_FIELD,
            'overridengr',
            'timeopen'
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
     * Update calendar events for provided instance.
     *
     * @param int $instanceid Instance id.
     */
    protected function update_calendar(int $instanceid): void {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $instanceid]);
        // Update the events relating to this quiz.
        quiz_update_events($quiz);
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
     * This will replace field in the form with custom date and time limited field.
     *
     * @param \MoodleQuickForm $form
     * @param string $cmid  Course module id.
     * @param string $oldfield Old field name.
     * @param string $newfield New field name.
     * @param string $overridefield Override field name.
     * @param string $addbeforefield Field name to add a new field before.
     * @param string $prefix A prefix to use for custom fields.
     */
    private function replace_date_field(
        MoodleQuickForm $form,
        string $cmid,
        string $oldfield,
        string $newfield,
        string $overridefield,
        string $addbeforefield,
        string $prefix = ''
    ) {
        global $CFG;

        MoodleQuickForm::registerElementType(
            'datetimelimited',
            $CFG->dirroot . '/local/submissionrestrict/classes/datetime_limited.php',
            'local_submissionrestrict\datetime_limited'
        );

        // Make date element hidden.
        // We need date field in the form to make sure that we save it to DB when the form is getting processed later on.
        // We will update the value of date in definition_after_data method, so we can set whatever is set in our new field.
        $form->removeElement($oldfield);
        $form->addElement('hidden', $oldfield);
        $form->setType($oldfield, PARAM_INT);

        // Add a custom element to actually replace old date element.
        $newelement = $form->createElement('datetimelimited', $newfield, get_string('quizclose', 'quiz'), [
            'optional' => true,
            'timeslots' => $this->get_available_time_slots(),
            'override' => $this->has_override_permissions(),
        ]);
        $form->insertElementBefore($newelement, $addbeforefield);

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

            // Disable fields if a new date is not enabled.
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

                    $staticelement = $form->createElement('static', $newelementstatic, get_string('quizclose', 'quiz'), $date);
                    $form->insertElementBefore($staticelement, $addbeforefield);
                    // Need to unset, as we use this method in a loop, but it's passed by a reference further in the forms API.
                    unset($staticelement);
                }
            }
        }
    }

    /**
     * Validate dates form submission.
     *
     * @param array $data Data to validate.
     * @param string $oldfield Old field name.
     * @param string $newfield New field name.
     * @param string $overridefield Override field name.
     * @param string $timeopenfield Field for allow submission from field. It's used in validation.
     * @param string $prefix A prefix to use for custom fields.
     *
     * @return array
     */
    private function validate_dates_fields(
        array $data,
        string $oldfield,
        string $newfield,
        string $overridefield,
        string $timeopenfield,
        string $prefix = ''
    ): array {
        $errors = [];

        $elementhour = $prefix . 'hour';
        $elementminute = $prefix . 'minute';
        $elementreason = $prefix . 'reason';

        if (isset($data[$elementreason]) && empty($data[$elementreason])) {
            $errors[$overridefield] = get_string('error:reasonrequired', 'local_submissionrestrict');
        }

        // Cover a scenario when date is set to 0 as a new overridden date is taking advantage.
        if (!empty($data[$timeopenfield]) && isset($data[$newfield]['time'])) {
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

            if ($newdate < $data[$timeopenfield]) {
                $errors[$field] = get_string('closebeforeopen', 'quiz');
            }
        }

        // Check open and close times are consistent.
        if (
            $data[$timeopenfield] != 0 && $data[$newfield] != 0 &&
            $data[$newfield] < $data[$timeopenfield]
        ) {
            $errors[$newfield] = get_string('closebeforeopen', 'quiz');
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
                // A new date is set to one of the standard option.
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

            $cmid = $this->report_get_cmid_from_element_name($elementname);
            if (empty($cmid)) {
                continue;
            }

            $cminfo = $dform->get_modinfo()->get_cm($cmid);
            if ($cminfo->modname != $this->get_name()) {
                continue;
            }

            if ($this->report_get_date_field_name_from_element_name($elementname) == 'timeclose') {
                $overridengrelementname = 'overridengr_' . $cmid . '_' . $this->get_name();
                $addbeforeelement = 'modrestrict' . $cmid;

                $this->replace_date_field(
                    $form,
                    $cmid,
                    $elementname,
                    $this->build_new_element_name($cmid, self::NEW_TIME_CLOSE_FIELD),
                    $overridengrelementname,
                    $addbeforeelement,
                    $this->build_field_prefix($cmid, self::NEW_TIME_CLOSE_FIELD)
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
            $cmid = $this->report_get_cmid_from_element_name($elementname);
            if (empty($cmid)) {
                continue;
            }

            $cminfo = $dform->get_modinfo()->get_cm($cmid);
            if ($cminfo->modname != $this->get_name()) {
                continue;
            }

            if ($this->report_get_date_field_name_from_element_name($elementname) == 'timeclose') {
                $overridengrelementname = 'overridengr_' . $cmid . '_' . $this->get_name();
                $timeopenfield = str_replace('timeclose', 'timeopen', $elementname);

                $errors = array_merge($errors, $this->validate_dates_fields(
                    $data,
                    $elementname,
                    $this->build_new_element_name($cmid, self::NEW_TIME_CLOSE_FIELD),
                    $overridengrelementname,
                    $timeopenfield,
                    $this->build_field_prefix($cmid, self::NEW_TIME_CLOSE_FIELD)
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

                $cmid = $this->report_get_cmid_from_element_name($elementname);
                if (empty($cmid)) {
                    continue;
                }

                $cminfo = $dform->get_modinfo()->get_cm($cmid);
                if (
                    $cminfo->modname !=
                    $this->get_name()
                ) {
                    continue;
                }

                if ($this->report_get_date_field_name_from_element_name($elementname) == 'timeclose') {
                    $newelementname = $this->build_new_element_name($cmid, self::NEW_TIME_CLOSE_FIELD);
                    $newelementhour = $this->build_field_prefix($cmid, self::NEW_TIME_CLOSE_FIELD) . 'hour';
                    $newelementminute = $this->build_field_prefix($cmid, self::NEW_TIME_CLOSE_FIELD) . 'minute';

                    if (!$form->elementExists($newelementname)) {
                        continue;
                    }

                    $customelement = $form->getElement($newelementname);
                    $submittedvalue = $form->getSubmitValue($newelementname);
                    $exportedvalue = $customelement->exportValue($submittedvalue);

                    $newduedate = $form->getSubmitValue($elementname);
                    $prefix = $this->build_field_prefix($cmid, self::NEW_TIME_CLOSE_FIELD);

                    if (empty($exportedvalue)) {
                        $newduedate = 0;
                    } else if ($this->is_new_date_overridden($exportedvalue, $values, $prefix)) {
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
            $cmid = $this->report_get_cmid_from_element_name($elementname);

            if (empty($cmid)) {
                continue;
            }

            $cminfo = $modinfo->get_cm($cmid);

            if ($cminfo->modname != $this->get_name()) {
                continue;
            }

            if ($this->report_get_date_field_name_from_element_name($elementname) == 'timeclose') {
                $this->form_post_actions(
                    $data,
                    $cmid,
                    $this->build_new_element_name($cmid, self::NEW_TIME_CLOSE_FIELD),
                    $elementvalue,
                    $this->build_field_prefix($cmid, self::NEW_TIME_CLOSE_FIELD)
                );
            }
        }

        return $data;
    }
}
