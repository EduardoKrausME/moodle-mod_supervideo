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
use core\output\notification;
use Exception;
use html_writer;
use mod_supervideo\analytics\supervideo_view;
use mod_supervideo\ottflix\repository as repositoryOttflix;
use mod_supervideo\pandavideo\repository as repositoryPanda;
use mod_supervideo\util\config_util;
use moodle_url;
use stdClass;

/**
 * View class
 */
class view {

    /** @var bool */
    public $hasteacher;

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
            get_string("seu_mapa_view", "mod_supervideo") . " <span></span>",
            3,
            "main-view",
            "seu-mapa-view"
        );
        return $OUTPUT->render_from_template("mod_supervideo/mapa", [
            "style" => $this->config->showmapa ? "" : "style='display:none'",
            "data-mapa" => base64_encode($this->supervideoview->mapa),
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
        global $PAGE, $USER, $OUTPUT;

        if ($this->supervideo->videourl) {
            $uniqueid = uniqid();
            $elementid = "{$this->supervideo->origem}-{$uniqueid}";

            if ($this->supervideo->origem == "ottflix") {
                if (preg_match("/([A-Z0-9\-\_]{3,255})/", $this->supervideo->videourl, $path)) {
                    $identifier = $path[1];

                    $isia = isset($this->supervideo->ottflix_ia[3]);
                    $isassetsh5p = strpos($this->supervideo->videourl, "Share/assetsh5p") > 2;
                    if ($isia || $isassetsh5p) {
                        $this->freemode = $this->config->distractionfreemode_h5p;
                        if ($isassetsh5p) {
                            $h5p = repositoryOttflix::h5p("h5p:{$identifier}", $this->supervideo->ottflix_ia);
                        } else {
                            $h5p = repositoryOttflix::h5p("video:{$identifier}", $this->supervideo->ottflix_ia);
                        }
                        $h5p = json_decode($h5p);
                        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "ottflix", [
                            (int)$this->supervideoview->id,
                            $this->supervideoview->currenttime,
                            $elementid,
                            $h5p->data->identifiers,
                        ]);
                        return $h5p->data->html;
                    } else {
                        return repositoryOttflix::getplayer($this->cm->id, $identifier, $USER->id);
                    }
                } else {
                    return $this->create_error_message(get_string("idnotfound", "mod_supervideo"));
                }
            }
            if ($this->supervideo->origem == "link") {
                $mustachedata = [
                    "elementid" => $elementid,
                    "videourl" => $this->supervideo->videourl,
                    "autoplay" => $this->supervideo->autoplay ? 1 : 0,
                    "showcontrols" => $this->supervideo->showcontrols ? 1 : 0,
                    "controls" => $this->config->controls,
                    "speed" => $this->config->speed,
                    "hls" => preg_match("/^https?.*\.(m3u8)/i", $this->supervideo->videourl, $output),
                    "has_audio" => preg_match("/^https?.*\.(mp3|aac|m4a)/i", $this->supervideo->videourl, $output),
                ];
                $PAGE->requires->js_call_amd(
                    "mod_supervideo/player_create",
                    $mustachedata["has_audio"] ? "resource_audio" : "resource_video",
                    [
                        (int)$this->supervideoview->id,
                        $this->supervideoview->currenttime,
                        $elementid,
                    ]
                );
                $this->create_errosmessages();
                $this->freemode = false;
                return $OUTPUT->render_from_template("mod_supervideo/embed_div", $mustachedata);
            }
            if ($this->supervideo->origem == "upload") {
                $files = supervideo_get_area_files($this->context->id);
                $file = reset($files);
                if ($file) {
                    $path = implode("/", [
                        "",
                        $this->context->id,
                        "mod_supervideo/content",
                        $file->get_id(),
                        "{$file->get_itemid()}{$file->get_filepath()}{$file->get_filename()}",
                    ]);
                    $fullurl = moodle_url::make_file_url("/pluginfile.php", $path, false)->out();

                    $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                    if ($extension == "mp3" || $extension == "aac" || $extension == "m4a") {
                        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "resource_audio", [
                            (int)$this->supervideoview->id,
                            $this->supervideoview->currenttime,
                            $elementid,
                        ]);
                    } else {
                        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "resource_video", [
                            (int)$this->supervideoview->id,
                            $this->supervideoview->currenttime,
                            $elementid,
                        ]);
                    }
                    $this->create_errosmessages();
                    $mustachedata = [
                        "elementid" => $elementid,
                        "videourl" => $fullurl,
                        "autoplay" => $this->supervideo->autoplay ? "true" : "false",
                        "showcontrols" => $this->supervideo->showcontrols ? 1 : 0,
                        "controls" => $this->config->controls,
                        "speed" => $this->config->speed,
                    ];
                    return $OUTPUT->render_from_template("mod_supervideo/embed_div", $mustachedata);
                } else {
                    $message = get_string("filenotfound", "mod_supervideo");
                    $notification = new notification($message, notification::NOTIFY_ERROR);
                    $notification->set_show_closebutton(false);
                    return html_writer::span($PAGE->get_renderer("core")->render($notification));
                }
            }
            if ($this->supervideo->origem == "youtube") {
                if (!isset($this->supervideo->playersize[3])) {
                    $this->supervideo->playersize = supervideo_youtube_size($this->supervideo, true);
                }

                $pattern = '/youtu(\.be|be\.com)\/(watch\?v=|embed\/|live\/|shorts\/)?([a-z0-9_\-]{11})/i';
                if (preg_match($pattern, $this->supervideo->videourl, $output)) {
                    $PAGE->requires->js_call_amd("mod_supervideo/player_create", "youtube", [
                        (int)$this->supervideoview->id,
                        $this->supervideoview->currenttime,
                        $elementid,
                        $output[3],
                        $this->supervideo->playersize,
                        $this->supervideo->showcontrols ? 1 : 0,
                        $this->supervideo->autoplay ? 1 : 0,
                    ]);

                    $link = "<script src='https://www.youtube.com/iframe_api'></script>";
                    return $link . $OUTPUT->render_from_template("mod_supervideo/embed_div", ["elementid" => $elementid]);
                } else {
                    return $this->create_error_message(get_string("idnotfound", "mod_supervideo"));
                }
            }
            if ($this->supervideo->origem == "drive") {
                if (preg_match('/\/d\/\K[^\/]+(?=\/)/', $this->supervideo->videourl, $output)) {
                    $parametersdrive = implode("&amp;", [
                        $this->supervideo->showcontrols ? "controls=1" : "controls=0",
                        $this->supervideo->autoplay ? "autoplay=1" : "autoplay=0",
                    ]);

                    $this->config->showmapa = false;

                    $PAGE->requires->js_call_amd("mod_supervideo/player_create", "drive", [
                        (int)$this->supervideoview->id,
                        $elementid,
                        $this->supervideo->playersize,
                    ]);
                    return $OUTPUT->render_from_template("mod_supervideo/embed_drive", [
                        "elementid" => $elementid,
                        "driveid" => $output[0],
                        "parametersdrive" => $parametersdrive,
                    ]);
                } else {
                    return $this->create_error_message(get_string("idnotfound", "mod_supervideo"));
                }
            }
            if ($this->supervideo->origem == "vimeo") {
                $parametersvimeo = implode("&amp;", [
                    "pip=1",
                    "title=0",
                    "byline=0",
                    $this->supervideo->showcontrols ? "title=1" : "title=0",
                    $this->supervideo->autoplay ? "autoplay=1" : "autoplay=0",
                    $this->supervideo->showcontrols ? "controls=1" : "controls=0",
                ]);

                if (preg_match("/vimeo.com\/(\d+)(\/(\w+))?/", $this->supervideo->videourl, $output)) {
                    if (isset($output[3])) {
                        $url = "{$output[1]}?h={$output[3]}&pip{$parametersvimeo}";
                    } else {
                        $url = "{$output[1]}?pip{$parametersvimeo}";
                    }
                }

                $PAGE->requires->js_call_amd("mod_supervideo/player_create", "vimeo", [
                    $this->supervideoview->id,
                    $this->supervideoview->currenttime,
                    $this->supervideo->videourl,
                    $elementid,
                ]);
                return $OUTPUT->render_from_template("mod_supervideo/embed_vimeo", [
                    "elementid" => $elementid,
                    "vimeo_id" => $url,
                    "parametersvimeo" => $parametersvimeo,
                ]);
            }
            if ($this->supervideo->origem == "pandavideo") {
                try {
                    $pandavideo = repositoryPanda::oembed($this->supervideo->videourl);
                    $pandavideo->video_player = preg_replace('/.*src="(.*?)".*/', '$1', $pandavideo->html);

                    $PAGE->requires->js_call_amd("mod_supervideo/player_create", "pandavideo", [
                        (int)$this->supervideoview->id,
                        $this->supervideoview->currenttime,
                        $elementid,
                        ["width" => $pandavideo->width, "height" => $pandavideo->height],
                    ]);
                    return $OUTPUT->render_from_template("mod_supervideo/embed_pandavideo", [
                        "elementid" => $elementid,
                        "id" => $pandavideo->id,
                        "video_player" => $pandavideo->video_player,
                    ]);
                } catch (Exception $e) {
                    return $this->create_error_message($e->getMessage());
                }
            }
        } else {
            return $this->create_error_message(get_string("idnotfound", "mod_supervideo"));
        }
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
        $this->config->showmapa = false;
        return $OUTPUT->render_from_template("mod_supervideo/error", [
            "elementId" => "message_notfound",
            "type" => "danger",
            "message" => $message,
        ]);
    }
}
