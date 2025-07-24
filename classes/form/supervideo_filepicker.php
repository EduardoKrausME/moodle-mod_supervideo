<?php

namespace mod_supervideo\form;

global $CFG;

use context_user;
use Exception;
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
     * @param string $elementName (optional) name of the filepicker
     * @param string $elementLabel (optional) filepicker label
     * @param array $attributes (optional) Either a typical HTML attribute string
     *              or an associative array
     * @param array $options set of options to initalize filepicker
     */
    public function __construct($elementName = null, $elementLabel = null, $attributes = null, $options = null) {
        $options = (array)$options;
        foreach ($options as $name => $value) {
            if (array_key_exists($name, $this->options)) {
                $this->options[$name] = $value;
            }
        }
        $this->_type = "filepicker";
        parent::__construct($elementName, $elementLabel, $attributes);
    }

    /**
     * Returns html for help button.
     *
     * @return string html for help button
     */
    function getHelpButton() {
        return $this->helpbutton;
    }

    /**
     * Returns type of filepicker element
     *
     * @return string
     */
    function getElementTemplateType() {
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
    function toHtml() {
        global $PAGE;

        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        }

        $args = new stdClass();
        // need these three to filter repositories list
        $args->accepted_types = $this->options["accepted_types"] ? $this->options["accepted_types"] : "*";
        $args->return_types = $this->options["return_types"];
        $args->itemid = $this->getValue();
        $args->context = $PAGE->context;
        $args->buttonname = $this->_attributes["name"] . "choose";
        $args->elementid = $this->_attributes["id"];

        $html = $this->_getTabs();
        $fp = new file_picker($args);
        $options = $fp->options;

        $straddfile = get_string("openpicker", "repository");
        $buttonname = "";
        if ($options->buttonname) {
            $buttonname = " name=\"{$options->buttonname}\"";
        }
        $html .= <<<EOD
            <div class="col-md-9 d-flex flex-wrap align-items-start felement">
                <input type="text" class="form-control" size="48"
                       name="{$this->_attributes["name"]}"
                       id="{$options->elementid}"
                       value="{$this->getValue()}"/>
                <input type="button" class="btn btn-primary fp-btn-choose me-3 ml-3"
                       id="filepicker-button-{$options->elementid}"
                       value="{$straddfile}" style="display:none"
                       {$buttonname}/>
            </div>
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
