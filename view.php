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
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_supervideo\event\course_module_viewed;
use mod_supervideo\output\view;

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

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Update "viewed" state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_cm($cm, $course);
$PAGE->set_url("/mod/supervideo/view.php", ["n" => $n, "id" => $id]);
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

$view = new view($cm, $course, $supervideo, $context);
$videoplayer = $view->get_player();

$PAGE->requires->jquery();

if ($view->freemode) {
    $PAGE->set_pagelayout("embedded");
    $PAGE->add_body_class("body-df");
    echo $OUTPUT->header();

    $mustachedata = [
        "showmapa" => $view->config->showmapa,
        "mapa" => $view->get_maps(),
        "errosmessages" => $view->errosmessages,
        "video-player" => $videoplayer,
        "page-title" => $view->supervideo->name,
        "url-back" => "{$CFG->wwwroot}/course/view.php?id={$cm->course}",
        "url-settings" => "{$CFG->wwwroot}/course/modedit.php?update={$cm->id}",
    ];
    echo $OUTPUT->render_from_template("mod_supervideo/view-freemode", $mustachedata);

    echo $OUTPUT->footer();
} else {
    echo $OUTPUT->header();

    echo $view->errosmessages;
    echo $videoplayer;
    if ($view->config->showmapa) {
        echo $view->get_maps();
    }

    echo $OUTPUT->footer();
}
