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
 * Class supervideo_filepicker
 *
 * @package   mod_supervideo
 * @copyright 2025 Eduardo Kraus {@link https://www.eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;

use core_filetypes;
use Exception;
use MoodleQuickForm;
use renderer_base;
use templatable;
use file_picker;
use HTML_QuickForm_input;
use stdClass;
use templatable_form_element;

require_once("HTML/QuickForm/button.php");
require_once("{$CFG->dirroot}/repository/lib.php");
require_once("{$CFG->dirroot}/lib/form/templatable_form_element.php");

/**
 * Class supervideo_filepicker
 */
class supervideo_filepicker extends HTML_QuickForm_input implements templatable {
    use templatable_form_element {
        export_for_template as export_for_template_base;
    }

    public $_helpbutton; // phpcs:disable

    /**
     * add_form
     *
     * @param MoodleQuickForm $mform
     * @param string $origem
     * @param string $elementname
     * @return void
     * @throws Exception
     */
    public static function add_form(MoodleQuickForm $mform, string $origem, $elementname) {
        global $CFG;

        static $loaded = false;
        if (!$loaded) {
            // Register Element Type supervideo_filepicker.
            MoodleQuickForm::registerElementType(
                "supervideo_filepicker",
                "{$CFG->dirroot}/mod/supervideo/classes/form/supervideo_filepicker.php",
                self::class
            );
            $loaded = true;
        }

        $filepickeroptions = [
            "accepted_types" => ["video/{$origem}"],
            "maxbytes" => -1,
            "return_types" => 1,
        ];
        $title = get_string("origem_{$origem}", "mod_supervideo");
        $mform->addElement("supervideo_filepicker", $elementname, $title, null, $filepickeroptions);
        $mform->addHelpButton($elementname, "origem_{$origem}", "mod_supervideo");
    }

    /** @var string html for help button, if empty then no help will icon will be dispalyed. */
    public $helpbutton = "";

    /** @var array options provided to initalize filemanager */
    protected $options = [
        "accepted_types" => "*",
        "return_types" => FILE_REFERENCE,
    ];

    /**
     * Constructor
     *
     * @param string $elementname (optional) name of the filepicker
     * @param string $elementlabel (optional) filepicker label
     * @param array $attributes (optional) Either a typical HTML attribute string
     *              or an associative array
     * @param array $options set of options to initalize filepicker
     */
    public function __construct($elementname = null, $elementlabel = null, $attributes = null, $options = null) {
        $options = (array)$options;
        foreach ($options as $name => $value) {
            if (array_key_exists($name, $this->options)) {
                $this->options[$name] = $value;
            }
        }
        $this->_type = "filepicker";
        parent::__construct($elementname, $elementlabel, $attributes);
    }

    /**
     * Returns html for help button.
     *
     * @return string html for help button
     */
    public function getHelpButton() { // phpcs:disable
        return $this->helpbutton;
    }

    /**
     * Returns type of filepicker element
     *
     * @return string
     */
    public function getElementTemplateType() { // phpcs:disable
        if ($this->_flagFrozen) {
            return "nodisplay";
        } else {
            return "default";
        }
    }

    /**
     * Returns HTML for filepicker form element.
     *
     * @return string
     * @throws Exception
     */
    public function toHtml() { // phpcs:disable
        global $DB, $CFG, $PAGE;

        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        }

        $origem = explode("/", $this->options["accepted_types"][0])[1];
        $filerepository = "{$CFG->dirroot}/repository/{$origem}/lib.php";
        if (file_exists($filerepository)) {
            $fp = new file_picker((object)[
                "accepted_types" => $this->options["accepted_types"],
                "return_types" => $this->options["return_types"],
                "itemid" => $this->getValue(),
                "context" => $PAGE->context,
                "buttonname" => $this->_attributes["name"] . "choose",
                "elementid" => $this->_attributes["id"],
            ]);

            $straddfile = get_string("openpicker", "repository");
            $buttonname = "";
            if ($fp->options->buttonname) {
                $buttonname = " name=\"{$fp->options->buttonname}\"";
            }

            $repository = $DB->get_record("repository", ["type" => $origem]);
            $message = "";
            if (!$repository || !$repository->visible) {
                $message = get_string("repository_{$origem}_disable", "mod_supervideo", $CFG->wwwroot);
            }

            $html = $this->_getTabs();

            $html .= <<<EOD
                <div class="col-md-9 d-flex gap-3 flex-wrap align-items-start felement">
                    <input type="text" class="form-control" size="48"
                           name="{$this->_attributes["name"]}"
                           id="{$fp->options->elementid}"
                           value="{$this->getValue()}"/>
                    <input type="button" class="btn btn-primary fp-btn-choose me-3 ml-3"
                           id="filepicker-button-{$fp->options->elementid}"
                           value="{$straddfile}" style="display:none"
                           {$buttonname}/>
                </div>
                {$message}
            EOD;
            $module = [
                "name" => "supervideo_filepicker",
                "fullpath" => "/mod/supervideo/classes/form/supervideo_filepicker.js",
                "requires" => [
                    "core_filepicker",
                    "node",
                    "node-event-simulate",
                ],
            ];
            $PAGE->requires->js_init_call("M.supervideo_filepicker.init", [$fp->options], true, $module);
        } else {
            $message = get_string("repository_{$origem}_notinstall", "mod_supervideo");
            $html = <<<EOD
                <div class="col-md-9 d-flex gap-3 flex-wrap align-items-start felement">
                    <input type="text" class="form-control" size="48"
                           name="{$this->_attributes["name"]}"
                           id="{$this->_attributes["id"]}"
                           value="{$this->getValue()}"/>
                </div>
                {$message}
            EOD;
        }

        return $html;
    }

    /**
     * export_for_template
     *
     * @param renderer_base $output
     * @return array|stdClass
     * @throws Exception
     */
    public function export_for_template(renderer_base $output) {
        $context = $this->export_for_template_base($output);
        $context["html"] = $this->toHtml();
        return $context;
    }
}
