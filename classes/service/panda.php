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

namespace mod_supervideo\service;

use external_api;
use external_format_value;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use mod_supervideo\panda\repository;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * Service Panda for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2025 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panda extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function list_videos_parameters() {
        return new external_function_parameters([
                'title' => new external_value(PARAM_TEXT, 'Find title', VALUE_OPTIONAL),
            ]
        );
    }

    /**
     * Get panda videos.
     *
     * @param int $title the supervideo instance id
     *
     * @return array of warnings and status result
     *
     * @throws \invalid_parameter_exception
     */
    public static function list_videos($title="") {
        global $CFG;
        require_once($CFG->dirroot . "/mod/supervideo/lib.php");

        $params = self::validate_parameters(self::list_videos_parameters(), ['title' => $title]);

        try {
            $videos = repository::get_videos(0, 100, $params["title"]);
        } catch (\Exception $e) {
            return[
                "status" => false,
                "error" => $e->getMessage(),
            ];
        }

        $return = [
            "status" => true,
            "error" => "",
            "videos" => [],
        ];
        foreach ($videos->videos as $video) {
            $return["videos"][] = [
                "video_id" => $video->id,
                "title" => $video->title,
                "status" => $video->status,
                "video_player" => $video->video_player,
                "video_hls" => $video->video_hls,
                "width" => $video->width,
                "height" => $video->height,
                "thumbnail" => $video->thumbnail,
            ];
        }

        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function list_videos_returns() {
        return new external_single_structure(
            [
                "status" => new external_value(PARAM_BOOL, "status: true if success"),
                "error"  => new external_value(PARAM_TEXT, "text error", VALUE_OPTIONAL),
                "videos" => new external_multiple_structure(
                    new external_single_structure(
                        [
                            "video_id" => new external_value(PARAM_TEXT, "video_id"),
                            "title" => new external_value(PARAM_TEXT, "title"),
                            "status" => new external_value(PARAM_TEXT, "status"),
                            "video_player" => new external_value(PARAM_TEXT, "video_player"),
                            "video_hls" => new external_value(PARAM_TEXT, "video_player"),
                            "width" => new external_value(PARAM_INT, "width"),
                            "height" => new external_value(PARAM_INT, "height"),
                            "thumbnail" => new external_value(PARAM_TEXT, "thumbnail"),
                        ], 'List Videos', VALUE_OPTIONAL
                    )
                ),
            ]
        );
    }
}
