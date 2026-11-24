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
 * Moodle form fields for caption tracks.
 *
 * @package mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\form;

use mod_supervideo\caption\repository;
use mod_supervideo\caption\validator;

/**
 * Add and prepare bounded, native Moodle caption fields.
 */
final class caption_fields {
    /**
     * Add rows, using a native no-submit button to reveal additional fields.
     *
     * @param object $form MoodleQuickForm.
     * @param int $count Number of rows.
     */
    public static function add($form, int $count): void {
        $form->addElement("header", "captionheader", get_string("captionheader", "mod_supervideo"));
        $form->addElement("static", "captionnotice", "", get_string("captionnotice", "mod_supervideo"));
        $form->addElement("hidden", "captionform", 1);
        $form->setType("captionform", PARAM_INT);
        $form->setConstant("captionform", 1);
        $form->addElement("hidden", "captioncount", $count);
        $form->setType("captioncount", PARAM_INT);
        $form->setConstant("captioncount", $count);
        $form->addElement("selectyesno", "transcriptenabled", get_string("transcriptenabled", "mod_supervideo"));
        $form->addElement("selectyesno", "transcriptdownload", get_string("transcriptdownload", "mod_supervideo"));
        $form->disabledIf("transcriptdownload", "transcriptenabled", "eq", 0);
        for ($index = 0; $index < $count; $index++) {
            $form->addElement("static", "captionheading[$index]", get_string("captionrow", "mod_supervideo", $index + 1));
            $form->addElement("hidden", "captionid[$index]", 0);
            $form->setType("captionid[$index]", PARAM_INT);
            $form->addElement("select", "captionsource[$index]", get_string("captionsource", "mod_supervideo"), [
                "" => get_string("none"),
                "file" => get_string("captionfile", "mod_supervideo"),
                "url" => get_string("captionurl", "mod_supervideo"),
            ]);
            $form->addElement("text", "captionlabel[$index]", get_string("captionlabel", "mod_supervideo"), ["maxlength" => 255]);
            $form->setType("captionlabel[$index]", PARAM_TEXT);
            $form->addElement(
                "text", "captionlanguage[$index]", get_string("captionlanguage", "mod_supervideo"), ["maxlength" => 63]
            );
            $form->setType("captionlanguage[$index]", PARAM_TEXT);
            $form->addElement("select", "captionkind[$index]", get_string("captionkind", "mod_supervideo"), [
                "subtitles" => get_string("captionsubtitles", "mod_supervideo"),
                "captions" => get_string("captioncc", "mod_supervideo"),
            ]);
            $form->addElement("text", "captionurl[$index]", get_string("captionurl", "mod_supervideo"), ["size" => 60]);
            $form->setType("captionurl[$index]", PARAM_RAW_TRIMMED);
            $form->addElement("filepicker", "captiondraft[$index]", get_string("captionfile", "mod_supervideo"), null,
                ["accepted_types" => [".vtt"], "maxbytes" => validator::MAX_BYTES]);
            $form->hideIf("captiondraft[$index]", "captionsource[$index]", "neq", "file");
            $form->hideIf("captionurl[$index]", "captionsource[$index]", "neq", "url");
            $form->addElement("text", "captionorder[$index]", get_string("captionorder", "mod_supervideo"), ["size" => 4]);
            $form->setType("captionorder[$index]", PARAM_INT);
            $form->setDefault("captionorder[$index]", $index);
            $form->addElement("selectyesno", "captiondefault[$index]", get_string("captiondefault", "mod_supervideo"));
            $form->addElement("selectyesno", "captiondelete[$index]", get_string("captiondelete", "mod_supervideo"));
            foreach (["captionlabel", "captionlanguage", "captionkind", "captionorder", "captiondefault"] as $field) {
                $form->hideIf("$field" . "[$index]", "captionsource[$index]", "eq", "");
                $form->disabledIf("$field" . "[$index]", "captiondelete[$index]", "eq", 1);
            }
            foreach (["ottflix", "pandavideo", "youtube", "vimeo", "drive", "embed"] as $source) {
                foreach (["captionheading", "captionsource", "captionlabel", "captionlanguage", "captionkind",
                    "captionurl", "captiondraft", "captionorder", "captiondefault", "captiondelete"] as $field) {
                    $form->hideIf("$field" . "[$index]", "origem", "eq", $source);
                }
            }
        }
        if ($count < validator::MAX_TRACKS) {
            $form->registerNoSubmitButton("captionadd");
            $form->addElement("submit", "captionadd", get_string("captionadd", "mod_supervideo"),
                ["data-no-submit" => 1, "data-skip-validation" => 1], false);
        }
        foreach (["ottflix", "pandavideo", "youtube", "vimeo", "drive", "embed"] as $source) {
            foreach (["transcriptenabled", "transcriptdownload"] as $field) {
                $form->hideIf($field, "origem", "eq", $source);
            }
            if ($count < validator::MAX_TRACKS) {
                $form->hideIf("captionadd", "origem", "eq", $source);
            }
        }
    }

    /**
     * Prepare existing caption draft areas for editing.
     *
     * @param array $defaults Form defaults.
     * @param int $activityid Activity.
     * @param int $contextid Context.
     * @param array $drafts Submitted draft identifiers.
     */
    public static function prepare(array &$defaults, int $activityid, int $contextid, array $drafts): void {
        $index = 0;
        foreach (repository::all($activityid) as $record) {
            foreach (["id", "source", "label", "language", "kind", "url"] as $field) {
                $defaults["caption$field"][$index] = $record->$field;
            }
            $defaults["captiondefault"][$index] = $record->isdefault;
            $defaults["captionorder"][$index] = $record->sortorder;
            $draft = (int)($drafts[$index] ?? 0);
            if ($record->source === "file") {
                file_prepare_draft_area($draft, $contextid, "mod_supervideo", "captions", $record->id,
                    ["subdirs" => false, "maxfiles" => 1, "maxbytes" => validator::MAX_BYTES]);
            }
            $defaults["captiondraft"][$index] = $draft;
            $index++;
        }
    }
}
