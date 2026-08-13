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
 * Render view for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\output;

use context_module;
use Exception;
use mod_supervideo\analytics\supervideo_view;
use mod_supervideo\player\manager as player_manager;
use mod_supervideo\util\config_util;
use stdClass;

/**
 * View class
 */
class view {

    /** @var bool */
    public $hasteacher;

    /** @var bool */
    public $caneditsettings;

    /** @var mixed */
    public $config;

    /** @var bool */
    public $freemode = false;

    /** @var stdClass */
    private $cm;

    /** @var stdClass */
    private $course;

    /** @var stdClass */
    public $supervideo;

    /** @var context_module */
    private $context;

    /** @var object */
    public $supervideoview;

    /** @var string */
    public $errosmessages = "";

    /**
     * Construct
     *
     * @param $cm
     * @param $course
     * @param $supervideo
     * @param $context
     * @throws Exception
     */
    public function __construct($cm, $course, $supervideo, $context) {
        global $CFG, $PAGE;

        $this->cm = $cm;
        $this->course = $course;
        $this->supervideo = $supervideo;
        $this->context = $context;

        $this->hasteacher = has_capability("mod/supervideo:addinstance", $context);
        $this->caneditsettings = has_capability("moodle/course:manageactivities", $context);
        $this->config = config_util::get_config($supervideo);

        $this->supervideoview = supervideo_view::create($cm->id);

        if ($this->config->distractionfreemode) {
            $this->freemode = true;
        } else {
            if ($CFG->branch <= 311) {
                $this->freemode = false;
            }
            if ($PAGE->user_is_editing()) {
                $this->freemode = false;
            }
            if (!$this->supervideo->videourl) {
                $this->freemode = false;
            }
        }

        require_capability("mod/supervideo:view", $this->context);
    }

    /**
     * get_maps
     *
     * @return bool|string
     * @throws Exception
     */
    public function get_maps() {
        global $OUTPUT;
        $text = $OUTPUT->heading(
            get_string("your_map_view", "mod_supervideo") . " <span></span>",
            3,
            "main-view",
            "your-map-view"
        );
        return $OUTPUT->render_from_template("mod_supervideo/map", [
            "style" => $this->config->showmap ? "" : "style='display:none'",
            "data_map" => base64_encode($this->supervideoview->map),
            "text" => $text,
        ]);
    }

    /**
     * get_player
     *
     * @return bool|object|string|void
     * @throws Exception
     */
    public function get_player() {
        $manager = new player_manager(
            $this->cm,
            $this->course,
            $this->supervideo,
            $this->context,
            $this->config,
            $this->supervideoview
        );
        $result = $manager->render();

        if ($result->freemode !== null) {
            $this->freemode = $result->freemode;
        }
        if ($result->showmap !== null) {
            $this->config->showmap = $result->showmap;
        }
        if ($result->mediaerrors) {
            $this->create_errosmessages();
        }

        return $result->html;
    }

    /**
     * Get direction data for templates that wrap the player.
     *
     * @return array
     */
    public function get_direction_data() {
        $direction = right_to_left() ? "ltr" : "";
        if (!$direction) {
            return [];
        }

        return ["direction" => $direction];
    }

    /**
     * create_errosmessages
     *
     * @return void
     * @throws Exception
     */
    public function create_errosmessages() {
        global $OUTPUT;
        $errors = [
            "error_media_err_aborted",
            "error_media_err_network",
            "error_media_err_decode",
            "error_media_err_src_not_supported",
            "error_default",
        ];
        foreach ($errors as $errorid) {
            $this->errosmessages .= $OUTPUT->render_from_template("mod_supervideo/error", [
                "elementId" => $errorid,
                "type" => "danger",
                "message" => get_string($errorid, "mod_supervideo"),
                "extratags" => "style='display:none;'",
            ]);
        }
    }

    /**
     * create_error_message
     *
     * @param $message
     * @return bool|string
     * @throws Exception
     */
    private function create_error_message($message) {
        global $OUTPUT;

        $this->freemode = false;
        $this->config->showmap = false;
        return $OUTPUT->render_from_template("mod_supervideo/error", [
            "elementId" => "message_notfound",
            "type" => "danger",
            "message" => $message,
        ]);
    }
}
