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

require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT);
$n = optional_param('n', 0, PARAM_INT);
$mobile = optional_param('mobile', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('supervideo', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $supervideo = $DB->get_record('supervideo', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $supervideo = $DB->get_record('supervideo', ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $supervideo->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('supervideo', $supervideo->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

$secret = optional_param('secret', false, PARAM_TEXT);
if ($secret) {
    $userid = optional_param('user_id', "", PARAM_INT);
    \mod_supervideo\output\mobile::valid_token($userid, $secret);
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/supervideo:view', $context);

$event = \mod_supervideo\event\course_module_viewed::create([
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
]);
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $supervideo);
$event->trigger();

// Update 'viewed' state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$params = [
    'n' => $n,
    'id' => $id,
    'mobile' => $mobile,
];
$PAGE->set_url('/mod/supervideo/view.php', $params);
$PAGE->requires->css('/mod/supervideo/style.css');
$PAGE->set_title("{$course->shortname}: {$supervideo->name}");
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

if ($mobile) {
    $PAGE->set_pagelayout('embedded');
}

echo $OUTPUT->header();

$linkreport = "";
if (has_capability('moodle/course:manageactivities', $context)) {
    $linkreport = "<a class='supervideo-report-link' href='report.php?id={$cm->id}'>" .
        get_string('report_title', 'mod_supervideo') . "</a>";
}
$title = format_string($supervideo->name);
echo $OUTPUT->heading("<span class='supervideoheading-title'>{$title}</span> {$linkreport}", 2, 'main', 'supervideoheading');


$config = get_config('supervideo');
$style = "";
if (@$config->maxwidth >= 500) {
    $config->maxwidth = intval($config->maxwidth);
    $style = "style='margin:0 auto;max-width:{$config->maxwidth}px;'";
}
echo "<div id='supervideo_area_embed' {$style}>";

$parseurl = \mod_supervideo\util\url::parse($supervideo->videourl);

$supervideoview = \mod_supervideo\analytics\supervideo_view::create($cm->id);

if ($parseurl->videoid) {
    $uniqueid = uniqid();

    $elementid = "{$parseurl->engine}-{$uniqueid}";

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

    if ($parseurl->engine == "link") {

        $controls = $supervideo->showcontrols ? "controls" : "";
        $autoplay = $supervideo->autoplay ? "autoplay" : "";

        echo "<div id='{$elementid}'></div>";
        if ($parseurl->extra == "mp3") {
            $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_audio', [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                $elementid,
                $parseurl->videoid,
                $supervideo->autoplay ? true : false,
                $supervideo->showcontrols ? true : false,
            ]);
        } else {
            $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_video', [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                $elementid,
                $parseurl->videoid,
                $supervideo->autoplay ? 1 : 0,
                $supervideo->showcontrols ? true : false,
            ]);
        }
    }
    if ($parseurl->engine == "ottflix") {
        echo "<div id='{$elementid}'></div>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'ottflix', [
            (int)$supervideoview->id,
            $supervideoview->currenttime,
            $elementid,
            $parseurl->videoid,
        ]);

        echo $OUTPUT->render_from_template('mod_supervideo/embed_ottflix', ['identifier' => $parseurl->videoid]);
    }
    if ($parseurl->engine == "resource") {
        $files = get_file_storage()->get_area_files(
            $context->id, 'mod_supervideo', 'content', $supervideo->id, 'sortorder DESC, id ASC', false);
        $file = reset($files);
        if ($file) {
            $path = "/{$context->id}/mod_supervideo/content/{$supervideo->id}{$file->get_filepath()}{$file->get_filename()}";
            $fullurl = moodle_url::make_file_url('/pluginfile.php', $path, false)->out();

            $embedparameters = implode(" ", [
                $supervideo->showcontrols ? "controls" : "",
                $supervideo->autoplay ? "autoplay" : "",
            ]);

            if ($parseurl->videoid == "mp3") {
                echo "<div id='{$elementid}'></div>";

                $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_audio', [
                    (int)$supervideoview->id,
                    $supervideoview->currenttime,
                    $elementid,
                    $fullurl,
                    $supervideo->autoplay ? true : false,
                    $supervideo->showcontrols ? true : false,
                ]);
            } else {
                echo "<div id='{$elementid}'></div>";

                $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_video', [
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
            echo \html_writer::span($PAGE->get_renderer('core')->render($notification));
        }
    }
    if ($parseurl->engine == "youtube") {
        echo "<script src='https://www.youtube.com/iframe_api'></script>";
        echo "<div id='{$elementid}'></div>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'youtube', [
            (int)$supervideoview->id,
            $supervideoview->currenttime,
            $elementid,
            $parseurl->videoid,
            $supervideo->playersize,
            $supervideo->showcontrols ? 1 : 0,
            $supervideo->autoplay ? 1 : 0,
        ]);
    }
    if ($parseurl->engine == "google-drive") {
        $parametersdrive = implode('&amp;', [
            $supervideo->showcontrols ? 'controls=1' : 'controls=0',
            $supervideo->autoplay ? 'autoplay=1' : 'autoplay=0',
        ]);
        echo "<iframe id='{$elementid}' width='100%' height='680'
                      frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen
                      allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
                      sandbox='allow-scripts allow-forms allow-same-origin allow-modals'
                      src='https://drive.google.com/file/d/{$parseurl->videoid}/preview?{$parametersdrive}'></iframe>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'drive', [
            (int)$supervideoview->id,
            $elementid,
            $supervideo->playersize,
        ]);

        $config->showmapa = false;
    }
    if ($parseurl->engine == "vimeo") {
        $parametersvimeo = implode('&amp;', [
            'pip=1',
            'title=0',
            'byline=0',
            $supervideo->showcontrols ? 'title=1' : 'title=0',
            $supervideo->autoplay ? 'autoplay=1' : 'autoplay=0',
            $supervideo->showcontrols ? 'controls=1' : 'controls=0',
        ]);

        if (strpos($parseurl->videoid, "?")) {
            $url = "{$parseurl->videoid}&pip{$parametersvimeo}";
        } else {
            $url = "{$parseurl->videoid}?pip{$parametersvimeo}";
        }

        echo $OUTPUT->render_from_template('mod_supervideo/embed_vimeo', [
            'html_id' => $elementid,
            'vimeo_url' => $url,
            'parametersvimeo' => $parametersvimeo,
        ]);

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'vimeo', [
            $supervideoview->id,
            $supervideoview->currenttime,
            $parseurl->videoid,
            $elementid,
        ]);
    }

    $text = $OUTPUT->heading(get_string('seu_mapa_view', 'mod_supervideo') . ' <span></span>', 3, 'main-view', 'seu-mapa-view');
    echo $OUTPUT->render_from_template('mod_supervideo/mapa', [
        'style' => $config->showmapa ? "" : "style='display:none'",
        'data-mapa' => base64_encode($supervideoview->mapa),
        'text' => $text,
    ]);

} else {
    echo $OUTPUT->render_from_template('mod_supervideo/error');
    $config->showmapa = false;
}

echo '</div>';

echo $OUTPUT->footer();
