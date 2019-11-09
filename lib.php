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
 * lib file
 *
 * @package    mod_supervideo
 * @copyright  2015 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param string $feature
 * @return bool|int|null
 */
function supervideo_supports($feature) {

    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        default:
            return null;
    }
}

/**
 * function supervideo_add_instance
 *
 * @param stdClass $supervideo
 * @param mod_supervideo_mod_form|null $mform
 * @return bool|int
 * @throws dml_exception
 */
function supervideo_add_instance(stdClass $supervideo, mod_supervideo_mod_form $mform = null) {
    global $DB;

    $supervideo->timecreated = time();

    $supervideo->id = $DB->insert_record('supervideo', $supervideo);

    return $supervideo->id;
}

/**
 * function supervideo_update_instance
 *
 * @param stdClass $supervideo
 * @param mod_supervideo_mod_form|null $mform
 * @return bool
 * @throws dml_exception
 */
function supervideo_update_instance(stdClass $supervideo, mod_supervideo_mod_form $mform = null) {
    global $DB;

    $supervideo->timemodified = time();
    $supervideo->id = $supervideo->instance;

    $result = $DB->update_record('supervideo', $supervideo);

    return $result;
}

/**
 * function supervideo_delete_instance
 *
 * @param int $id
 * @return bool
 * @throws dml_exception
 */
function supervideo_delete_instance($id) {
    global $DB;

    if (!$supervideo = $DB->get_record('supervideo', array('id' => $id))) {

        echo('aaaa');
        return false;
    }

    $DB->delete_records('supervideo', array('id' => $supervideo->id));

    return true;
}

/**
 * function supervideo_user_outline
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $supervideo
 * @return stdClass
 */
function supervideo_user_outline($course, $user, $mod, $supervideo) {
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * function supervideo_user_complete
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $supervideo
 * @throws coding_exception
 * @throws dml_exception
 */
function supervideo_user_complete($course, $user, $mod, $supervideo) {
    global $DB;

    if ($logs = $DB->get_records('log', array('userid' => $user->id, 'module' => 'supervideo',
        'action' => 'view', 'info' => $supervideo->id), 'time ASC')) {
        $numviews = count($logs);
        $lastlog = array_pop($logs);

        $strmostrecently = get_string('mostrecently');
        $strnumviews = get_string('numviews', '', $numviews);

        echo "$strnumviews - $strmostrecently " . userdate($lastlog->time);

    } else {
        print_string('neverseen', 'supervideo');
    }
}

/**
 * function supervideo_get_coursemodule_info
 * @param stdClass $coursemodule
 * @return cached_cm_info
 * @throws dml_exception
 */
function supervideo_get_coursemodule_info($coursemodule) {
    global $DB;

    $supervideo = $DB->get_record('supervideo', array('id' => $coursemodule->instance),
        'id, name, videourl, videosize, intro, introformat');

    $info = new cached_cm_info();
    $info->name = $supervideo->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('supervideo', $supervideo, $coursemodule->id, false);
    }

    return $info;
}

