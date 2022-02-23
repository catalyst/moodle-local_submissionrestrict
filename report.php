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
 * @package     local_submissionrestict
 * @copyright   2021 Catalyst IT
 * @author      Dmitrii Metelkin (dmitriim@catalyst-au.net)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_submissionrestict\report_table;

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('local_submissionrestict_report');

$page = optional_param('page', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$perpage = optional_param('perpage', 30, PARAM_INT);

$baseurl = new moodle_url('/local/submissionrestict/report.php');
$PAGE->set_url($baseurl->out(false));
$PAGE->set_context(context_system::instance());

// TODO: Replace with a form with filters.
$filters = [];

$table = new report_table('local_submissionrestict_report', $filters, $page, $perpage, !empty($download));
$table->is_downloading($download, 'report', get_string('report:title', 'local_submissionrestict'));
$output = $PAGE->get_renderer('local_submissionrestict');

if (!$table->is_downloading()) {
    $PAGE->set_pagelayout('report');
    $PAGE->set_title(get_string('report:title', 'local_submissionrestict'));
    $PAGE->set_heading(get_string('report:heading', 'local_submissionrestict'));
    echo $output->header();
}

$table->define_baseurl($baseurl->out(false));
$table->out($perpage, true);

if (!$table->is_downloading()) {
    echo $output->footer();
}
