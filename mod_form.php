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


        $filemanager_options = [
            'accepted_types' => ['.mp3', '.mp4'],
            'maxbytes' => 0
        ];
        $mform->addElement('filepicker', 'videofile', get_string('videofile', 'mod_supervideo'), null, $filemanager_options);
        $mform->addHelpButton('videofile', 'videofile', 'mod_supervideo');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        $config = get_config('supervideo');

        $sizeoptions = array(
            0 => 'Vídeo ED (4x3)',
            1 => 'Vídeo HD (16x9)',

            5 => 'PDF / DOC / XLS',
            6 => 'Vídeo 4x3',
            7 => 'Vídeo 16x9',

        );
        $mform->addElement('select', 'videosize', get_string('video_size', 'mod_supervideo'), $sizeoptions);
        $mform->setType('videosize', PARAM_INT);
        $mform->setDefault('videosize', 1);

        $mform->addElement('advcheckbox', 'showrel', get_string('showrel_desc', 'mod_supervideo'));
        $mform->setDefault('showrel', $config->showrel);

        $mform->addElement('advcheckbox', 'showcontrols', get_string('showcontrols_desc', 'mod_supervideo'));
        $mform->setDefault('showcontrols', $config->showcontrols);

        $mform->addElement('advcheckbox', 'showshowinfo', get_string('showshowinfo_desc', 'mod_supervideo'));
        $mform->setDefault('showshowinfo', $config->showshowinfo);

        $mform->addElement('advcheckbox', 'autoplay', get_string('autoplay_desc', 'mod_supervideo'));
        $mform->setDefault('autoplay', $config->autoplay);

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

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

    function data_preprocessing(&$default_values) {
        $draftitemid = file_get_submitted_draft_itemid('videofile');
        file_prepare_draft_area($draftitemid, $this->context->id, 'mod_supervideo', 'content', 0);
        $defaultvalues['videofile'] = $draftitemid;
    }

    function definition_after_data() {
        if ($this->current->instance) {
            // supervideo not migrated yet
            return;
        }

        parent::definition_after_data();
    }

    /**
     * @return array
     * @throws coding_exception
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('text', 'complet_percent', get_string('complet_percent', 'mod_supervideo'), ['size' => 4]);
        $mform->addHelpButton('complet_percent', 'complet_percent', 'mod_supervideo');
        $mform->setType('complet_percent', PARAM_INT);

        return ['complet_percent'];

    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return $data['complet_percent'];
    }


    /**
     * @param $data
     * @param $files
     *
     * @return array
     * @throws coding_exception
     */
    public function validation($data, $files) {
        global $CFG;

        //        echo '<pre>';
        //        print_r($data);
        //        echo '</pre>';die();

        $errors = parent::validation($data, $files);
        if (!isset($data['videourl']) || empty($data['videourl'])) {
            $errors['videourl'] = get_string('required');
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
                if (count($files) < 1) {
                    // No file uploaded.
                    $errors['videofile'] = get_string('required');
                } else {

                }
            }
        }

        return $errors;
    }
}
