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
 * Report for supervideo.
 *
 * @package    mod_supervideo
 * @copyright  2023 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$id = optional_param('id', 0, PARAM_INT);
$userid = optional_param('u', false, PARAM_INT);
$cm = get_coursemodule_from_id('supervideo', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$supervideo = $DB->get_record('supervideo', array('id' => $cm->instance), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/supervideo:view_report', $context);

if (!has_capability('moodle/course:manageactivities', $context, $USER)) {
    $userid = $USER->id;
}

$table = new \mod_supervideo\report\supervideo_view("supervideo_report", $cm->id, $userid, $supervideo);

if (!$table->is_downloading()) {
    $PAGE->set_url('/mod/supervideo/report.php', array('id' => $cm->id));
    $PAGE->requires->css('/mod/supervideo/style.css');
    $PAGE->set_title("{$course->shortname}: {$supervideo->name}");
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    $linkvoltar = "";
    if ($table->userid) {
        $linkvoltar = " <a href='?id={$table->cmid}' class='supervideo-report-link'>Voltar</a>";
    }

    $title = get_string('report') . ": " . format_string($supervideo->name) . $linkvoltar;
    echo $OUTPUT->heading($title, 2, 'main', 'supervideoheading');
}

$table->define_baseurl("{$CFG->wwwroot}/mod/supervideo/report.php?id={$cm->id}&u={$userid}");
$table->out(40, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
