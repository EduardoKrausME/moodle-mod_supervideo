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
 * Prints an instance of mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\session\manager;
use mod_supervideo\event\course_module_viewed;
use mod_supervideo\output\view;

require(__DIR__ . "/../../config.php");

$id = optional_param("id", 0, PARAM_INT);
$cm = get_coursemodule_from_id("supervideo", $id, 0, false, MUST_EXIST);
$course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
$supervideo = $DB->get_record("supervideo", ["id" => $cm->instance], "*", MUST_EXIST);

$token = required_param("token", PARAM_TEXT);
$externalservice = $DB->get_record("external_services", ["shortname" => MOODLE_OFFICIAL_MOBILE_SERVICE]);
$externaltoken = $DB->get_record("external_tokens", ["token" => $token, "externalserviceid" => $externalservice->id], "userid");
$user = $DB->get_record("user", ["id" => $externaltoken->userid]);

if ($user) {
    manager::login_user($user);
    require_course_login($course, false, null, false, true);
} else {
    redirect(new moodle_url("/mod/supervideo/view.php", ["id" => $id]));
}

$context = context_module::instance($cm->id);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_url("/mod/supervideo/view-mobile.php", ["id" => $cm->id]);
$PAGE->set_title(format_string($supervideo->name));
$PAGE->set_pagelayout("embedded");
$PAGE->add_body_class("body-df");
$PAGE->add_body_class("body-df-noheader");

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
$mustachedata = [
    "showmapa" => $view->config->showmapa,
    "mapa" => $view->get_maps(),
    "errosmessages" => $view->errosmessages,
    "video-player" => $videoplayer,
    "page-title" => $view->supervideo->name,
    "url-back" => "{$CFG->wwwroot}/course/view.php?id={$cm->course}",
    "url-settings" => "{$CFG->wwwroot}/course/modedit.php?update={$cm->id}",
];
echo $OUTPUT->render_from_template("mod_supervideo/view-mobile", $mustachedata);
echo $OUTPUT->footer();
