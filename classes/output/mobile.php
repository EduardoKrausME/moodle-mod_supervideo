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
 * Moodle App output handler.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\output;

use context_module;

/** Moodle App integration. */
class mobile {
    /**
     * Render the activity using native App Web Services instead of a tokenised WebView.
     *
     * @param array $args Handler arguments.
     * @return array
     */
    public static function mobile_course_view($args) {
        global $CFG, $DB, $OUTPUT;

        $cmid = (int)$args["cmid"];
        $cm = get_coursemodule_from_id("supervideo", $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
        require_login($course, false, $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability("mod/supervideo:view", $context);

        $data = [
            "cmid" => $cm->id,
            "courseid" => $course->id,
            "supervideoid" => $cm->instance,
        ];
        if (right_to_left()) {
            $data["direction"] = "ltr";
        }

        return [
            "templates" => [[
                "id" => "main",
                "html" => $OUTPUT->render_from_template("mod_supervideo/mobileapp/mobile", $data),
            ]],
            "javascript" => file_get_contents($CFG->dirroot . "/mod/supervideo/js/mobileapp/player.js"),
            "otherdata" => ["cmid" => $cm->id],
            "files" => [],
        ];
    }
}
