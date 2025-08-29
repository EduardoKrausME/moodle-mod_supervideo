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

namespace mod_supervideo\analytics;

use mod_supervideo\grade\grades_util;

/**
 * Supervideo View implementation for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supervideo_view {
    /**
     * Create function.
     *
     * @param $cmid
     *
     * @return object
     *
     * @throws \dml_exception
     */
    public static function create($cmid) {
        global $USER, $DB;

        $sql = "SELECT * FROM {supervideo_view} WHERE cm_id = :cm_id AND user_id = :user_id ORDER BY id DESC LIMIT 1";
        $supervideoview = $DB->get_record_sql($sql, ["cm_id" => $cmid, "user_id" => $USER->id]);

        if ($supervideoview) {
            if ($supervideoview->currenttime > ($supervideoview->duration - 3)) {
                return self::internal_create($cmid);
            }
            if ($supervideoview->percent < 90) {
                return $supervideoview;
            }
        }

        return self::internal_create($cmid);
    }

    /**
     * Function internal_create
     *
     * @param $cmid
     *
     * @return object
     */
    private static function internal_create($cmid) {
        global $USER, $DB;

        $supervideoview = (object)[
            "cm_id" => $cmid,
            "user_id" => $USER->id,
            "currenttime" => 0,
            "duration" => 0,
            "percent" => 0,
            "mapa" => "{}",
            "timecreated" => time(),
            "timemodified" => time(),
        ];

        try {
            $supervideoview->id = $DB->insert_record("supervideo_view", $supervideoview);
        } catch (\dml_exception $e) {
            return (object)['id' => 0];
        }

        return $supervideoview;
    }

    /**
     * Function update
     *
     * @param $viewid
     * @param $currenttime
     * @param $duration
     * @param $percent
     * @param $mapa
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function update($viewid, $currenttime, $duration, $percent, $mapa) {
        global $DB, $USER, $CFG;

        $supervideoview = $DB->get_record('supervideo_view', ['id' => $viewid, "user_id" => $USER->id]);

        if ($supervideoview) {
            $supervideoview->currenttime = $currenttime;
            $supervideoview->duration = $duration;
            $supervideoview->percent = $percent;
            $supervideoview->mapa = $mapa;
            $supervideoview->timemodified = time();

            $status = $DB->update_record("supervideo_view", $supervideoview);

            require_once("{$CFG->dirroot}/mod/supervideo/classes/grade/grades_util.php");
            grades_util::update($supervideoview->cm_id, $percent);

            return $status;
        }
        return false;
    }
}
