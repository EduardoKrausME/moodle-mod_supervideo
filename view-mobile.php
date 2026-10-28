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
 * Browser fallback page for the Super Video activity.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Browser fallback page for mod_supervideo.
 *
 * This endpoint deliberately does not accept Moodle Web Service tokens. The
 * official Moodle App uses mod_supervideo_get_playback_data instead.
 *
 * @package mod_supervideo
 */

use mod_supervideo\event\course_module_viewed;
use mod_supervideo\output\view;

require(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$cm = get_coursemodule_from_id("supervideo", $id, 0, false, MUST_EXIST);
$course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
$supervideo = $DB->get_record("supervideo", ["id" => $cm->instance], "*", MUST_EXIST);

require_course_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability("mod/supervideo:view", $context);

$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_url("/mod/supervideo/view-mobile.php", ["id" => $cm->id]);
$PAGE->set_title(format_string($supervideo->name));
$PAGE->set_pagelayout("embedded");
$PAGE->add_body_class("distraction-free-mode");
$PAGE->add_body_class("distraction-free-mode-noheader");

$event = course_module_viewed::create([
    "objectid" => $PAGE->cm->instance,
    "context" => $context,
]);
$event->add_record_snapshot("course", $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $supervideo);
$event->trigger();

$view = new view($cm, $course, $supervideo, $context);
$videoplayer = $view->get_player();

echo $OUTPUT->header();
$data = [
    "showmap" => $view->config->showmap,
    "map" => $view->get_maps(),
    "errosmessages" => $view->errosmessages,
    "video_player" => $videoplayer,
    "page_title" => $view->supervideo->name,
    "url_back" => $CFG->wwwroot . "/course/view.php?id=" . $cm->course,
    "url_settings" => $CFG->wwwroot . "/course/modedit.php?update=" . $cm->id,
];
echo $OUTPUT->render_from_template("mod_supervideo/view-mobile", $data);
echo $OUTPUT->footer();
