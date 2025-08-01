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
 * view file
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use mod_supervideo\analytics\supervideo_view;
use mod_supervideo\event\course_module_viewed;
use mod_supervideo\panda\repository;
use mod_supervideo\util\config_util;

require_once("../../config.php");
global $CFG, $PAGE, $OUTPUT, $DB, $USER;
require_once($CFG->libdir . "/completionlib.php");

$id = optional_param("id", 0, PARAM_INT);
$n = optional_param("n", 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id("supervideo", $id, 0, false, MUST_EXIST);
    $course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
    $supervideo = $DB->get_record("supervideo", ["id" => $cm->instance], "*", MUST_EXIST);
} else if ($n) {
    $supervideo = $DB->get_record("supervideo", ["id" => $n], "*", MUST_EXIST);
    $course = $DB->get_record("course", ["id" => $supervideo->course], "*", MUST_EXIST);
    $cm = get_coursemodule_from_instance("supervideo", $supervideo->id, $course->id, false, MUST_EXIST);
} else {
    throw new Exception("You must specify a course_module ID or an instance ID");
}

$context = context_module::instance($cm->id);

$mobile = optional_param("mobile", 0, PARAM_INT);
if ($mobile) {
    session_write_close();
    if ($user) {
        $USER = $user;
    }
    $PAGE->set_cm($cm, $course);
    $PAGE->set_course($course);
} else {
    require_course_login($course, true, $cm);
    require_capability("mod/supervideo:view", $context);
}

// Update "viewed" state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$params = [
    "n" => $n,
    "id" => $id,
    "mobile" => $mobile,
];
$PAGE->set_url("/mod/supervideo/view.php", $params);
$PAGE->set_title($supervideo->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

$event = course_module_viewed::create([
    "objectid" => $PAGE->cm->instance,
    "context" => $PAGE->context,
]);
$event->add_record_snapshot("course", $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $supervideo);
$event->trigger();

$hasteacher = has_capability("mod/supervideo:addinstance", $context);
$config = config_util::get_config($supervideo);

if ($mobile) {
    $PAGE->set_pagelayout("embedded");
    $config->distractionfreemode = false;
    $config->maxwidth = false;
}

if ($config->distractionfreemode) {
    if (isset($USER->editing) && $USER->editing) {
        $PAGE->add_body_class("distraction-free-mode--editing");
    } else {
        $PAGE->add_body_class("distraction-free-mode");
    }
}

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin("ui");
$PAGE->requires->jquery_plugin("ui-css");

echo $OUTPUT->header();

if ($CFG->branch <= 311) {
    $linkreport = "";
    if ($hasteacher) {
        $linkreport = "<a class='supervideo-report-link' href='report.php?id={$cm->id}'>" .
            get_string("report_title", "mod_supervideo") . "</a>";
    }
    $title = format_string($supervideo->name);
    echo $OUTPUT->heading("<span class='supervideoheading-title'>{$title}</span> {$linkreport}",
        2, "main", "supervideoheading");
}

$extraembedtag = $config->maxwidth ? "margin:0 auto;max-width:{$config->maxwidth}px;" : "";
echo "<div id='supervideo_area_embed' style='{$extraembedtag}'>";

$supervideoview = supervideo_view::create($cm->id);

if ($supervideo->videourl) {
    $showerrors = false;
    $uniqueid = uniqid();
    $elementid = "{$supervideo->origem}-{$uniqueid}";

    if ($supervideo->origem == "ottflix") {
        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "ottflix", [
            (int)$supervideoview->id,
            $supervideoview->currenttime,
            $elementid,
            $supervideo->videourl,
        ]);

        if (preg_match("/([A-Z0-9\-\_]{3,255})/", $supervideo->videourl, $path)) {
            $identifier = $path[1];

            $ai = \mod_supervideo\ottflix\repository::ai($identifier, $supervideo->ottflix_ia);
            $tabs = [];
            $contents = "";

            // Criação das abas.
            foreach ($ai->data->itens as $item) {
                switch ($item->id) {
                    case 'admin':
                        if ($hasteacher) {
                            $tabs[] = "<li><a href='#tab-{$item->id}'>{$item->title}</a></li>";
                            $contents .= "
                            <div id='tab-{$item->id}'>
                                <a href='{$item->link_admin}' target='_blank'>{$item->title}</a>
                            </div>";
                        }
                        break;
                    case 'book':
                    case 'mindmap':
                        $tabs[] = "<li><a href='#tab-{$item->id}'>{$item->title}</a></li>";
                        $allow = implode("; ", [
                            "accelerometer",
                            "autoplay",
                            "clipboard-write",
                            "encrypted-media",
                            "gyroscope",
                            "picture-in-picture",
                            "web-share",
                        ]);
                        $contents .= "
                            <div id='tab-{$item->id}'>
                                <iframe data-src='{$item->html_iframe}' width='100%' height='600px'
                                        style='border:none;'
                                        sandbox='allow-scripts allow-popups allow-forms allow-same-origin allow-modals'
                                        allow='{$allow}'
                                ></iframe>
                            </div>";
                        break;
                    case 'glossary':
                    case 'quiz':
                        $tabs[] = "<li><a href='#tab-{$item->id}'>{$item->title}</a></li>";
                        $contents .= "<div id='tab-{$item->id}'>{$item->html}</div>";
                        break;
                    case 'suggestion':
                        if ($hasteacher) {
                            // Adiciona a aba.
                            $tabs[] = "<li><a href='#tab-{$item->id}'>{$item->title}</a></li>";
                            $contents .= "<div id='tab-{$item->id}'>{$item->html}</div>";
                        }
                        break;
                    case 'caption':
                        if ($hasteacher) {
                            // Adiciona a aba.
                            $tabs[] = "<li><a href='#tab-{$item->id}'>{$item->title}</a></li>";
                            $contents .= "
                                <div id='tab-{$item->id}'>
                                    <pre>{$item->html}</pre>
                                </div>";
                        }
                        break;
                }
            }

            if (count($tabs)) {

                $playerhtml = \mod_supervideo\ottflix\repository::getplayer($id, $identifier, $USER->id);

                $contents = "<div id='tab-player'>{$playerhtml}</div>{$contents}";

                array_unshift($tabs, "<li><a href='#tab-player'>Vídeo</a></li>");

                $tabshtml = implode("\n", $tabs);
                echo "
                    <link href='{$ai->data->css}' rel='stylesheet'>
                    <div id='ottflix-tabs' style='display:none'><ul id='ottflix-tabs-ul'>{$tabshtml}</ul>{$contents}</div>";
            } else {
                echo \mod_supervideo\ottflix\repository::getplayer($id, $identifier, $USER->id);
            }

            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "ottflix", [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                $elementid,
                $identifier,
            ]);
        } else {
            echo $OUTPUT->render_from_template("mod_supervideo/error", [
                "elementId" => "message_notfound",
                "type" => "danger",
                "message" => get_string("idnotfound", "mod_supervideo"),
            ]);
            $config->showmapa = false;
        }
    }
    if ($supervideo->origem == "link") {
        $mustachedata = [
            "elementid" => $elementid,
            "videourl" => $supervideo->videourl,
            "autoplay" => $supervideo->autoplay ? 1 : 0,
            "showcontrols" => $supervideo->showcontrols ? 1 : 0,
            "controls" => $config->controls,
            "speed" => $config->speed,
            "hls" => preg_match("/^https?.*\.(m3u8)/i", $supervideo->videourl, $output),
            "has_audio" => preg_match("/^https?.*\.(mp3|aac|m4a)/i", $supervideo->videourl, $output),
        ];
        echo $OUTPUT->render_from_template("mod_supervideo/embed_div", $mustachedata);
        $PAGE->requires->js_call_amd("mod_supervideo/player_create",
            $mustachedata["has_audio"] ? "resource_audio" : "resource_video", [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                $elementid,
            ]);
        $showerrors = true;
    }
    if ($supervideo->origem == "upload") {
        $files = supervideo_get_area_files($context->id);
        $file = reset($files);
        if ($file) {
            $path = implode("/", [
                "",
                $context->id,
                "mod_supervideo/content",
                $file->get_id(),
                "{$file->get_itemid()}{$file->get_filepath()}{$file->get_filename()}",
            ]);
            $fullurl = moodle_url::make_file_url("/pluginfile.php", $path, false)->out();

            $mustachedata = [
                "elementid" => $elementid,
                "videourl" => $fullurl,
                "autoplay" => $supervideo->autoplay ? "true" : "false",
                "showcontrols" => $supervideo->showcontrols ? 1 : 0,
                "controls" => $config->controls,
                "speed" => $config->speed,
            ];
            echo $OUTPUT->render_from_template("mod_supervideo/embed_div", $mustachedata);

            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if ($extension == "mp3" || $extension == "aac" || $extension == "m4a") {
                $PAGE->requires->js_call_amd("mod_supervideo/player_create", "resource_audio", [
                    (int)$supervideoview->id,
                    $supervideoview->currenttime,
                    $elementid,
                ]);
            } else {
                $PAGE->requires->js_call_amd("mod_supervideo/player_create", "resource_video", [
                    (int)$supervideoview->id,
                    $supervideoview->currenttime,
                    $elementid,
                ]);
            }
            $showerrors = true;
        } else {
            $message = get_string("filenotfound", "mod_supervideo");
            $notification = new notification($message, notification::NOTIFY_ERROR);
            $notification->set_show_closebutton(false);
            echo \html_writer::span($PAGE->get_renderer("core")->render($notification));
        }
    }
    if ($supervideo->origem == "youtube") {
        echo "<script src='https://www.youtube.com/iframe_api'></script>";
        echo $OUTPUT->render_from_template("mod_supervideo/embed_div", ["elementid" => $elementid]);

        if (!isset($supervideo->playersize[3])) {
            $supervideo->playersize = supervideo_youtube_size($supervideo, true);
        }

        if (preg_match('/youtu(\.be|be\.com)\/(watch\?v=|embed\/|live\/|shorts\/)?([a-z0-9_\-]{11})/i',
            $supervideo->videourl, $output)) {
            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "youtube", [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                $elementid,
                $output[3],
                $supervideo->playersize,
                $supervideo->showcontrols ? 1 : 0,
                $supervideo->autoplay ? 1 : 0,
            ]);
        } else {
            echo $OUTPUT->render_from_template("mod_supervideo/error", [
                "elementId" => "message_notfound",
                "type" => "danger",
                "message" => get_string("idnotfound", "mod_supervideo"),
            ]);
            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "error_idnotfound");
            $config->showmapa = false;
        }
    }
    if ($supervideo->origem == "drive") {
        if (preg_match('/\/d\/\K[^\/]+(?=\/)/', $supervideo->videourl, $output)) {
            $parametersdrive = implode("&amp;", [
                $supervideo->showcontrols ? "controls=1" : "controls=0",
                $supervideo->autoplay ? "autoplay=1" : "autoplay=0",
            ]);

            echo $OUTPUT->render_from_template("mod_supervideo/embed_drive", [
                "elementid" => $elementid,
                "driveid" => $output[0],
                "parametersdrive" => $parametersdrive,
            ]);
            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "drive", [
                (int)$supervideoview->id,
                $elementid,
                $supervideo->playersize,
            ]);
        } else {
            echo $OUTPUT->render_from_template("mod_supervideo/error", [
                "elementId" => "message_notfound",
                "type" => "danger",
                "message" => get_string("idnotfound", "mod_supervideo"),
            ]);
        }
        $config->showmapa = false;
    }
    if ($supervideo->origem == "vimeo") {
        $parametersvimeo = implode("&amp;", [
            "pip=1",
            "title=0",
            "byline=0",
            $supervideo->showcontrols ? "title=1" : "title=0",
            $supervideo->autoplay ? "autoplay=1" : "autoplay=0",
            $supervideo->showcontrols ? "controls=1" : "controls=0",
        ]);

        if (preg_match("/vimeo.com\/(\d+)(\/(\w+))?/", $supervideo->videourl, $output)) {
            if (isset($output[3])) {
                $url = "{$output[1]}?h={$output[3]}&pip{$parametersvimeo}";
            } else {
                $url = "{$output[1]}?pip{$parametersvimeo}";
            }
        }

        echo $OUTPUT->render_from_template("mod_supervideo/embed_vimeo", [
            "elementid" => $elementid,
            "vimeo_id" => $url,
            "parametersvimeo" => $parametersvimeo,
        ]);

        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "vimeo", [
            $supervideoview->id,
            $supervideoview->currenttime,
            $supervideo->videourl,
            $elementid,
        ]);
    }
    if ($supervideo->origem == "panda") {

        try {
            if (isset($config->panda_token[20])) {
                $pandavideo = repository::get_video_properties($supervideo->videourl);
            } else {
                $pandavideo = repository::oembed($supervideo->videourl);
                $pandavideo->video_player = preg_replace('/.*src="(.*?)".*/', '$1', $pandavideo->html);
            }

            echo $OUTPUT->render_from_template("mod_supervideo/embed_panda", [
                "elementid" => $elementid,
                "id" => $pandavideo->id,
                "video_player" => $pandavideo->video_player,
            ]);
            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "panda", [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                $elementid,
                ["width" => $pandavideo->width, "height" => $pandavideo->height],
            ]);

        } catch (Exception $e) {
            echo $OUTPUT->render_from_template("mod_supervideo/error", [
                "elementId" => "panda-error",
                "type" => "danger",
                "message" => $e->getMessage(),
            ]);
        }
    }

    if ($showerrors) {
        $errors = [
            "error_media_err_aborted",
            "error_media_err_network",
            "error_media_err_decode",
            "error_media_err_src_not_supported",
            "error_default",
        ];
        foreach ($errors as $error) {
            echo $OUTPUT->render_from_template("mod_supervideo/error", [
                "elementId" => $error,
                "type" => "danger",
                "message" => get_string($error, "mod_supervideo"),
                "extratags" => "style='display:none;'",
            ]);
        }
    }

    if (!(isset($USER->editing) && $USER->editing)) {
        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "secondary_navigation", [$course->id]);
    }

} else {
    echo $OUTPUT->render_from_template("mod_supervideo/error", [
        "elementId" => "message_notfound",
        "type" => "danger",
        "message" => get_string("idnotfound", "mod_supervideo"),
    ]);
    $PAGE->requires->js_call_amd("mod_supervideo/player_create", "error_idnotfound");
    $config->showmapa = false;
}

echo "</div>";

$text = $OUTPUT->heading(get_string("seu_mapa_view", "mod_supervideo") . " <span></span>",
    3, "main-view", "seu-mapa-view");
echo $OUTPUT->render_from_template("mod_supervideo/mapa", [
    "style" => $config->showmapa ? "" : "style='display:none'",
    "data-mapa" => base64_encode($supervideoview->mapa),
    "text" => $text,
]);

echo $OUTPUT->footer();
