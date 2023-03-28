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

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '48'), array());
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'videourl',
            get_string('videourl', 'mod_supervideo'), array('size' => '60'), array('usefilepicker' => true));
        $mform->setType('videourl', PARAM_TEXT);
        $mform->addRule('videourl', null, 'required', null, 'client');
        $mform->addHelpButton('videourl', 'videourl', 'mod_supervideo');

        $mform->addElement('html', "
                <div id='fitem_element_videofile' style='display:none'>
                    <div id='fitem_id_videofile'>
                        <div class='input-wrapper'>    
                            Ou
                            <label for='videofile_file'>
                                Selecione um Áudio MP3 ou um Vídeo MP4 do seu computador
                            </label>
                            <input id='videofile_file' type='file' name='videofile'/>
                            <span id='videofile_file-name'></span>
                        </div>
                    </div>
                </div>");

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

        // Grade Element.
        $mform->addElement('header', 'modstandardgrade', get_string('modgrade', 'grades'));

        $values = [
            0 => get_string('grade_approval_0', 'mod_supervideo'),
            1 => get_string('grade_approval_1', 'mod_supervideo'),
        ];
        $mform->addElement('select', 'grade_approval', get_string('grade_approval', 'mod_supervideo'), $values);

        $mform->addElement('select', 'gradecat', get_string('gradecategoryonmodform', 'grades'),
            grade_get_categories_menu($COURSE->id, false));
        $mform->addHelpButton('gradecat', 'gradecategoryonmodform', 'grades');
        $mform->disabledIf('gradecat', 'grade_approval', 'eq', '0');

        $mform->addElement('text', 'gradepass', get_string('gradepass', 'grades'));
        $mform->addHelpButton('gradepass', 'gradepass', 'grades');
        $mform->disabledIf('gradepass', 'grade_approval', 'eq', '0');

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

        $errors = parent::validation($data, $files);
        if (!isset($data['videourl']) || empty($data['videourl'])) {
            $errors['videourl'] = get_string('required');
        }

        $urlparse = \mod_supervideo\util\url::parse($data['videourl']);
        if ($urlparse->engine == "") {
            $errors['videourl'] = get_string('idnotfound', 'mod_supervideo');
        }

        if ($urlparse->engine == "resource") {

            if (@$_FILES['videofile']['error'] === 0) {

                $extension = pathinfo($_FILES['videofile']['name'], PATHINFO_EXTENSION);
                $extension = strtolower($extension);

                if ($extension == "mp4" || $extension == "mp3") {
                    // OK
                } else {
                    $errors['videourl'] = 'Somente arquivos MP3 e MP4 são permitidos!';
                }
            } else {
                switch ($_FILES['videofile']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $errors['videourl'] = 'Erro 1: O arquivo enviado excede o limite definido na diretiva upload_max_filesize do php.ini.';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $errors['videourl'] = 'Erro 2: O arquivo excede o limite definido em MAX_FILE_SIZE no formulário.';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errors['videourl'] = 'Erro 3: O upload do arquivo foi feito parcialmente.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $errors['videourl'] = 'Valor: 4; Nenhum arquivo foi carregado.';
                        break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                        $errors['videourl'] = 'Erro 6: Pasta temporária ausênte.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $errors['videourl'] = 'Erro 7: Falha em escrever o arquivo no HD. ' .
                            'Provavelmente o HD esteja lotado ou com falhas.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $errors['videourl'] = 'Erro 8: Uma extensão do PHP interrompeu o upload do arquivo. ' .
                            'O PHP não fornece uma maneira de determinar qual extensão causou a interrupção. ' .
                            'Examinar a lista das extensões carregadas com o phpinfo() pode ajudar.';
                        break;
                }
            }
        }


        return $errors;
    }

    /**
     * @param  array $defaultvalues
     *
     * @throws coding_exception
     * @throws file_exception
     */
    public function data_preprocessing(&$defaultvalues) {

        $videourl = optional_param("videourl", "", PARAM_TEXT);

        $urlparse = \mod_supervideo\util\url::parse($videourl);
        if ($urlparse->engine == "resource") {

            if (@$_FILES['videofile']['error'] === 0) {

                $extension = pathinfo($_FILES['videofile']['name'], PATHINFO_EXTENSION);
                $extension = strtolower($extension);

                if ($extension == "mp4" || $extension == "mp3") {
                    $fs = get_file_storage();
                    $fileinfo = [
                        'contextid' => $this->get_context()->id,
                        'component' => 'mod_supervideo',
                        'filearea' => 'video',
                        'itemid' => $this->_cm->id,
                        'filepath' => '/',
                        'filename' => 'video.mp4'
                    ];

                    $fileDelete = get_file_storage()->get_file(
                        $fileinfo['contextid'],
                        $fileinfo['component'],
                        $fileinfo['filearea'],
                        $fileinfo['itemid'],
                        $fileinfo['filepath'],
                        $fileinfo['filename']);
                    if ($fileDelete) {
                        $fileDelete->delete();
                    }

                    $fs->create_file_from_string($fileinfo, file_get_contents($_FILES['videofile']['tmp_name']));
                }
            }
        }
    }
}

echo '<pre>';
print_r($urlparse);
echo '</pre>';