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
 * The Web service script that is called from the filepicker front end
 *
 * @since Moodle 2.0
 * @package    repository
 * @copyright  2009 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../lib/filelib.php');
require_once(__DIR__ . '/../../repository/lib.php');

// Parameters
$action = required_param('action', PARAM_ALPHA);
$saveas_filename = optional_param('title', '', PARAM_FILE);     // save as file name

$context = context_user::instance($USER->id);
$PAGE->set_context($context);

if (empty($_POST) && !empty($action)) {
    $err = (object)["error" => get_string('errorpostmaxsize', 'repository')];
    die(json_encode($err));
}

ajax_capture_output();
$repo_user = $DB->get_record('repository', ['type' => 'upload']);
/** @var repository_upload $repo */
$repo = repository::get_repository_by_id($repo_user->id, $context->id, ['ajax' => true, 'mimetypes' => '*']);

switch ($action) {
    case 'upload':
        $result = $repo->upload($saveas_filename, -1);
        echo json_encode($result);
        break;
    case 'list':

        $itemid = optional_param("itemid", 13, PARAM_INT);

        $fs = get_file_storage();
        $draftcontext = context_user::instance($USER->id);
        $files = $fs->get_area_files($context->id, 'user', 'draft', $itemid, $itemid, false);

        $returnFiles = [];
        /** @var stored_file $file */
        foreach ($files as $file) {
            if (strpos($file->get_filepath(), "kapture")) {
                $path = "/{$context->id}/mod_supervideo/{$file->get_filearea()}{$file->get_filepath()}{$file->get_itemid()}/{$file->get_filename()}";

                $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                $icon = "";
                switch ($extension) {
                    case 'doc':
                    case 'docx':
                        $icon = "{$CFG->wwwroot}/mod/supervideo/vendor/kapture/img/icons/types/docx_icon.svg";
                        break;
                    case 'xls':
                    case 'xlsx':
                        $icon = "{$CFG->wwwroot}/mod/supervideo/vendor/kapture/img/icons/types/xlsx_icon.svg";
                        break;
                    case 'ppt':
                    case 'pptx':
                        $icon = "{$CFG->wwwroot}/mod/supervideo/vendor/kapture/img/icons/types/pptx_icon.svg";
                        break;
                }

                $returnFiles[] = [
                    "filename" => $file->get_filename(),
                    "titulo" => $file->get_filename(),
                    "image" => $icon,
                    "file" => moodle_url::make_file_url('/pluginfile.php', $path, false)->out()
                ];
            }
        }

        echo json_encode(['slides' => $returnFiles]);
        break;
}
