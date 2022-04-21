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
 * Report page.
 *
 * @package     local_submissionrestrict
 * @copyright   2021 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_submissionrestrict\report_table;

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$pagecontextid = required_param('pagecontextid', PARAM_INT);
$category = optional_param('category', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$perpage = optional_param('perpage', 30, PARAM_INT);

$context = context::instance_by_id($pagecontextid);

$defaultcategoryid = $category;
$category = core_course_category::get($defaultcategoryid);

require_login();
require_capability('local/submissionrestrict:overridereport', $context);

$baseurl = new moodle_url('/local/submissionrestrict/report.php', [
    'pagecontextid' => $context->id
]);

$PAGE->set_context($context);
$PAGE->set_url($baseurl);

$filters = [
    'category' => $defaultcategoryid,
];

$mform = new \local_submissionrestrict\report_form($baseurl->out(false), ['categoryid' => $defaultcategoryid]);

if ($data = $mform->get_data()) {
    $filters['category'] = $data->category;
} else {
    $filters['category'] = $defaultcategoryid;
}

foreach ($filters as $name => $value) {
    $baseurl->param($name, $value);
}

$table = new report_table('local_submissionrestrict_report', $filters, $page, $perpage);
$table->is_downloading($download, 'submission_overrides_report', get_string('report:title', 'local_submissionrestrict'));
$output = $PAGE->get_renderer('local_submissionrestrict');

if (!$table->is_downloading()) {
    $PAGE->navbar->add(get_string('report:title', 'local_submissionrestrict'));
    $PAGE->set_pagelayout('report');
    $PAGE->set_title(get_string('report:title', 'local_submissionrestrict'));
    $PAGE->set_heading(get_string('report:title', 'local_submissionrestrict'));

    echo $output->header();
    echo $output->heading(get_string('report:title', 'local_submissionrestrict'));
    echo $mform->render();
}

$table->define_baseurl($baseurl->out(false));
$table->out($perpage, true);

if (!$table->is_downloading()) {
    echo $output->footer();
}
