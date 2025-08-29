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
