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

namespace mod_supervideo\completion;

use dml_exception;

/**
 * Completion Util class
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_util {

    /**
     * Function get_completion_state
     *
     * @param $course
     * @param $cm
     * @param $userid
     *
     * @return bool
     * @throws dml_exception
     */
    public static function get_completion_state($course, $cm, $userid) {
        global $DB;

        $supervideo = $DB->get_record("supervideo", ["id" => $cm->instance], "*", MUST_EXIST);

        if (empty($supervideo->completionpercent)) {
            return true;
        }

        $sql = "
         SELECT MAX(percent)
           FROM {supervideo_view}
          WHERE cm_id = :cm_id
            AND user_id = :user_id";
        $params = [
            "cm_id" => $cm->id,
            "user_id" => $userid,
        ];
        $userpercent = $DB->get_field_sql($sql, $params);

        return (int) ($userpercent ?? 0) >= (int) $supervideo->completionpercent;
    }
}
