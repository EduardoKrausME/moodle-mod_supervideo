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
 * @copyright  2023 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param string $feature
 *
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
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

/**
 * @param      $supervideo
 * @param null $grades
 *
 * @return int
 */
function supervideo_grade_item_update($supervideo, $grades = null) {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname'  => $supervideo->name,
        'idnumber'  => $supervideo->cmidnumber,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => 100,
        'grademin'  => 0
    ];

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/supervideo', $supervideo->course, 'mod', 'supervideo', $supervideo->id, 0, $grades, $params);
}

/**
 * @param      $supervideo
 * @param int  $userid
 * @param bool $nullifnone
 *
 * @return null
 * @throws coding_exception
 * @throws dml_exception
 */
function supervideo_update_grades($supervideo, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (!$supervideo->grade_approval) {
        return null;
    }

    if ($grades = supervideo_get_user_grades($supervideo, $userid)) {
        supervideo_grade_item_update($supervideo, $grades);
    }
}

/**
 * @param     $supervideo
 * @param int $userid
 *
 * @return array|bool
 * @throws coding_exception
 * @throws dml_exception
 */
function supervideo_get_user_grades($supervideo, $userid = 0) {
    global $DB;

    if (!$supervideo->grade_approval) {
        return false;
    }

    $cm = get_coursemodule_from_instance('supervideo', $supervideo->id);

    $params = ['cm_id' => $cm->id];

    $extra_where = ' ';
    if ($userid > 0) {
        $extra_where .= ' AND user_id = :user_id';
        $params['user_id'] = $userid;
    }

    $sql = "SELECT user_id as userid, MAX(percent) as rawgrade
              FROM {supervideo_view}
             WHERE cm_id = :cm_id {$extra_where}
             GROUP BY user_id";
    return $DB->get_records_sql($sql, $params);
}


/**
 * @param stdClass                     $supervideo
 * @param mod_supervideo_mod_form|null $mform
 *
 * @return bool|int
 * @throws dml_exception
 */
function supervideo_add_instance(stdClass $supervideo, mod_supervideo_mod_form $mform = null) {
    global $DB;

    $supervideo->timecreated = time();

    $supervideo->id = $DB->insert_record('supervideo', $supervideo);

    supervideo_grade_item_update($supervideo);

    return $supervideo->id;
}

/**
 * function supervideo_update_instance
 *
 * @param stdClass                     $supervideo
 * @param mod_supervideo_mod_form|null $mform
 *
 * @return bool
 * @throws dml_exception
 */
function supervideo_update_instance(stdClass $supervideo, mod_supervideo_mod_form $mform = null) {
    global $DB;

    $supervideo->timemodified = time();
    $supervideo->id = $supervideo->instance;

    $result = $DB->update_record('supervideo', $supervideo);

    supervideo_grade_item_update($supervideo);

    return $result;
}

/**
 * function supervideo_delete_instance
 *
 * @param int $id
 *
 * @return bool
 * @throws dml_exception
 */
function supervideo_delete_instance($id) {
    global $DB;

    if (!$supervideo = $DB->get_record('supervideo', array('id' => $id))) {
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
 *
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
 *
 * @throws coding_exception
 * @throws dml_exception
 */
function supervideo_user_complete($course, $user, $mod, $supervideo) {
    global $DB;

    $sql = "SELECT sv.user_id, sv.currenttime, sv.duration, sv.percent, sv.timecreated, sv.timemodified, sv.mapa,
                   u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email
              FROM {supervideo_view} sv
              JOIN {user} u ON u.id = sv.user_id
             WHERE sv.cm_id   = :cm_id 
               AND sv.user_id = :user_id 
               AND percent    > 0 
          ORDER BY sv.timecreated ASC";
    $param = [
        'cm_id'   => $mod->id,
        'user_id' => $user->id,
    ];
    if ($registros = $DB->get_records_sql($sql, $param)) {
        echo "<table>
                <tr>
                    <th>" . get_string('report_userid', 'mod_supervideo') . "</th> 
                    <th>" . get_string('report_nome', 'mod_supervideo') . "</th>
                    <th>" . get_string('report_email', 'mod_supervideo') . "</th>
                    <th>" . get_string('report_tempo', 'mod_supervideo') . "</th>
                    <th>" . get_string('report_duracao', 'mod_supervideo') . "</th>
                    <th>" . get_string('report_porcentagem', 'mod_supervideo') . "</th>
                    <th>" . get_string('report_comecou', 'mod_supervideo') . "</th>
                    <th>" . get_string('report_terminou', 'mod_supervideo') . "</th>
                </tr>";
        foreach ($registros as $registro) {
            echo "
                <tr>
                    <td>" . $registro->user_id . "</td>
                    <td>" . fullname($registro) . "</td>
                    <td>" . $registro->email . "</td>
                    <td>" . formatTime($registro->currenttime) . "</td>
                    <td>" . formatTime($registro->duration) . "</td>
                    <td>" . $registro->percent . "%</td>
                    <td>" . userdate($registro->timecreated) . "</td>
                    <td>" . userdate($registro->timemodified) . "</td>
                </tr>";
        }
        echo "</table>";

    } else {
        print_string('no_data', 'supervideo');
    }
}

/**
 * function supervideo_get_coursemodule_info
 *
 * @param stdClass $coursemodule
 *
 * @return cached_cm_info
 * @throws dml_exception
 */
function supervideo_get_coursemodule_info($coursemodule) {
    global $DB;

    $supervideo = $DB->get_record('supervideo', ['id' => $coursemodule->instance],
        'id, name, videourl, intro, introformat');

    $info = new cached_cm_info();
    $info->name = $supervideo->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('supervideo', $supervideo, $coursemodule->id, false);
    }

    return $info;
}

/**
 * @param settings_navigation $settings
 * @param navigation_node     $supervideonode
 *
 * @return void
 * @throws \coding_exception
 * @throws moodle_exception
 */
function supervideo_extend_settings_navigation($settings, $supervideonode) {
    global $PAGE;

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $supervideonode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('moodle/course:manageactivities', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('report', 'mod_supervideo'),
            new moodle_url('/mod/supervideo/report.php', array('id' => $PAGE->cm->id)),
            navigation_node::TYPE_SETTING, null, 'mod_supervideo_report',
            new pix_icon('i/report', ''));
        $supervideonode->add_node($node, $beforekey);
    }
}

/**
 * @param $navigation
 * @param $course
 * @param $context
 *
 * @throws coding_exception
 * @throws moodle_exception
 */
function supervideo_extend_navigation_course($navigation, $course, $context) {
    $node = $navigation->get('coursereports');
    if (has_capability('mod/supervideo:view_report', $context)) {
        $url = new moodle_url('/mod/supervideo/index.php', ['id' => $course->id]);
        $node->add(get_string('pluginname', 'supervideo'), $url, navigation_node::TYPE_SETTING, null, null,
            new pix_icon('i/report', ''));
    }
}

function supervideo_get_completion_state($course, $cm, $userid, $type) {
    return \mod_supervideo\grades::supervideo_get_completion_state($cm);
}