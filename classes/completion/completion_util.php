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
 * User: Eduardo Kraus
 * Date: 18/07/2023
 * Time: 18:47
 */

namespace mod_supervideo\completion;


class completion_util {

    /**
     * @param \stdClass $course
     * @param \stdClass $cm
     * @param int $userid
     *
     * @return bool
     * @throws \dml_exception
     */
    public static function get_completion_state($course, $cm, $userid) {
        global $CFG, $DB, $USER;

        $supervideo = $DB->get_record('supervideo', ['id' => $cm->instance], '*', MUST_EXIST);
        if ($supervideo->completionpercent) {

            require_once($CFG->libdir . '/gradelib.php');
            $grades = grade_get_grades($course->id, 'mod', 'supervideo', $supervideo->id, $USER->id);

            if (isset($grades->items[0]->grades)) {
                foreach ($grades->items[0]->grades as $grade) {
                    if (intval($supervideo->completionpercent) >= intval($grade->grade)) {
                        return true;
                    }
                }
            }

            return false;
        }

        return true;
    }
}
