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
 * form file
 *
 * @package    mod_supervideo
 * @copyright  2023 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * class mod_supervideo_mod_for
 *
 * @package   mod_supervideo
 * @copyright 2023 Eduardo kraus (http://eduardokraus.com)
 * @license   https://www.eduardokraus.com/
 */
class mod_supervideo_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $DB, $CFG, $PAGE, $COURSE;

        $PAGE->requires->css('/mod/supervideo/style.css');

        $mform = $this->_form;
        $mform->updateAttributes(array('enctype' => 'multipart/form-data'));

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '48'), array());
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'videourl',
            get_string('videourl', 'mod_supervideo'), array('size' => '60'), []);
        $mform->setType('videourl', PARAM_TEXT);
        $mform->addRule('videourl', null, 'required', null, 'client');
        $mform->addHelpButton('videourl', 'videourl', 'mod_supervideo');

        $filemanageroptions = [
            'accepted_types' => ['.mp3', '.mp4'],
            'maxbytes' => 0
        ];
        $mform->addElement('filepicker', 'videofile', get_string('videofile', 'mod_supervideo'), null, $filemanageroptions);
        $mform->addHelpButton('videofile', 'videofile', 'mod_supervideo');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        $sizeoptions = array(
            0 => 'Vídeo ED (4x3)',
            1 => 'Vídeo HD (16x9)',

            5 => 'PDF / DOC / XLS',
            6 => 'Vídeo 4x3',
            7 => 'Vídeo 16x9',

        );
        $mform->addElement('select', 'videosize', get_string('video_size', 'mod_supervideo'), $sizeoptions);
        $mform->setDefault('videosize', 1);

        $config = get_config('supervideo');

        if ($config->showrel <= 1) {
            $mform->addElement('advcheckbox', 'showrel', get_string('showrel_desc', 'mod_supervideo'));
            $mform->setDefault('showrel', $config->showrel);
        }

        if ($config->showcontrols <= 1) {
            $mform->addElement('advcheckbox', 'showcontrols', get_string('showcontrols_desc', 'mod_supervideo'));
            $mform->setDefault('showcontrols', $config->showcontrols);
        }

        if ($config->showinfo <= 1) {
            $mform->addElement('advcheckbox', 'showinfo', get_string('showinfo_desc', 'mod_supervideo'));
            $mform->setDefault('showinfo', $config->showinfo);
        }

        if ($config->autoplay <= 1) {
            $mform->addElement('advcheckbox', 'autoplay', get_string('autoplay_desc', 'mod_supervideo'));
            $mform->setDefault('autoplay', $config->autoplay);
        }

        // Grade Element.
        //$this->standard_grading_coursemodule_elements();
        $mform->addElement('header', 'modstandardgrade', get_string('modgrade', 'grades'));

        $values = [
            0 => get_string('grade_approval_0', 'mod_supervideo'),
            1 => get_string('grade_approval_1', 'mod_supervideo'),
        ];
        $mform->addElement('select', 'grade_approval', get_string('grade_approval', 'mod_supervideo'), $values);

        $mform->addElement('select', 'gradecat', get_string('gradecategoryonmodform', 'grades'),
            grade_get_categories_menu($COURSE->id, false));
        $mform->addHelpButton('gradecat', 'gradecategoryonmodform', 'grades');
        $mform->hideIf('gradecat', 'grade_approval', 'eq', '0');

        $mform->addElement('text', 'gradepass', get_string('gradepass', 'grades'), ['size' => 4]);
        $mform->addHelpButton('gradepass', 'gradepass', 'grades');
        $mform->setType('gradepass', PARAM_INT);
        $mform->hideIf('gradepass', 'grade_approval', 'eq', '0');

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        $mform->hideIf('completionusegrade', 'grade_approval', 'eq', '0');
        $mform->hideIf('completionpassgrade', 'grade_approval', 'eq', '0');

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

        $engine = "";
        if ($this->_cm && $this->_cm->instance) {
            $supervideo = $DB->get_record("supervideo", ["id" => $this->_cm->instance]);
            $urlparse = \mod_supervideo\util\url::parse($supervideo->videourl);
            $engine = $urlparse->engine;
        }
        $PAGE->requires->js_call_amd('mod_supervideo/mod_form', 'init', [$engine]);
    }

    /**
     * Set up the completion checkbox which is not part of standard data.
     *
     * @param array $defaultvalues
     *
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        $draftitemid = file_get_submitted_draft_itemid('videofile');
        file_prepare_draft_area($draftitemid, $this->context->id, 'mod_supervideo', 'content', $defaultvalues['id']);
        $defaultvalues['videofile'] = $draftitemid;

        $defaultvalues['completionpercentenabled'] = !empty($defaultvalues['completionpercent']) ? 1 : 0;
        if (empty($defaultvalues['completionpercent'])) {
            $defaultvalues['completionpercent'] = 1;
        }
    }

    /**
     * Allows modules to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionpercentenabled) || !$autocompletion) {
                $data->completionpercent = 0;
            }
        }
    }

    /**
     * @return array
     * @throws coding_exception
     */
    public function add_completion_rules_oold() {
        $mform =& $this->_form;

        $mform->addElement('text', 'completionpercent', get_string('completionpercent', 'mod_supervideo'), ['size' => 4]);
        $mform->addHelpButton('completionpercent', 'completionpercent', 'mod_supervideo');
        $mform->setType('completionpercent', PARAM_INT);

        return ['completionpercent'];
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     * @throws coding_exception
     */
    public function add_completion_rules() {
        $mform = &$this->_form;
        $group = [
            $mform->createElement('checkbox', 'completionpercentenabled', '',
                get_string('completionpercent_label', 'mod_supervideo')),
            $mform->createElement('text', 'completionpercent',
                get_string('completionpercent_label', 'mod_supervideo'), array('size' => '2')),
            $mform->createElement('html', '%'),
        ];

        $mform->addGroup($group, 'completionpercentgroup', get_string('completionpercent', 'mod_supervideo'),
            [' '], false);
        $mform->disabledIf('completionpercent', 'completionpercentenabled', 'notchecked');
        $mform->setDefault('completionpercent', 0);
        $mform->setType('completionpercent', PARAM_INT);
        return ['completionpercentgroup'];
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return ($data['completionpercent'] > 0);
    }


    /**
     * @param $data
     * @param $files
     *
     * @return array
     * @throws coding_exception
     */
    public function validation($data, $files) {

        $errors = parent::validation($data, $files);
        if (!isset($data['videourl']) || empty($data['videourl'])) {
            $errors['videourl'] = get_string('required');
        }

        if (isset($data['completionpercent']) && $data['completionpercent'] != '') {
            $data['completionpercent'] = intval($data['completionpercent']);
            if ($data['completionpercent'] < 1) {
                $data['completionpercent'] = "";
            }
            if ($data['completionpercent'] > 100) {
                $errors['completionpercent'] = get_string('completionpercent_error', 'mod_supervideo');
            }
        }

        if (isset($data['gradepass']) && $data['gradepass'] != '') {
            $data['gradepass'] = intval($data['gradepass']);
            if ($data['gradepass'] < 1) {
                $data['gradepass'] = "";
            }
            if ($data['gradepass'] > 100) {
                $errors['gradepass'] = get_string('completionpercent_error', 'mod_supervideo');
            }
        }

        $urlparse = \mod_supervideo\util\url::parse($data['videourl']);
        if ($urlparse->engine == "") {
            $errors['videourl'] = get_string('idnotfound', 'mod_supervideo');
        }

        if ($urlparse->engine == "resource") {

            if (empty($data['videofile'])) {
                // Field missing.
                $errors['videofile'] = get_string('required');
            } else {
                $files = $this->get_draft_files('videofile');
                if ($files && count($files) < 1) {
                    // No file uploaded.
                    $errors['videofile'] = get_string('required');
                }
            }
        }

        return $errors;
    }
}
