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
 * @package    mod_supervideo
 * @copyright  2023 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT);
$n = optional_param('n', 0, PARAM_INT);
$mobile = optional_param('mobile', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('supervideo', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $supervideo = $DB->get_record('supervideo', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $supervideo = $DB->get_record('supervideo', array('id' => $n), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $supervideo->course), '*', MUST_EXIST);
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

$event = \mod_supervideo\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $supervideo);
$event->trigger();

// Update 'viewed' state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$params = [
    'n' => $n,
    'id' => $id,
    'mobile' => $mobile
];
$PAGE->set_url('/mod/supervideo/view.php', $params);
$PAGE->requires->css('/mod/supervideo/style.css');
$PAGE->set_title("{$course->shortname}: {$supervideo->name}");
$PAGE->set_heading($course->fullname);

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

echo '<div id="supervideo_area_embed">';

$parseurl = \mod_supervideo\util\url::parse($supervideo->videourl);

$supervideoview = \mod_supervideo\analytics\supervideo_view::create($cm->id);

if ($parseurl->videoid) {
    $uniqueid = uniqid();

    $config = get_config('supervideo');

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

        if ($url->extra == "mp3") {
            echo "<audio style='width:100%' id='{$parseurl->engine}-{$uniqueid}' {$controls} {$autoplay} crossorigin playsinline >
                      <source src='{$parseurl->videoid}' type='video/mp3'>
                  </audio>";

            $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_audio', [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                "{$parseurl->engine}-{$uniqueid}",
                $supervideo->autoplay ? true : false,
                $supervideo->showcontrols ? true : false,
            ]);

        } else {
            echo "<video style='width:100%' id='{$parseurl->engine}-{$uniqueid}' {$controls} {$autoplay} crossorigin playsinline >
                      <source src='{$parseurl->videoid}' type='video/mp4'>
                  </video>";

            $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_video', [
                (int)$supervideoview->id,
                $supervideoview->currenttime,
                "{$parseurl->engine}-{$uniqueid}",
                $supervideo->playersize,
                $supervideo->autoplay ? 1 : 0,
                $supervideo->showcontrols ? true : false,
            ]);
        }

    } else if ($parseurl->engine == "resource") {
        $files = get_file_storage()->get_area_files(
            $context->id, 'mod_supervideo', 'content', $supervideo->id, 'sortorder DESC, id ASC', false);
        $file = reset($files);
        if ($file) {
            $path = "/{$context->id}/mod_supervideo/content/{$supervideo->id}{$file->get_filepath()}{$file->get_filename()}";
            $fullurl = moodle_url::make_file_url('/pluginfile.php', $path, false);

            $controls = $supervideo->showcontrols ? "controls" : "";
            $autoplay = $supervideo->autoplay ? "autoplay" : "";

            if ($parseurl->videoid == "mp3") {
                echo "<audio id='{$parseurl->engine}-{$uniqueid}' {$controls} {$autoplay} crossorigin playsinline >
                          <source src='{$fullurl}' type='audio/mp3'>
                      </audio>";

                $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_audio', [
                    (int)$supervideoview->id,
                    $supervideoview->currenttime,
                    "{$parseurl->engine}-{$uniqueid}",
                    $supervideo->autoplay ? true : false,
                    $supervideo->showcontrols ? true : false,
                ]);
            } else if ($parseurl->videoid == "mp4") {
                echo "<video id='{$parseurl->engine}-{$uniqueid}' {$controls} {$autoplay} crossorigin playsinline >
                          <source src='{$fullurl}' type='video/mp4'>
                      </video>";

                $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_video', [
                    (int)$supervideoview->id,
                    $supervideoview->currenttime,
                    "{$parseurl->engine}-{$uniqueid}",
                    $supervideo->playersize,
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
    } else if ($parseurl->engine == "youtube") {
        echo "<script src='https://www.youtube.com/iframe_api'></script>";
        echo "<div id='{$parseurl->engine}-{$uniqueid}'></div>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'youtube', [
            (int)$supervideoview->id,
            $supervideoview->currenttime,
            "{$parseurl->engine}-{$uniqueid}",
            $parseurl->videoid,
            $supervideo->playersize,
            $supervideo->showcontrols ? 1 : 0,
            $supervideo->autoplay ? 1 : 0
        ]);

    } else if ($parseurl->engine == "google-drive") {
        $parametersdrive = implode('&amp;', [
            $supervideo->showcontrols ? 'controls=1' : 'controls=0',
            $supervideo->autoplay ? 'autoplay=1' : 'autoplay=0'
        ]);
        echo "<iframe id='{$parseurl->engine}-{$uniqueid}' width='100%' height='680'
                      frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen
                      allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
                      sandbox='allow-scripts allow-forms allow-same-origin allow-modals'
                      src='https://drive.google.com/file/d/{$parseurl->videoid}/preview?{$parametersdrive}'></iframe>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'drive', [
            (int)$supervideoview->id,
            "{$parseurl->engine}-{$uniqueid}",
            $supervideo->playersize
        ]);

        $config->showmapa = false;

    } else if ($parseurl->engine == "vimeo") {
        $parametersvimeo = implode('&amp;', [
            $supervideo->showcontrols ? 'title=1' : 'title=0',
            $supervideo->autoplay ? 'autoplay=1' : 'autoplay=0',
            $supervideo->showcontrols ? 'controls=1' : 'controls=0',
        ]);
        echo "<script src='https://player.vimeo.com/api/player.js'></script>";
        echo "<iframe id='{$parseurl->engine}-{$uniqueid}' width='100%' height='480'
                      frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen
                      allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
                      sandbox='allow-scripts allow-forms allow-same-origin allow-modals'
                      src='https://player.vimeo.com/video/{$parseurl->videoid}?{$parametersvimeo}'></iframe>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'vimeo', [
            $supervideoview->id,
            $supervideoview->currenttime,
            $parseurl->videoid,
            "{$parseurl->engine}-{$uniqueid}"
        ]);
    }

    $classmapa = $config->showmapa ? "" : "style='display:none'";
    $text = $OUTPUT->heading(get_string('seu_mapa_view', 'mod_supervideo') . ' <span></span>', 3, 'main-view', 'seu-mapa-view');
    echo "<div id='mapa-visualizacao' {$classmapa}>
              <div class='mapa' data-mapa='" . base64_encode($supervideoview->mapa) . "'></div>
              {$text}
              <div class='clique'></div>
          </div>";
} else {
    echo "<div class='alert alert-warning'>
              <i class='fa fa-exclamation-circle'></i>
              <div>" . get_string('idnotfound', 'mod_supervideo') . "</div>
          </div>";
}

echo '</div>';
echo $OUTPUT->footer();
