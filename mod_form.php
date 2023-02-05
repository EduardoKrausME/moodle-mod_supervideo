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

defined('MOODLE_INTERNAL') || die();

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
        global $CFG;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '48'), array());
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'videourl',
            get_string('videourl', 'supervideo'), array('size' => '60'), array('usefilepicker' => true));
        $mform->setType('videourl', PARAM_TEXT);
        $mform->addRule('videourl', null, 'required', null, 'client');
        $mform->addHelpButton('videourl', 'videourl', 'mod_supervideo');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        $config = get_config('supervideo');

        $mform->addElement('advcheckbox', 'showrel', get_string('showrel_desc', 'supervideo'));
        $mform->setDefault('showrel', $config->showrel);

        $mform->addElement('advcheckbox', 'showcontrols', get_string('showcontrols_desc', 'supervideo'));
        $mform->setDefault('showcontrols', $config->showcontrols);

        $mform->addElement('advcheckbox', 'showshowinfo', get_string('showshowinfo_desc', 'supervideo'));
        $mform->setDefault('showshowinfo', $config->showshowinfo);

        $mform->addElement('advcheckbox', 'autoplay', get_string('autoplay_desc', 'supervideo'));
        $mform->setDefault('autoplay', $config->autoplay);

        $sizeoptions = array(
            0 => 'ED (3x4)',
            1 => 'HD (16x9)',
            2 => 'HD (16x10)'
        );
        $mform->addElement('select', 'videosize', get_string('video_size', 'supervideo'), $sizeoptions);
        $mform->setType('videosize', PARAM_INT);
        $mform->setDefault('videosize', 1);

        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * function validation
     *
     * @param stdClass $data
     * @param stdClass $files
     *
     * @return mixed
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

}
