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

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Filters for report table..
 *
 * @package    local_submissionrestrict
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_form extends moodleform {

    /**
     * Defines forms elements.
     *
     * @return void
     */
    public function definition() {

        $mform = $this->_form;
        $selectedcategoryid = $this->_customdata['categoryid'];
        $categoryoptions = [];

        if (is_siteadmin()) {
            $categoryoptions[0] = get_string('all');
        }

        $mform->addElement('header', 'filters', get_string('report:filters', 'local_submissionrestrict'));

        $categoryoptions += \core_course_category::make_categories_list('local/submissionrestrict:override');
        $mform->addElement('select', 'category', get_string('category'), $categoryoptions);
        $mform->setType('category', PARAM_INT);
        $mform->setDefault('category', $selectedcategoryid);
        $mform->addElement('submit', 'submitbutton', get_string('search'));
    }

}
