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

/**
 * Lib functions.
 *
 * @package    local_submissionrestict
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_submissionrestict\mod_manager;

/**
 * Extend course module form.
 *
 * @param \moodleform_mod $modform Mod form instance.
 * @param \MoodleQuickForm $form Form instance.
 */
function local_submissionrestict_coursemodule_standard_elements(moodleform_mod $modform, MoodleQuickForm $form): void {
    $cm = $modform->get_coursemodule();
    $modname = '';

    // Coerce modname from course module if we are updating existing module.
    if (!empty($cm) && !empty($cm->modname)) {
        $modname = $cm->modname;
    } else if (!empty($modform->get_current()->modulename)) {
        $modname = $modform->get_current()->modulename;
    }

    if (!empty($modname)) {
        $mods = mod_manager::get_mods();
        if (!empty($mods[$modname])) {
            $mods[$modname]->coursemodule_standard_elements($modform, $form);
        }
    }
}


/**
 * Extend course module form after the data already set.
 *
 * @param \moodleform_mod $modform Mod form instance.
 * @param \MoodleQuickForm $form Form instance.
 */
function local_submissionrestict_coursemodule_definition_after_data(moodleform_mod $modform, MoodleQuickForm $form): void {
    $cm = $modform->get_coursemodule();
    $modname = '';

    if (!empty($cm) && !empty($cm->modname)) {
        $modname = $cm->modname;
    } else if (!empty($modform->get_current()->modulename)) {
        $modname = $modform->get_current()->modulename;
    }

    if (!empty($modname)) {
        $mods = mod_manager::get_mods();
        if (!empty($mods[$modname])) {
            $mods[$modname]->coursemodule_definition_after_data($modform, $form);
        }
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
function local_submissionrestict_coursemodule_edit_post_actions(stdClass $moduleinfo, stdClass $course): stdClass {
    if (!empty($moduleinfo->modulename)) {
        $mods = mod_manager::get_mods();
        if (!empty($mods[$moduleinfo->modulename])) {
            $moduleinfo = $mods[$moduleinfo->modulename]->coursemodule_edit_post_actions($moduleinfo, $course);
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
function local_submissionrestict_coursemodule_validation(moodleform_mod $modform, array $data): array {
    $errors = [];

    $cm = $modform->get_coursemodule();
    $modname = '';

    if (!empty($cm) && !empty($cm->modname)) {
        $modname = $cm->modname;
    } else if (!empty($modform->get_current()->modulename)) {
        $modname = $modform->get_current()->modulename;
    }

    if (!empty($modname)) {
        $mods = mod_manager::get_mods();
        if (!empty($mods[$modname])) {
            $errors = $mods[$modname]->coursemodule_validation($modform, $data);
        }
    }

    return $errors;
}

/**
 * Hook called before we delete a course module.
 *
 * @param \stdClass $cm The course module record.
 */
function local_submissionrestict_pre_course_module_delete($cm) {
    $modinfo = get_fast_modinfo($cm->course);
    if ($modinfo && isset($modinfo->cms[$cm->id])) {
        $cm = $modinfo->get_cm($cm->id);
        if (!empty($cm->modname)) {
            $mods = mod_manager::get_mods();
            if (!empty($mods[$cm->modname])) {
                $mods[$cm->modname]->pre_course_module_delete($cm);
            }
        }
    }
}

/**
 * Hook called before we delete a course.
 *
 * @param object $course The Moodle course object.
 */
function local_submissionrestict_pre_course_delete($course) {
    global $DB;

    // Cleanup course module related data.
    $modules = $DB->get_records('course_modules', ['course' => $course->id]);

    foreach ($modules as $module) {
        local_submissionrestict_pre_course_module_delete($module);
    }
}

/**
 * Extend category navigation.
 *
 * @param \navigation_node $nav Navigation node.
 * @param \context_coursecat $context Category context.
 */
function local_submissionrestict_extend_navigation_category_settings(navigation_node $nav, context_coursecat $context) {

    // Report link.
    if (has_capability('local/submissionrestict:overridereport', $context)) {
        $title = get_string('report:title', 'local_submissionrestict');
        $url = new moodle_url("/local/submissionrestict/report.php",
            ['category' => $context->instanceid, 'pagecontextid' => $context->id]
        );

        $nav->add_node(navigation_node::create(
            $title,
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/report', $title)
        ));
    }
}

