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

/**
 * @param string $feature
 *
 * @return bool|int|null
 */
function supervideo_supports($feature) {

    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMMENT:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case 'mod_purpose':
            return 'content';
        default:
            return null;
    }
}


/**
 * @param stdClass $supervideo
 * @param int $userid
 * @param bool $nullifnone
 *
 * @return null
 * @throws coding_exception
 * @throws dml_exception
 */
function supervideo_update_grades($supervideo, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($supervideo->grade_approval) {
        if ($grades = supervideo_get_user_grades($supervideo, $userid)) {
            \mod_supervideo\grade\grades_util::grade_item_update($supervideo, $grades);
        }
    }
}

/**
 * @param stdClass $supervideo
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

    $extrawhere = ' ';
    if ($userid > 0) {
        $extrawhere .= ' AND user_id = :user_id';
        $params['user_id'] = $userid;
    }

    $sql = "SELECT user_id as userid, MAX(percent) as rawgrade
              FROM {supervideo_view}
             WHERE cm_id = :cm_id {$extrawhere}
             GROUP BY user_id";
    return $DB->get_records_sql($sql, $params);
}

/**
 * @param stdClass $supervideo
 * @param mod_supervideo_mod_form|null $mform
 *
 * @return bool|int
 * @throws dml_exception
 * @throws coding_exception
 */
function supervideo_add_instance(stdClass $supervideo, mod_supervideo_mod_form $mform = null) {
    global $DB;

    $supervideo->timemodified = time();
    $supervideo->timecreated = time();
    $supervideo->id = $DB->insert_record('supervideo', $supervideo);

    \mod_supervideo\grade\grades_util::grade_item_update($supervideo);
    supervideo_set_mainfile($supervideo);

    return $supervideo->id;
}

/**
 * @param stdClass $supervideo
 *
 * @throws coding_exception
 */
function supervideo_set_mainfile($supervideo) {
    $fs = get_file_storage();
    $cmid = $supervideo->coursemodule;
    $draftitemid = $supervideo->videofile;

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        $options = ['subdirs' => true, 'embed' => true];
        file_save_draft_area_files($draftitemid, $context->id, 'mod_supervideo', 'content', $supervideo->id, $options);
    }
    $files = $fs->get_area_files($context->id, 'mod_supervideo', 'content', 0, 'sortorder', false);
    if (count($files) == 1) {
        $file = reset($files);
        file_set_sortorder($context->id, 'mod_supervideo', 'content', 0, $file->get_filepath(), $file->get_filename(), 1);
    }
}

/**
 * function supervideo_update_instance
 *
 * @param stdClass $supervideo
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

    \mod_supervideo\grade\grades_util::grade_item_update($supervideo);

    return $result;
}

/**
 * function supervideo_delete_instance
 *
 * @param int $id
 *
 * @return bool
 * @throws dml_exception
 * @throws coding_exception
 */
function supervideo_delete_instance($id) {
    global $DB;

    if (!$supervideo = $DB->get_record('supervideo', array('id' => $id))) {
        return false;
    }

    $fs = get_file_storage();
    $cm = get_coursemodule_from_id('supervideo', $supervideo->id);
    if ($cm) {
        $files = $fs->get_area_files(context_module::instance($cm->id)->id, 'mod_supervideo', 'content',
            $supervideo->id, 'sortorder DESC, id ASC', false);

        foreach ($files as $file) {
            $file->delete();
        }
    }
    $DB->delete_records('supervideo', array('id' => $supervideo->id));
    $DB->delete_records('supervideo_view', array('cm_id' => $cm->id));

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
        'cm_id' => $mod->id,
        'user_id' => $user->id,
    ];
    if ($registros = $DB->get_records_sql($sql, $param)) {
        echo "<table><tr>";
        echo "      <th>" . get_string('report_userid', 'mod_supervideo') . "</th>";
        echo "      <th>" . get_string('report_nome', 'mod_supervideo') . "</th>";
        echo "      <th>" . get_string('report_email', 'mod_supervideo') . "</th>";
        echo "      <th>" . get_string('report_tempo', 'mod_supervideo') . "</th>";
        echo "      <th>" . get_string('report_duracao', 'mod_supervideo') . "</th>";
        echo "      <th>" . get_string('report_porcentagem', 'mod_supervideo') . "</th>";
        echo "      <th>" . get_string('report_comecou', 'mod_supervideo') . "</th>";
        echo "      <th>" . get_string('report_terminou', 'mod_supervideo') . "</th>";
        echo "  </tr>";
        foreach ($registros as $registro) {
            echo "<tr>";
            echo "  <td>" . $registro->user_id . "</td>";
            echo "  <td>" . fullname($registro) . "</td>";
            echo "  <td>" . $registro->email . "</td>";
            echo "  <td>" . formatTime($registro->currenttime) . "</td>";
            echo "  <td>" . formatTime($registro->duration) . "</td>";
            echo "  <td>" . $registro->percent . "%</td>";
            echo "  <td>" . userdate($registro->timecreated) . "</td>";
            echo "  <td>" . userdate($registro->timemodified) . "</td>";
            echo "</tr>";
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
        'id, name, videourl, intro, introformat, completionpercent');

    $info = new cached_cm_info();
    if ($supervideo) {
        $info->name = $supervideo->name;
    }

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('supervideo', $supervideo, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionpercent'] = $supervideo->completionpercent;
    }

    return $info;
}

/**
 * @param settings_navigation $settings
 * @param navigation_node $supervideonode
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
    if ($i === false && array_key_exists(0, $keys)) {
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
 * @param \navigation_node $navigation
 * @param stdClass $course
 * @param \context $context
 *
 * @throws coding_exception
 * @throws moodle_exception
 */
function supervideo_extend_navigation_course($navigation, $course, $context) {
    $node = $navigation->get('coursereports');
    if ($node && has_capability('mod/supervideo:view_report', $context)) {
        $url = new moodle_url('/mod/supervideo/reports.php', ['course' => $course->id]);
        $node->add(get_string('pluginname', 'supervideo'), $url, navigation_node::TYPE_SETTING, null, null,
            new pix_icon('i/report', ''));
    }
}

/**
 * Serve the files from the supervideo file areas
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 *
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 *
 * @throws coding_exception
 * @throws moodle_exception
 * @throws require_login_exception
 */
function supervideo_pluginfile($course, $cm, context $context, $filearea, $args, $forcedownload, array $options = array()) {

    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Make sure the user is logged in and has access to the module
    // (plugins that are not course modules should leave out the 'cm' part).
    require_login($course, true, $cm);

    // Check the relevant capabilities - these may vary depending on the filearea being accessed.
    if (!has_capability('mod/supervideo:view', $context)) {
        return false;
    }

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.

    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        // Variable $args is empty => the path is '/'.
        $filepath = '/';
    } else {
        // Variable $args contains elements of the filepath.
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_supervideo', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Register the ability to handle drag and drop file uploads
 *
 * @return array containing details of the files / types the mod can handle
 * @throws coding_exception
 */
function supervideo_dndupload_register() {
    $ret = [
        'files' => [
            [
                'extension' => 'mp3',
                'message' => get_string('dnduploadlabel-mp3', 'mod_supervideo'),
            ],
            [
                'extension' => 'mp4',
                'message' => get_string('dnduploadlabel-mp4', 'mod_supervideo'),
            ]
        ],
        'types' => [
            [
                'identifier' => 'text/html',
                'message' => get_string('dnduploadlabeltext', 'mod_supervideo'),
                'noname' => true
            ],
            [
                'identifier' => 'text',
                'message' => get_string('dnduploadlabeltext', 'mod_supervideo'),
                'noname' => true
            ]
        ]
    ];
    return $ret;
}


/**
 * Handle a file that has been uploaded
 *
 * @param stdClass $uploadinfo details of the file / content that has been uploaded
 *
 * @return int instance id of the newly created mod
 * @throws coding_exception
 * @throws dml_exception
 */
function supervideo_dndupload_handle($uploadinfo) {
    global $USER;

    // Gather the required info.
    $data = new stdClass();
    $data->course = $uploadinfo->course->id;
    $data->name = $uploadinfo->displayname;
    $data->intro = '';
    $data->introformat = FORMAT_HTML;
    $data->coursemodule = $uploadinfo->coursemodule;

    $data->videosize = 1;
    $data->showrel = 0;
    $data->showcontrols = 1;
    $data->showinfo = 0;
    $data->autoplay = 0;
    $data->grade_approval = 0;

    $data->instance = supervideo_add_instance($data, null);

    if (!empty($uploadinfo->draftitemid)) {
        $fs = get_file_storage();
        $draftcontext = context_user::instance($USER->id);
        $context = context_module::instance($uploadinfo->coursemodule);
        $files = $fs->get_area_files($draftcontext->id, 'user', 'draft', $uploadinfo->draftitemid, '', false);
        if ($file = reset($files)) {

            $data->videourl = "[resource-file:{$file->get_filename()}]";
            $options = ['subdirs' => true, 'embed' => true];
            file_save_draft_area_files(
                $uploadinfo->draftitemid, $context->id, 'mod_supervideo', 'content', $data->instance, $options);

            supervideo_update_instance($data, null);
        }
    } else if (!empty($uploadinfo->content)) {
        $data->intro = $uploadinfo->content;
        if ($uploadinfo->type != 'text/html') {
            $data->introformat = FORMAT_PLAIN;
        }
    }

    return $data->instance;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 * @throws coding_exception
 */
function mod_supervideo_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    throw new Exception("aaaa");

    $descriptions = [];
    $completionpercent = $cm->customdata['customcompletionrules']['completionpercent'] ?? 0;
    $descriptions[] = "Requer {$completionpercent} %";
    return $descriptions;
}

/**
 * Sets the automatic completion state for this database item based on the
 * count of on its entries.
 * @since Moodle 3.3
 * @param object $data The data object for this activity
 * @param object $course Course
 * @param object $cm course-module
 * @throws moodle_exception
 */
function supervideo_update_completion_state($data, $course, $cm) {

    throw new Exception("bbbb");

    // If completion option is enabled, evaluate it and return true/false.
    $completion = new completion_info($course);
    if ($data->completionpercent && $completion->is_enabled($cm)) {
        $numentries = data_numentries($data);
        // Check the number of entries required against the number of entries already made.
        if ($numentries >= $data->completionpercent) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        } else {
            $completion->update_state($cm, COMPLETION_INCOMPLETE);
        }
    }
}

/**
 * Obtains the automatic completion state for this database item based on any conditions
 * on its settings. The call for this is in completion lib where the modulename is appended
 * to the function name. This is why there are unused parameters.
 *
 * @deprecated since Moodle 3.11
 * @todo MDL-71196 Final deprecation in Moodle 4.3
 * @see \mod_data\completion\custom_completion
 * @since Moodle 3.3
 * @param stdClass $course Course
 * @param cm_info|stdClass $cm course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function supervideo_get_completion_state($course, $cm, $userid, $type) {
    global $DB, $PAGE;

    // No need to call debugging here. Deprecation debugging notice already being called in \completion_info::internal_get_state().

    $result = $type; // Default return value
    // Get data details.
    if (isset($PAGE->cm->id) && $PAGE->cm->id == $cm->id) {
        $data = $PAGE->activityrecord;
    } else {
        $data = $DB->get_record('data', array('id' => $cm->instance), '*', MUST_EXIST);
    }
    // If completion option is enabled, evaluate it and return true/false.
    if ($data->completionpercent) {

        //$sql = 'SELECT COUNT(*) FROM {data_records} WHERE dataid=? AND userid=?';
        //$numentries = $DB->count_records_sql($sql, array($data->id, $userid));

        $numentries = 10;

        // Check the number of entries required against the number of entries already made.
        if ($numentries >= $data->completionpercent) {
            $result = true;
        } else {
            $result = false;
        }
    }
    return $result;
}

///**
// * @param stdClass $course
// * @param stdClass $cm
// * @param int $userid
// * @param string $type
// *
// * @return bool
// *
// * @throws dml_exception
// */
//function supervideo_get_completion_state($course, $cm, $userid, $type) {
//    return \mod_supervideo\completion\completion_util::get_completion_state($course, $cm, $userid);
//}