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
 * Lib file.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Supervideo_supports function.
 *
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
 * supervideo_update_grades File.
 *
 * @param stdClass $supervideo
 * @param int $userid
 * @param bool $nullifnone
 *
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
 * supervideo_get_user_grades file.
 *
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
 * supervideo_add_instance file.
 *
 * @param stdClass $supervideo
 * @param mod_supervideo_mod_form $mform
 *
 * @return bool|int
 * @throws dml_exception
 * @throws coding_exception
 * @throws moodle_exception
 */
function supervideo_add_instance(stdClass $supervideo, $mform = null) {
    global $DB;

    $supervideo->timemodified = time();
    $supervideo->timecreated = time();
    $supervideo->playersize = optional_param("playersize", null, PARAM_RAW);

    $supervideo->id = $DB->insert_record('supervideo', $supervideo);

    \mod_supervideo\grade\grades_util::grade_item_update($supervideo);
    supervideo_set_mainfile($supervideo);

    return $supervideo->id;
}

/**
 * supervideo_set_mainfile file.
 *
 * @param stdClass $supervideo
 *
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function supervideo_set_mainfile($supervideo) {
    $cmid = $supervideo->coursemodule;
    $draftitemid = $supervideo->videofile;

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        $options = ['subdirs' => true, 'embed' => true];
        file_save_draft_area_files($draftitemid, $context->id, 'mod_supervideo', 'content', $supervideo->id, $options);
    }
    $files = supervideo_get_area_files($context->id);
    if ($files && count($files) == 1) {
        $file = reset($files);
        file_set_sortorder($context->id, 'mod_supervideo', 'content', 0, $file->get_filepath(), $file->get_filename(), 1);
    }
}

/**
 * function supervideo_update_instance
 *
 * @param stdClass $supervideo
 * @param mod_supervideo_mod_form $mform
 *
 * @return bool
 * @throws dml_exception
 * @throws coding_exception
 */
function supervideo_update_instance(stdClass $supervideo, $mform = null) {
    global $DB;

    $supervideo->timemodified = time();
    $supervideo->id = $supervideo->instance;
    $supervideo->playersize = optional_param("playersize", null, PARAM_RAW);

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
 * @throws moodle_exception
 */
function supervideo_delete_instance($id) {
    global $DB;

    if (!$supervideo = $DB->get_record('supervideo', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_id('supervideo', $supervideo->id);
    if ($cm) {
        $files = supervideo_get_area_files(context_module::instance($cm->id)->id);

        foreach ($files as $file) {
            $file->delete();
        }
    }
    $DB->delete_records('supervideo', ['id' => $supervideo->id]);
    $DB->delete_records('supervideo_view', ['cm_id' => $cm->id]);

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
            echo "  <td>" . supervideo_format_time($registro->currenttime) . "</td>";
            echo "  <td>" . supervideo_format_time($registro->duration) . "</td>";
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
 * supervideo_format_time function
 *
 * @param $time
 *
 * @return string
 */
function supervideo_format_time($time) {
    if ($time < 60) {
        return "00:00:{$time}";
    } else {
        $horas = '';
        $minutos = floor($time / 60);
        $segundos = ($time % 60);

        if ($minutos > 59) {
            $horas = floor($minutos / 60);
            $minutos = ($minutos % 60);
        }

        $horas = substr("00{$horas}", -2);
        $minutos = substr("00{$minutos}", -2);
        $segundos = substr("00{$segundos}", -2);
        return "{$horas}:{$minutos}:{$segundos}";
    }
}

/**
 * supervideo_extend_settings_navigation function.
 *
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
            new moodle_url('/mod/supervideo/report.php', ['id' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING, null, 'mod_supervideo_report',
            new pix_icon('i/report', ''));
        $supervideonode->add_node($node, $beforekey);
    }
}

/**
 * supervideo_extend_navigation_course function
 *
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
 * @param stdClass $course    the course object
 * @param stdClass $cm        the course module object
 * @param context $context    the context
 * @param string $filearea    the name of the file area
 * @param array $args         extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options      additional options affecting the file serving
 *
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 * @throws coding_exception
 * @throws moodle_exception
 * @throws require_login_exception
 */
function supervideo_pluginfile($course, $cm, context $context, $filearea, $args, $forcedownload, array $options = []) {

    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_MODULE) {
        $filepath = $args[0];
        $itemid = $args[1];
        $filename = $args[2];

        $fs = get_file_storage();

        $file = $fs->get_file($context->id, 'user', $filearea, $itemid, "/{$filepath}", $filename);
        if ($file) {
            send_stored_file($file, 86400, 0, $forcedownload, $options);
            return true;
        }
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
    if ($file) {
        send_stored_file($file, 86400, 0, $forcedownload, $options);
        return true;
    }
    return false;
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
            ],
            [
                'extension' => 'webm',
                'message' => get_string('dnduploadlabel-mp4', 'mod_supervideo'),
            ],
        ],
        'types' => [
            [
                'identifier' => 'text/html',
                'message' => get_string('dnduploadlabeltext', 'mod_supervideo'),
                'noname' => true,
            ],
            [
                'identifier' => 'text',
                'message' => get_string('dnduploadlabeltext', 'mod_supervideo'),
                'noname' => true,
            ],
        ],
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
 * @throws moodle_exception
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

    $data->playersize = 1;
    $data->showcontrols = 1;
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
 *
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_supervideo_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    $completionpercent = $cm->customdata['customcompletionrules']['completionpercent'] ?? 0;
    $descriptions[] = "Requer {$completionpercent} %";
    return $descriptions;
}

/**
 * Sets the automatic completion state for this database item based on the count of on its entries.
 *
 * @param object $data   The data object for this activity
 * @param object $course Course
 * @param object $cm     course-module
 *
 * @throws moodle_exception
 */
function supervideo_update_completion_state($data, $course, $cm) {

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
 * @param stdClass $course     Course
 * @param cm_info|stdClass $cm course-module
 * @param int $userid          User ID
 * @param bool $type           Type of comparison (or/and; can be used as return value if no conditions)
 *
 * @return bool True if completed, false if not, $type if conditions not set.
 * @throws dml_exception
 */
function supervideo_get_completion_state($course, $cm, $userid, $type) {
    global $DB, $PAGE;

    // No need to call debugging here. Deprecation debugging notice already being called in \completion_info::internal_get_state().

    $result = $type; // Default return value
    // Get data details.
    if (isset($PAGE->cm->id) && $PAGE->cm->id == $cm->id) {
        $data = $PAGE->activityrecord;
    } else {
        $data = $DB->get_record('data', ['id' => $cm->instance], '*', MUST_EXIST);
    }
    // If completion option is enabled, evaluate it and return true/false.
    if ($data->completionpercent) {

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

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $supervideo supervideo object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 *
 * @throws coding_exception
 */
function supervideo_view($supervideo, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $supervideo->id,
    ];

    $event = \mod_supervideo\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('supervideo', $supervideo);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Export file supervideo contents
 *
 * @param $cm
 * @param $baseurl
 *
 * @return array of file content
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function supervideo_export_contents($cm, $baseurl) {
    global $DB;

    $contents = [];
    $context = context_module::instance($cm->id);
    $supervideo = $DB->get_record('supervideo', ['id' => $cm->instance], '*', MUST_EXIST);

    $parseurl = \mod_supervideo\util\url::parse($supervideo->videourl);

    $config = get_config('supervideo');
    if ($config->showcontrols == 2) {
        $config->showcontrols = 0;
    } else if ($config->showcontrols == 3) {
        $config->showcontrols = 1;
    } else {
        $config->showcontrols = $supervideo->showcontrols;
    }
    if ($config->autoplay == 2) {
        $config->autoplay = 0;
    } else if ($config->autoplay == 3) {
        $config->autoplay = 1;
    } else {
        $config->autoplay = $supervideo->autoplay;
    }
    $config->playersize = $supervideo->playersize;
    $supervideoview = \mod_supervideo\analytics\supervideo_view::create($cm->id);
    $config->datamapa = base64_encode($supervideoview->mapa);
    $config->viewid = $supervideoview->id;
    $config->currenttime = $supervideoview->currenttime;

    if ($parseurl->videoid) {
        if ($parseurl->engine == "link") {
            $contents[] = [
                'type' => "link",
                'filename' => "link.{$parseurl->extra}",
                'filepath' => $parseurl->extra,
                'filesize' => 1,
                'fileurl' => $parseurl->videoid,
                'timecreated' => time(),
                'timemodified' => time(),
                'sortorder' => 0,
                'userid' => 0,
                'author' => '',
                'license' => json_encode($config, JSON_NUMERIC_CHECK),
            ];
            return $contents;
        }
        if ($parseurl->engine == "ottflix") {
            $contents[] = [
                'type' => 'ottflix',
                'filename' => 'ottflix.mp4',
                'filepath' => "",
                'filesize' => 1,
                'fileurl' => $parseurl->videoid,
                'timecreated' => time(),
                'timemodified' => time(),
                'sortorder' => 0,
                'userid' => 0,
                'author' => '',
                'license' => json_encode($config),
            ];
            return $contents;
        }
        if ($parseurl->engine == "resource") {
            $files = supervideo_get_area_files($context->id);
            foreach ($files as $file) {
                $path = "/{$context->id}/mod_supervideo/content/{$supervideo->id}{$file->get_filepath()}{$file->get_filename()}";
                $fullurl = moodle_url::make_file_url('/pluginfile.php', $path, false)->out();
                $file = [
                    'type' => 'file',
                    'engine' => 'resource',
                    'filename' => $file->get_filename(),
                    'filepath' => $file->get_filepath(),
                    'filesize' => $file->get_filesize(),
                    'fileurl' => $fullurl,
                    'timecreated' => $file->get_timecreated(),
                    'timemodified' => $file->get_timemodified(),
                    'sortorder' => $file->get_sortorder(),
                    'userid' => $file->get_userid(),
                    'author' => $file->get_author(),
                    'license' => json_encode($config, JSON_NUMERIC_CHECK),
                ];
                $contents[] = $file;

                return $contents;
            }
        }
        if ($parseurl->engine == "youtube") {
            $contents[] = [
                'type' => 'youtube',
                'filename' => 'youtube.mp4',
                'filepath' => "",
                'filesize' => 1,
                'fileurl' => "https://www.youtube.com/watch?v={$parseurl->videoid}",
                'timecreated' => time(),
                'timemodified' => time(),
                'sortorder' => 0,
                'userid' => 0,
                'author' => '',
                'license' => json_encode($config, JSON_NUMERIC_CHECK),
            ];
            return $contents;
        }
        if ($parseurl->engine == "google-drive") {
            $config->showmapa = false;

            $parametersdrive = implode('&amp;', [
                $supervideo->showcontrols ? 'controls=1' : 'controls=0',
                $supervideo->autoplay ? 'autoplay=1' : 'autoplay=0',
            ]);
            $url = "https://drive.google.com/file/d/{$parseurl->videoid}/preview?{$parametersdrive}";

            $contents[] = [
                'type' => 'google-drive',
                'filename' => 'google-drive.mp4',
                'filepath' => "",
                'filesize' => 1,
                'fileurl' => $url,
                'timecreated' => time(),
                'timemodified' => time(),
                'sortorder' => 0,
                'userid' => 0,
                'author' => '',
                'license' => json_encode($config, JSON_NUMERIC_CHECK),
            ];
            return $contents;
        }
        if ($parseurl->engine == "vimeo") {
            $parametersvimeo = implode('&amp;', [
                'pip=1',
                'title=0',
                'byline=0',
                $supervideo->showcontrols ? 'title=1' : 'title=0',
                $supervideo->autoplay ? 'autoplay=1' : 'autoplay=0',
                $supervideo->showcontrols ? 'controls=1' : 'controls=0',
            ]);

            if (strpos($parseurl->videoid, "?")) {
                $url = "https://player.vimeo.com/video/{$parseurl->videoid}&pip{$parametersvimeo}";
            } else {
                $url = "https://player.vimeo.com/video/{$parseurl->videoid}?pip{$parametersvimeo}";
            }

            $contents[] = [
                'type' => 'vimeo',
                'filename' => 'vimeo.mp4',
                'filepath' => "",
                'filesize' => 1,
                'fileurl' => $url,
                'timecreated' => time(),
                'timemodified' => time(),
                'sortorder' => 0,
                'userid' => 0,
                'author' => '',
                'license' => json_encode($config, JSON_NUMERIC_CHECK),
            ];
            return $contents;
        }
    }
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 * See {@link get_array_of_activities()} in course/lib.php
 *
 * @param stdClass $coursemodule
 *
 * @return cached_cm_info info
 * @throws dml_exception
 */
function supervideo_get_coursemodule_info($coursemodule) {
    global $DB;

    if (!$supervideo = $DB->get_record('supervideo', ['id' => $coursemodule->instance],
        'id, name, videourl, intro, introformat, completionpercent')) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $supervideo->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('supervideo', $supervideo, $coursemodule->id, false);
    }

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('supervideo', $supervideo, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionpercent'] = $supervideo->completionpercent;
    }

    $info->completionpassgrade = false;
    $info->downloadcontent = false;
    $info->lang = false;

    return $info;
}

/**
 * Function supervideo_get_area_files
 *
 * @param int $contextid
 *
 * @return array
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function supervideo_get_area_files($contextid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, 'mod_supervideo', 'content');

    $returnfiles = [];
    /** @var stored_file $file */
    foreach ($files as $file) {
        if ($file->get_filename() != '.') {
            $returnfiles[] = $file;
        }
    }

    return $returnfiles;
}
