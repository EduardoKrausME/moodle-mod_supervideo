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

require_once("../../config.php");
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
    error("You must specify a course_module ID or an instance ID");
}

$context = context_module::instance($cm->id);

$mobile = optional_param("mobile", 0, PARAM_INT);
if ($mobile) {
    session_write_close();
    $USER = $user;
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
$PAGE->requires->css("/mod/supervideo/style.css");
$PAGE->set_title("{$course->shortname}: {$supervideo->name}");
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

$event = \mod_supervideo\event\course_module_viewed::create([
    "objectid" => $PAGE->cm->instance,
    "context" => $PAGE->context,
]);
$event->add_record_snapshot("course", $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $supervideo);
$event->trigger();

if ($mobile) {
    $PAGE->set_pagelayout("embedded");
}

$config = get_config("supervideo");

$hasteacher = has_capability("mod/supervideo:addinstance", $context);
$hasteacher = false;
if (!$hasteacher && $config->distractionfreemode) {
    if (isset($USER->editing) && $USER->editing) {
        $PAGE->add_body_class("distraction-free-mode--editing");
    } else {
        $PAGE->add_body_class("distraction-free-mode");
    }
}

echo $OUTPUT->header();

$linkreport = "";
if ($hasteacher) {
    $linkreport = "<a class='supervideo-report-link' href='report.php?id={$cm->id}'>" .
        get_string("report_title", "mod_supervideo") . "</a>";
}
$title = format_string($supervideo->name);
echo $OUTPUT->heading("<span class='supervideoheading-title'>{$title}</span> {$linkreport}", 2, "main", "supervideoheading");

$extraembedtag = "";
if ($config->maxwidth >= 500 && !$config->distractionfreemode) {
    $config->maxwidth = intval($config->maxwidth);
    $extraembedtag .= " style='margin:0 auto;max-width:{$config->maxwidth}px;' ";
}

echo "<div id='supervideo_area_embed' {$extraembedtag}>";

$supervideoview = \mod_supervideo\analytics\supervideo_view::create($cm->id);

if ($supervideo->videourl) {
    $uniqueid = uniqid();

    $elementid = "{$supervideo->origem}-{$uniqueid}";

    if ($config->showcontrols == 2) {
        $supervideo->showcontrols = 0;
    } else if ($config->showcontrols == 3) {
        $supervideo->showcontrols = 1;
    }

    if ($config->autoplay == 2) {
        $supervideo->autoplay = 0;
    } else if ($config->autoplay == 3) {
        $supervideo->autoplay = 1;
    }

    if ($supervideo->origem == "link") {

        $controls = $supervideo->showcontrols ? "controls" : "";
        $autoplay = $supervideo->autoplay ? "autoplay" : "";

        echo "<div id='{$elementid}'></div>";
        if (preg_match("/^https?.*\.(mp3|aac|m4a)/i", $supervideo->videourl, $output)) {
            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "resource_audio", [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                $elementid,
                $supervideo->videourl,
                $supervideo->autoplay ? true : false,
                $supervideo->showcontrols ? true : false,
            ]);
        } else {
            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "resource_video", [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                $elementid,
                $supervideo->videourl,
                $supervideo->autoplay ? 1 : 0,
                $supervideo->showcontrols ? true : false,
            ]);
        }
    }
    if ($supervideo->origem == "ottflix") {
        echo "<div id='{$elementid}'></div>";

        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "ottflix", [
            (int)$supervideoview->id,
            $supervideoview->currenttime,
            $elementid,
            $supervideo->videourl,
        ]);

        if (preg_match("/\/\w+\/\w+\/([A-Z0-9\-\_]{3,255})/", $supervideo->videourl, $path)) {
            $url->videoid = $path[1];
            echo $OUTPUT->render_from_template("mod_supervideo/embed_ottflix", ["identifier" => $path[1]]);
        } else {
            echo $OUTPUT->render_from_template("mod_supervideo/error");
            $config->showmapa = false;
        }
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

            $embedparameters = implode(" ", [
                $supervideo->showcontrols ? "controls" : "",
                $supervideo->autoplay ? "autoplay" : "",
            ]);

            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if ($extension == "mp3" || $extension == "aac" || $extension == "m4a") {
                echo "<div id='{$elementid}'></div>";

                $PAGE->requires->js_call_amd("mod_supervideo/player_create", "resource_audio", [
                    (int)$supervideoview->id,
                    $supervideoview->currenttime,
                    $elementid,
                    $fullurl,
                    $supervideo->autoplay ? true : false,
                    $supervideo->showcontrols ? true : false,
                ]);
            } else {
                echo "<div id='{$elementid}'></div>";

                $PAGE->requires->js_call_amd("mod_supervideo/player_create", "resource_video", [
                    (int)$supervideoview->id,
                    $supervideoview->currenttime,
                    $elementid,
                    $fullurl,
                    $supervideo->autoplay ? true : false,
                    $supervideo->showcontrols ? true : false,
                ]);
            }
        } else {
            $message = "Arquivo não localizado!";
            $notification = new \core\output\notification($message, \core\output\notification::NOTIFY_ERROR);
            $notification->set_show_closebutton(false);
            echo \html_writer::span($PAGE->get_renderer("core")->render($notification));
        }
    }
    if ($supervideo->origem == "youtube") {
        echo "<script src='https://www.youtube.com/iframe_api'></script>";
        echo "<div id='{$elementid}'></div>";

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
                "type" => "warning",
                "message" => get_string("idnotfound", "mod_supervideo"),
            ]);

            $PAGE->requires->js_call_amd("mod_supervideo/player_create", "error_idnotfound");
        }
    }
    if ($supervideo->origem == "drive") {
        $parametersdrive = implode("&amp;", [
            $supervideo->showcontrols ? "controls=1" : "controls=0",
            $supervideo->autoplay ? "autoplay=1" : "autoplay=0",
        ]);
        echo "<iframe id='{$elementid}' width='100%' height='680'
                      frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen
                      allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
                      sandbox='allow-scripts allow-forms allow-same-origin allow-modals'
                      src='https://drive.google.com/file/d/{$supervideo->videourl}/preview?{$parametersdrive}'></iframe>";

        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "drive", [
            (int)$supervideoview->id,
            $elementid,
            $supervideo->playersize,
        ]);

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
            "html_id" => $elementid,
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
        ]);
    }

    $text = $OUTPUT->heading(get_string("seu_mapa_view", "mod_supervideo") . " <span></span>",
        3, "main-view", "seu-mapa-view");
    echo $OUTPUT->render_from_template("mod_supervideo/mapa", [
        "style" => $config->showmapa ? "" : "style='display:none'",
        "data-mapa" => base64_encode($supervideoview->mapa),
        "text" => $text,
    ]);

    if (!(isset($USER->editing) && $USER->editing)) {
        $PAGE->requires->js_call_amd("mod_supervideo/player_create", "secondary_navigation");
    }

} else {
    echo $OUTPUT->render_from_template("mod_supervideo/error", [
        "elementId" => "message_notfound",
        "type" => "warning",
        "message" => get_string("idnotfound", "mod_supervideo"),
    ]);

    $PAGE->requires->js_call_amd("mod_supervideo/player_create", "error_idnotfound");
}

echo "</div>";

echo $OUTPUT->footer();
