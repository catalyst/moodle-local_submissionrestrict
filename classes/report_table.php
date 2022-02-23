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

use table_sql;

/**
 * Report table
 *
 * @package    local_submissionrestict
 * @author     Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table extends table_sql {

    /**
     * @var array filters to apply to report data.
     */
    protected $filters;

    /**
     * report_table constructor.
     *
     * @param string $uniqueid a string identifying this table.
     * @param array $filters array of optional filters to apply to data.
     * @param int $page current page.
     * @param int $perpage results per page.
     * @param bool $downloading is this table being built for download?
     */
    public function __construct(string $uniqueid, array $filters = [], int $page = 0,
                                int $perpage = 30, bool $downloading = false) {
        parent::__construct($uniqueid);

        $this->show_download_buttons_at(array(TABLE_P_BOTTOM));
        $this->define_columns($this->get_columns());
        $this->define_headers($this->get_headers());
        $this->currpage = $page;
        $this->pagesize = $perpage;
        $this->use_pages = true;
        $this->filters = $filters;
        $this->sortable(false);
    }

    /**
     * Get the column names for table.
     *
     * @return string[]
     */
    protected function get_columns() : array {
        return [
            'coursename',
            'modulename',
            'modname',
            'date',
            'time',
            'reason',
            'category',
        ];
    }

    /**
     * Get the headers for table columns.
     *
     * @return string[] $headers Headers for table columns.
     */
    protected function get_headers() : array {
        return [
            get_string('report:coursename', 'local_submissionrestict'),
            get_string('report:modulename', 'local_submissionrestict'),
            get_string('report:modname', 'local_submissionrestict'),
            get_string('report:date', 'local_submissionrestict'),
            get_string('report:time', 'local_submissionrestict'),
            get_string('report:reason', 'local_submissionrestict'),
            get_string('report:category', 'local_submissionrestict'),
        ];
    }


    /**
     * Query the database for report records.
     *
     * @param int $pagesize size of page for paginated table.
     * @param bool $useinitialsbar do you want to use the initials bar?
     */
    public function query_db($pagesize, $useinitialsbar = true) : void {
        global $DB;

        $offset = $pagesize * $this->currpage;
        $limit = $pagesize;

        list($countsql, $countparams) = $this->get_sql_and_params(true);
        list($sql, $params) = $this->get_sql_and_params();

        $total = $DB->count_records_sql($countsql, $countparams);

        if ($this->is_downloading()) {
            $this->rawdata = $DB->get_records_sql($sql, $params);
        } else {
            $this->rawdata = $DB->get_records_sql($sql, $params, $offset, $limit);
        }

        $this->pagesize($pagesize, $total);

        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }
    }

    /**
     * Builds the complete sql .
     *
     * @param bool $count setting this to true, returns an sql to get count only instead of the complete data records.
     * @return array containing sql to use and an array of params.
     */
    protected function get_sql_and_params(bool $count = false): array {
        if ($count) {
            $select = "COUNT(1)";
        } else {
            $select = "ls.cmid, c.id as courseid, c.fullname, ls.modname,  ls.newdate, ls.reason, c.category ";
        }

        list($where, $params) = $this->get_filters_sql_and_params();

        $sql = "SELECT $select 
                  FROM {local_submissionrestict} ls
             LEFT JOIN {course_modules} cm ON ls.cmid = cm.id
             LEFT JOIN {course} c ON cm.course = c.id
                 WHERE $where";

        if (!$count && $sqlsort = $this->get_sql_sort()) {
            $sql .= " ORDER BY " . $sqlsort;
        }

        return [$sql, $params];
    }

    /**
     * Builds the sql and param list needed, based on the user selected filters.
     *
     * @return array Containing sql to use and an array of params.
     */
    protected function get_filters_sql_and_params(): array {
        global $DB;

        $filter = 'c.id IS NOT NULL';
        $params = [];

        if (!empty($this->filters->category)) {
            $coursecat = \core_course_category::get($this->filters->category);

            if ($coursecat->has_children()) {
                $categories = $coursecat->get_all_children_ids();
                $coursecat->get_nested_name();
                $categories[] = $this->filters->category;
                list($insql, $plist) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED);
                $filter .= " AND c.category $insql";
                $params += $plist;

            } else {
                $filter .= ' AND c.category = :category';
                $params['category'] = $this->filters->category;
            }
        }

        return [$filter, $params];
    }

    /**
     * Column formatting method for 'category'.
     *
     * @param \stdClass $row the row data.
     * @return string formatted HTML to display in table.
     */
    public function col_category(\stdClass $row) : string {
        $category = \core_course_category::get($row->category);

        if ($this->is_downloading()) {
            $parents = $category->get_parents();
            if (!empty($parents)) {
                // If not top level category, we would like to display top level parent name,
                // as it will get us required faculty category.
                $coursecategoryname = \core_course_category::get($parents[0])->name;
            } else {
                $coursecategoryname = $category->name;
            }
        } else {
            $coursecategoryname = $category->get_nested_name(true);

        }

        return $coursecategoryname;
    }

    /**
     * Column formatting method for 'coursename'.
     *
     * @param \stdClass $row the row data.
     * @return string formatted HTML to display in table.
     */
    public function col_coursename(\stdClass $row) : string {
        if ($this->is_downloading()) {
            $result = $this->format_text($row->fullname);
        } else {
            $url = new \moodle_url('/course/view.php', ['id' => $row->courseid]);
            $link = \html_writer::link($url, $row->fullname);
            $result = $this->format_text($link, FORMAT_HTML);
        }

        return $result;
    }

    /**
     * Column formatting method for 'modulename'.
     *
     * @param \stdClass $row the row data.
     * @return string formatted HTML to display in table.
     */
    public function col_modulename(\stdClass $row) : string {

        [$course, $cm] = get_course_and_cm_from_cmid($row->cmid, $row->modname, $row->courseid);

        if ($this->is_downloading()) {
            $result = $this->format_text($cm->name);
        } else {
            $link = \html_writer::link($cm->url, $cm->name);
            $result = $this->format_text($link, FORMAT_HTML);
        }

        return $result;
    }

    /**
     * Column formatting method for 'date'.
     *
     * @param \stdClass $row the row data.
     * @return string formatted HTML to display in table.
     */
    public function col_date(\stdClass $row) : string {
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        return $calendartype->timestamp_to_date_string($row->newdate, '%d.%m.%Y', 99, true, true);
    }


    /**
     * Column formatting method for 'time'.
     *
     * @param \stdClass $row the row data.
     * @return string formatted HTML to display in table.
     */
    public function col_time(\stdClass $row) : string {
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        return $calendartype->timestamp_to_date_string($row->newdate, '%H:%M', 99, true, true);
    }

}
