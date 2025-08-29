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
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . "/course/moodleform_mod.php");

use mod_supervideo\form\supervideo_filepicker;

/**
 * class mod_supervideo_mod_for
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 */
class mod_supervideo_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     *
     * @throws Exception
     */
    public function definition() {
        global $DB, $CFG, $PAGE, $COURSE, $USER;

        $supervideo = null;
        if ($this->_cm && $this->_cm->instance) {
            $supervideo = $DB->get_record("supervideo", ["id" => $this->_cm->instance]);
        }

        $mform = $this->_form;
        $mform->updateAttributes(["enctype" => "multipart/form-data"]);

        $mform->addElement("header", "general", get_string("general", "form"));

        $mform->addElement("text", "name", get_string("name"), ["size" => "48"], []);
        $mform->setType("name", !empty($CFG->formatstringstriptags) ? PARAM_TEXT : PARAM_CLEANHTML);
        $mform->addRule("name", null, "required", null, "client");
        $mform->addRule("name", get_string("maximumchars", "", 255), "maxlength", 255, "client");

        // Origem.
        $origems = ["upload", "ottflix", "pandavideo", "youtube", "vimeo", "drive", "link"];
        if ($supervideo && in_array($supervideo->origem, $origems)) {
            $mform->addElement("hidden", "origem", $supervideo->origem);
            $mform->setType("origem", PARAM_TEXT);

            if ($supervideo->origem == "upload") {
                $mform->addElement("hidden", "videourl", $supervideo->videourl);
                $mform->setType("videourl", PARAM_TEXT);
            } else if ($supervideo->origem == "pandavideo" || $supervideo->origem == "ottflix") {
                supervideo_filepicker::add_form($mform, $supervideo->origem, "videourl");
            } else {
                $title = get_string("origem_{$supervideo->origem}", "mod_supervideo");
                $mform->addElement("text", "videourl", $title, ["size" => "60"], []);
                $mform->setType("videourl", PARAM_TEXT);
                $mform->addHelpButton("videourl", "origem_{$supervideo->origem}", "mod_supervideo");
            }
        } else {
            $origemselect = [];
            foreach ($origems as $origem) {
                $origemselect[$origem] = get_string("origem_{$origem}", "mod_supervideo");
            }

            $mform->addElement("select", "origem", get_string("origem_name", "mod_supervideo"), $origemselect);

            foreach ($origems as $origem) {
                if ($origem == "upload") {
                    continue;
                }
                if ($supervideo && $origem != $supervideo->origem) {
                    continue;
                }

                if ($origem == "pandavideo" || $origem == "ottflix") {
                    supervideo_filepicker::add_form($mform, $origem, "videourl_{$origem}");
                } else {
                    $mform->addElement(
                        "text",
                        "videourl_{$origem}",
                        get_string("origem_{$origem}", "mod_supervideo"),
                        ["size" => "60"], []
                    );
                    $mform->setType("videourl_{$origem}", PARAM_TEXT);
                    $mform->addHelpButton("videourl_{$origem}", "origem_{$origem}", "mod_supervideo");
                }
                if (!$supervideo) {
                    $mform->hideIf("videourl_{$origem}", "origem", "neq", $origem);
                }
            }
        }

        // Ottflix IA.
        $mform->addElement(
            "static",
            "ottflix_ia_text",
            get_string("ottflix_ia", "mod_supervideo"),
            get_string("ottflix_ia_desc", "mod_supervideo")
        );
        $mform->hideIf("ottflix_ia_text", "origem", "eq", "upload");
        $mform->hideIf("ottflix_ia_text", "origem", "eq", "youtube");
        $mform->hideIf("ottflix_ia_text", "origem", "eq", "vimeo");
        $mform->hideIf("ottflix_ia_text", "origem", "eq", "drive");
        $mform->hideIf("ottflix_ia_text", "origem", "eq", "pandavideo");
        $mform->hideIf("ottflix_ia_text", "origem", "eq", "link");

        $options = [
            "InteractiveVideo" => get_string("ottflix_ia_interactivevideo", "mod_supervideo"),
            "AdvancedText"     => get_string("ottflix_ia_advancedtext", "mod_supervideo"),
            "Accordion"        => get_string("ottflix_ia_accordion", "mod_supervideo"),
            "Dialogcards"      => get_string("ottflix_ia_dialogcards", "mod_supervideo"),
            "QuestionSet"      => get_string("ottflix_ia_questionset", "mod_supervideo"),
            "DragText"         => get_string("ottflix_ia_dragtext", "mod_supervideo"),
        ];
        $attributes = ["multiple" => "multiple", "size" => 6];
        $mform->addElement("select", "ottflix_ia", "", $options, $attributes);
        $mform->setType("ottflix_ia", PARAM_TEXT);
        $mform->hideIf("ottflix_ia", "origem", "eq", "upload");
        $mform->hideIf("ottflix_ia", "origem", "eq", "youtube");
        $mform->hideIf("ottflix_ia", "origem", "eq", "vimeo");
        $mform->hideIf("ottflix_ia", "origem", "eq", "drive");
        $mform->hideIf("ottflix_ia", "origem", "eq", "pandavideo");
        $mform->hideIf("ottflix_ia", "origem", "eq", "link");

        // Upload.
        if (!$supervideo || $supervideo->origem == "upload" || !in_array($supervideo->origem, $origems)) {
            $filemanageroptions = [
                "accepted_types" => [".mp3", ".mp4", ".webm", ".m4v", ".mov", ".aac", ".m4a"],
                "maxbytes" => -1,
                // Retornar "maxfiles" => 1,.
            ];
            $mform->addElement("filepicker", "videofile", get_string("videofile", "mod_supervideo"), null, $filemanageroptions);
            $mform->addHelpButton("videofile", "videofile", "mod_supervideo");

            if (!$supervideo || !in_array($supervideo->origem, $origems)) {
                $mform->hideIf("videofile", "origem", "neq", "upload");
            }
        }

        // Player size.
        $sizeoptions = [
            "pdf" => "PDF / DOC / XLS",
            "4x3" => "Video 4x3",
            "16x9" => "Video 16x9",
        ];
        if ($supervideo && $supervideo->playersize != 0) {
            if (!isset($sizeoptions[$supervideo->playersize])) {
                $sizeoptions[$supervideo->playersize] = $supervideo->playersize;
            }
        }
        $mform->addElement("select", "playersize", get_string("playersize", "mod_supervideo"), $sizeoptions);
        $mform->setDefault("playersize", 1);
        $mform->setType("playersize", PARAM_TEXT);
        $mform->hideIf("playersize", "origem", "eq", "vimeo");
        $mform->hideIf("playersize", "origem", "eq", "youtube");
        $mform->hideIf("playersize", "origem", "eq", "ottflix");
        $mform->hideIf("playersize", "origem", "eq", "pandavideo");
        $mform->hideIf("playersize", "origem", "eq", "link");

        $config = get_config("supervideo");

        if ($config->showcontrols <= 1) {
            $mform->addElement("advcheckbox", "showcontrols", get_string("showcontrols_desc", "mod_supervideo"));
            $mform->setDefault("showcontrols", $config->showcontrols);
            $mform->hideIf("showcontrols", "origem", "eq", "ottflix");
            $mform->hideIf("showcontrols", "origem", "eq", "pandavideo");
        }

        if ($config->autoplay <= 1) {
            $mform->addElement("advcheckbox", "autoplay", get_string("autoplay_desc", "mod_supervideo"));
            $mform->setDefault("autoplay", $config->autoplay);
            $mform->hideIf("autoplay", "origem", "eq", "ottflix");
            $mform->hideIf("autoplay", "origem", "eq", "pandavideo");
        }

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Grade Element.
        $mform->addElement("header", "modstandardgrade", get_string("modgrade", "grades"));

        $values = [
            0 => get_string("grade_approval_0", "mod_supervideo"),
            1 => get_string("grade_approval_1", "mod_supervideo"),
        ];
        $mform->addElement("select", "grade_approval", get_string("grade_approval", "mod_supervideo"), $values);

        $mform->addElement(
            "select",
            "gradecat",
            get_string("gradecategoryonmodform", "grades"),
            grade_get_categories_menu($COURSE->id, false)
        );
        $mform->addHelpButton("gradecat", "gradecategoryonmodform", "grades");
        $mform->hideIf("gradecat", "grade_approval", "eq", "0");

        $mform->addElement("text", "gradepass", get_string("gradepass", "grades"), ["size" => 4]);
        $mform->addHelpButton("gradepass", "gradepass", "grades");
        $mform->setType("gradepass", PARAM_INT);
        $mform->hideIf("gradepass", "grade_approval", "eq", "0");

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        $mform->hideIf("completionusegrade", "grade_approval", "eq", "0");
        $mform->hideIf("completionpassgrade", "grade_approval", "eq", "0");

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

        $courseinfo = false;
        if (!($this->_cm && $this->_cm->instance)) {
            $course = $this->optional_param("course", 0, PARAM_INT);
            $section = $this->optional_param("section", false, PARAM_INT);
            if ($course && $section !== false) {
                $courseinfo = "course={$course}&section={$section}&token=" . sesskey();
            }
        }

        $config = get_config("supervideo");
        $PAGE->requires->strings_for_js(["record_kapture"], "supervideo");
        $PAGE->requires->js_call_amd(
            "mod_supervideo/mod_form",
            "init",
            [$USER->lang, $courseinfo]
        );
    }

    /**
     * Set up the completion checkbox which is not part of standard data.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid("videofile");
            if (isset($defaultvalues["id"])) {
                $id = intval($defaultvalues["id"]);
                file_prepare_draft_area($draftitemid, $this->context->id, "mod_supervideo", "content", $id, ["subdirs" => true]);
                $defaultvalues["videofile"] = $draftitemid;
            }
        }

        $defaultvalues["completionpercentenabled"] = !empty($defaultvalues["completionpercent"]) ? 1 : 0;
        if (empty($defaultvalues["completionpercent"])) {
            $defaultvalues["completionpercent"] = 1;
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
     * add_completion_rules_oold function
     *
     * @return array
     * @throws Exception
     */
    public function add_completion_rules_oold() {
        $mform = &$this->_form;

        $mform->addElement("text", "completionpercent", get_string("completionpercent", "mod_supervideo"), ["size" => 4]);
        $mform->addHelpButton("completionpercent", "completionpercent", "mod_supervideo");
        $mform->setType("completionpercent", PARAM_INT);

        return ["completionpercent"];
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     *
     * @return array Array of string IDs of added items, empty array if none
     * @throws Exception
     */
    public function add_completion_rules() {
        $mform = &$this->_form;
        $group = [
            $mform->createElement(
                "checkbox",
                "completionpercentenabled",
                "",
                get_string("completionpercent_label", "mod_supervideo")
            ),
            $mform->createElement(
                "text",
                "completionpercent",
                get_string("completionpercent_label", "mod_supervideo"),
                ["size" => "2"]
            ),
            $mform->createElement("html", "%"),
        ];

        $mform->addGroup(
            $group,
            "completionpercentgroup",
            get_string("completionpercent", "mod_supervideo"),
            [" "],
            false
        );
        $mform->disabledIf("completionpercent", "completionpercentenabled", "notchecked");
        $mform->setDefault("completionpercent", 0);
        $mform->setType("completionpercent", PARAM_INT);
        return ["completionpercentgroup"];
    }

    /**
     * completion_rule_enabled function
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return ($data["completionpercent"] > 0);
    }

    /**
     * validation function
     *
     * @param $data
     * @param $files
     * @return array
     * @throws Exception
     */
    public function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        if (isset($data["completionpercent"]) && $data["completionpercent"] != "") {
            $data["completionpercent"] = intval($data["completionpercent"]);
            if ($data["completionpercent"] < 1) {
                $data["completionpercent"] = "";
            }
            if ($data["completionpercent"] > 100) {
                $errors["completionpercent"] = get_string("completionpercent_error", "mod_supervideo");
            }
        }

        if (isset($data["gradepass"]) && $data["gradepass"] != "") {
            $data["gradepass"] = intval($data["gradepass"]);
            if ($data["gradepass"] < 1) {
                $data["gradepass"] = "";
            }
            if ($data["gradepass"] > 100) {
                $errors["gradepass"] = get_string("completionpercent_error", "mod_supervideo");
            }
        }
        $origem = $data["origem"];
        if ($origem == "upload") {
            $usercontext = context_user::instance($USER->id);
            $fs = get_file_storage();
            if (!$videofile = $fs->get_area_files($usercontext->id, "user", "draft", $data["videofile"], "sortorder, id", false)) {
                $errors["videofile"] = get_string("required");
                return $errors;
            }
        } else {
            if ($data["instance"]) {
                if (!isset($data["videourl"]) || empty($data["videourl"])) {
                    $errors["videourl"] = get_string("required");
                }
            } else {
                if (!isset($data["videourl_{$origem}"]) || empty($data["videourl_{$origem}"])) {
                    $errors["videourl_{$origem}"] = get_string("required");
                }
            }
        }
        return $errors;
    }
}
