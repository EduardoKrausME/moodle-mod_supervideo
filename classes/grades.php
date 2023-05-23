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
 * Grades implementation for mod_supervideo.
 */

namespace mod_supervideo;

class grades {

    private static $supervideo = null;

    /**
     * @param $cmid
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function update($cmid, $percent) {
        global $DB, $CFG;

        require_once("{$CFG->libdir}/completionlib.php");

        $cm = get_coursemodule_from_id('supervideo', $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        self::$supervideo = $DB->get_record('supervideo', ['id' => $cm->instance], '*', MUST_EXIST);

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            if ($percent >= self::$supervideo->complet_percent) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }
    }

    public static function supervideo_get_completion_state($cm) {
        global $DB;

        if (!self::$supervideo) {
            self::$supervideo = $DB->get_record('supervideo', ['id' => $cm->instance], '*', MUST_EXIST);
        }

        if (self::$supervideo->complet_percent) {
            if (self::$supervideo->complet_percent < self::$supervideo->complet_percent) {
                return false;
            }
        }

        return true;
    }
}
