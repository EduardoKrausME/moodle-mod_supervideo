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
 * @copyright  2015 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

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

// Print the page header.
$PAGE->set_url('/mod/supervideo/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($supervideo->name));
$PAGE->set_heading(format_string($course->fullname));

// Update 'viewed' state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$config = get_config('supervideo');

$PAGE->set_url('/mod/supervideo/view.php', array('id' => $cm->id));
$PAGE->requires->js('/mod/supervideo/assets/util.js', true);
$PAGE->requires->css('/mod/supervideo/assets/supervideo.css');
$PAGE->set_title($course->shortname . ': ' . $supervideo->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($supervideo);
echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($supervideo->name), 2, 'main', 'supervideoheading');

echo '<div id="supervideoworkaround">';

$videoId = false;
$engine = "";
if (strpos($supervideo->videourl, "youtube")) {
    if (preg_match('/[\?|&]v=([a-zA-Z0-9\-_]{11})/', $supervideo->videourl, $output)) {
        $videoId = $output[1];
        $engine = "youtube";
    }
} else if (strpos($supervideo->videourl, "youtu.be")) {
    if (preg_match('/youtu.be\/([a-zA-Z0-9\-_]{11})/', $supervideo->videourl, $output)) {
        $videoId = $output[1];
        $engine = "youtube";
    }
} else if (strpos($supervideo->videourl, "vimeo")) {
    if (preg_match('/vimeo.com\/(\d+)/', $supervideo->videourl, $output)) {
        $videoId = $output[1];
        $engine = "vimeo";
    }
} else if (strpos($supervideo->videourl, "drive.google.com")) {
    if (preg_match('/([a-zA-Z0-9\-_]{33})/', $supervideo->videourl, $output)) {
        $videoId = $output[1];
        $engine = "drive";
    }
}

if ($supervideo->videosize == 0) {
    $size = 'width="320" height="240"';
} else if ($supervideo->videosize == 1) {
    $size = 'width="720" height="480"';
} else if ($supervideo->videosize == 2) {
    $size = 'width="720" height="450"';
}

if ($videoId) {
    if ($engine == "youtube" || $engine == "drive") {
        $urlparameters = array();

        if ($supervideo->showrel) {
            $urlparameters[] = 'rel=1';
        } else {
            $urlparameters[] = 'rel=0';
        }

        if ($supervideo->showcontrols) {
            $urlparameters[] = 'controls=1';
        } else {
            $urlparameters[] = 'controls=0';
        }

        if ($supervideo->showshowinfo) {
            $urlparameters [] = 'showinfo=1';
        } else {
            $urlparameters [] = 'showinfo=0';
        }

        if ($supervideo->autoplay) {
            $urlparameters [] = 'autoplay=1';
        } else {
            $urlparameters [] = 'autoplay=0';
        }
        $parameters = implode('&amp;', $urlparameters);

        if ($engine == "youtube") {
            $url = "https://www.youtube.com/embed/{$videoId}?{$parameters}";
            echo "<iframe id=\"videohd{$supervideo->videosize}\" {$size} 
                      frameborder=\"0\" webkitallowfullscreen mozallowfullscreen allowfullscreen
                      src=\"{$url}\"></iframe>";
        } else if ($engine == "drive") {
            $url = "https://drive.google.com/file/d/{$videoId}/preview?{$parameters}";
            echo "<iframe id=\"videohd{$supervideo->videosize}\" {$size}
                      frameborder=\"0\" webkitallowfullscreen mozallowfullscreen allowfullscreen
                      src=\"{$url}\"></iframe>";
        }
    } else if ($engine == "vimeo") {
        $urlparametersvimeo = array();

        if ($supervideo->showcontrols) {
            $urlparametersvimeo [] = 'title=true';
        } else {
            $urlparametersvimeo [] = 'title=false';
        }

        if ($supervideo->autoplay) {
            $urlparametersvimeo [] = 'autoplay=true';
        } else {
            $urlparametersvimeo [] = 'autoplay=false';
        }

        $parametersvimeo = implode('&amp;', $urlparametersvimeo);

        $url = "https://player.vimeo.com/video/{$videoId}?{$parametersvimeo}";
        echo "<iframe id=\"videohd{$supervideo->videosize}\" {$size}
                      frameborder=\"0\" webkitallowfullscreen mozallowfullscreen allowfullscreen
                      src=\"{$url}\"></iframe>";
    }
} else {
    echo "<div class=\"alert alert-warning\">
              <i class=\"fa fa-exclamation-circle\"></i>
              " . get_string('idnotfound', 'supervideo') . "
          </div>";
}

echo '</div>';
echo $OUTPUT->footer();
