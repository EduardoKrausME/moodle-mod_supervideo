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
 * Migrate vídeos from mod_videotime
 *
 * @package    mod_supervideo
 * @copyright  2023 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
(new \core\task\file_trash_cleanup_task())->execute();

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
session_write_close();

$modulevideotime = $DB->get_record('modules', ['name' => 'videotime']);
if (!$modulevideotime) {
    die("Você não tem o MOD_VIDEOTIME instalado");
}
$modulesupervideo = $DB->get_record('modules', ['name' => 'supervideo']);

$videotimes = $DB->get_records("videotime");

foreach ($videotimes as $videotime) {

    $supervideo = (object)[
        'course' => $videotime->course,
        'name' => $videotime->name,
        'intro' => $videotime->intro,
        'introformat' => $videotime->introformat,
        'videourl' => $videotime->vimeo_url,
        'playersize' => 1,
        'showcontrols' => 1,
        'autoplay' => 0,
        'timemodified' => $videotime->timemodified,
    ];

    $supervideo->id = $DB->insert_record("supervideo", $supervideo);

    $coursemodules = $DB->get_record("course_modules", [
        'module' => $modulevideotime->id,
        'instance' => $videotime->id,
        'deletioninprogress' => 0
    ]);

    if ($coursemodules) {
        $coursemodules->module = $modulesupervideo->id;
        $coursemodules->instance = $supervideo->id;

        $DB->update_record('course_modules', $coursemodules);
    }
}
