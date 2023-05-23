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

$PAGE->set_url('/mod/supervideo/view.php', array('id' => $cm->id));
$PAGE->requires->css('/mod/supervideo/style.css');
$PAGE->set_title("{$course->shortname}: {$supervideo->name}");
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

$extra = "";
if (has_capability('moodle/course:manageactivities', $context)) {
    $extra = "<a class='supervideo-report-link' href='report.php?id={$cm->id}'>" . get_string('report', 'mod_supervideo') . "</a>";
}

echo $OUTPUT->heading(format_string($supervideo->name) . " " . $extra, 2, 'main', 'supervideoheading');

echo '<div id="supervideo_area_embed">';

$parseurl = \mod_supervideo\util\url::parse($supervideo->videourl);

$supervideoview = \mod_supervideo\analytics\supervideo_view::create($cm->id);


if ($parseurl->videoid) {
    $uniqueid = uniqid();


    if ($parseurl->engine == "link") {

        $controls = $supervideo->showcontrols ? "controls" : "";
        $autoplay = $supervideo->autoplay ? "autoplay" : "";

        echo "<script src='https://cdn.polyfill.io/v2/polyfill.min.js?features=es6,Array.prototype.includes,CustomEvent,Object.entries,Object.values,URL'></script>";
        echo "<script src='https://unpkg.com/plyr@3'></script>";
        echo "<link rel='stylesheet' href='https://unpkg.com/plyr@3/dist/plyr.css'>";

        echo "<video style='width:100%' id='{$parseurl->engine}-{$uniqueid}' {$controls} {$autoplay} crossorigin playsinline >
                      <source src='{$parseurl->videoid}' type='video/mp4'>
                  </video>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_video', [
            (int)$supervideoview->id,
            $supervideoview->currenttime,
            "{$parseurl->engine}-{$uniqueid}",
            $supervideo->videosize,
            $supervideo->autoplay ? 1 : 0
        ]);

    } else if ($parseurl->engine == "resource") {
        $files = get_file_storage()->get_area_files($context->id, 'mod_supervideo', 'content', $supervideo->id, 'sortorder DESC, id ASC', false);
        $file = reset($files);
        if ($file) {
            $path = "/{$context->id}/mod_supervideo/content/{$supervideo->id}{$file->get_filepath()}{$file->get_filename()}";
            $fullurl = moodle_url::make_file_url('/pluginfile.php', $path, false);

            $controls = $supervideo->showcontrols ? "controls" : "";
            $autoplay = $supervideo->autoplay ? "autoplay" : "";

            echo "<script src='https://cdn.polyfill.io/v2/polyfill.min.js?features=es6,Array.prototype.includes,CustomEvent,Object.entries,Object.values,URL'></script>";
            echo "<script src='https://unpkg.com/plyr@3'></script>";
            echo "<link rel='stylesheet' href='https://unpkg.com/plyr@3/dist/plyr.css'>";

            if ($parseurl->videoid == "mp3") {
                echo "<audio id='{$parseurl->engine}-{$uniqueid}' {$controls} {$autoplay} crossorigin playsinline >
                      <source src='{$fullurl}' type='audio/mp3'>
                  </audio>";

                $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_audio', [
                    (int)$supervideoview->id,
                    $supervideoview->currenttime,
                    "{$parseurl->engine}-{$uniqueid}",
                    $supervideo->autoplay ? 1 : 0
                ]);
            } else if ($parseurl->videoid == "mp4") {
                echo "<video id='{$parseurl->engine}-{$uniqueid}' {$controls} {$autoplay} crossorigin playsinline >
                      <source src='{$fullurl}' type='video/mp4'>
                  </video>";

                $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'resource_video', [
                    (int)$supervideoview->id,
                    $supervideoview->currenttime,
                    "{$parseurl->engine}-{$uniqueid}",
                    $supervideo->videosize,
                    $supervideo->autoplay ? 1 : 0
                ]);
            }
        }
    } else if ($parseurl->engine == "youtube") {
        echo "<script src='https://www.youtube.com/iframe_api'></script>
              <div id='{$parseurl->engine}-{$uniqueid}'></div>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'youtube', [
            (int)$supervideoview->id,
            $supervideoview->currenttime,
            "{$parseurl->engine}-{$uniqueid}",
            $parseurl->videoid,
            $supervideo->videosize,
            $supervideo->showrel ? 1 : 0,
            $supervideo->showcontrols ? 1 : 0,
            $supervideo->showshowinfo ? 1 : 0,
            $supervideo->autoplay ? 1 : 0
        ]);

    } else if ($parseurl->engine == "google-drive") {
        $urlparameters = array(
            $supervideo->showrel ? 'rel=1' : 'rel=0',
            $supervideo->showcontrols ? 'controls=1' : 'controls=0',
            $supervideo->showshowinfo ? 'showinfo=1' : 'showinfo=0',
            $supervideo->autoplay ? 'autoplay=1' : 'autoplay=0',
        );

        $parameters = implode('&amp;', $urlparameters);

        echo "<iframe id='{$parseurl->engine}-{$uniqueid}' width='100%' height='680'
                      frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen
                      src='https://drive.google.com/file/d/{$parseurl->videoid}/preview?{$parameters}'></iframe>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'drive', [
            (int)$supervideoview->id,
            "{$parseurl->engine}-{$uniqueid}",
            $supervideo->videosize
        ]);

    } else if ($parseurl->engine == "vimeo") {
        $urlparametersvimeo = [
            $supervideo->showcontrols ? 'title=true' : 'title=false',
            $supervideo->autoplay ? 'autoplay=true' : 'autoplay=false',
            $supervideo->showcontrols ? 'controls=true' : 'controls=false',
        ];

        $parametersvimeo = implode('&amp;', $urlparametersvimeo);

        echo "<script src='https://player.vimeo.com/api/player.js'></script>
              <iframe id='{$parseurl->engine}-{$uniqueid}' width='640' height='480'
                      frameborder='0' webkitallowfullscreen mozallowfullscreen allowfullscreen
                      src='https://player.vimeo.com/video/{$parseurl->videoid}?{$parametersvimeo}'></iframe>";

        $PAGE->requires->js_call_amd('mod_supervideo/player_create', 'vimeo', [
            $supervideoview->id,
            $supervideoview->currenttime,
            $parseurl->videoid,
            "{$parseurl->engine}-{$uniqueid}"
        ]);
    }
} else {
    echo "<div class='alert alert-warning'>
              <i class='fa fa-exclamation-circle'></i>
              <div>" . get_string('idnotfound', 'mod_supervideo') . "</div>
          </div>";
}


$config = get_config('supervideo');
$extra = $config->showmapa ? "" : "style='display:none;opacity:0;height:0;'";
echo $OUTPUT->heading(get_string('seu_view', 'mod_supervideo') . ' <span></span>', 3, 'main-view', 'sua-view');
echo "<div id='mapa-visualizacao' data-mapa='" . base64_encode($supervideoview->mapa) . "' {$extra}></div>";

echo '</div>';
echo $OUTPUT->footer();
