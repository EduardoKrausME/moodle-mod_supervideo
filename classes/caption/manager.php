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
 * Caption form validation and persistence.
 *
 * @package mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\caption;

use context_module;
use InvalidArgumentException;
use moodle_exception;

/**
 * Validate and apply explicitly submitted caption edits.
 */
final class manager {
    /**
     * Validate rows before making changes, including ownership and single-default rules.
     *
     * @param array $data Submitted form fields.
     * @param int $activityid Existing activity id, or zero.
     * @return array
     */
    public static function inspect(array $data, int $activityid): array {
        $rows = [];
        $errors = [];
        if (empty($data["captionform"]) || !in_array($data["origem"] ?? "", ["upload", "link"], true)) {
            return [$rows, $errors];
        }
        $existing = repository::all($activityid);
        $count = (int)($data["captioncount"] ?? 0);
        if ($count < 0 || $count > validator::MAX_TRACKS) {
            return [$rows, ["captionnotice" => get_string("caption_invalid", "mod_supervideo")]];
        }
        $seen = [];
        $defaults = 0;
        for ($index = 0; $index < $count; $index++) {
            $id = (int)($data["captionid"][$index] ?? 0);
            $source = (string)($data["captionsource"][$index] ?? "");
            if ($id && (!isset($existing[$id]) || isset($seen[$id]))) {
                $errors["captionlabel[$index]"] = get_string("caption_invalid", "mod_supervideo");
                continue;
            }
            if ($id) {
                $seen[$id] = true;
            }
            if (!empty($data["captiondelete"][$index])) {
                if ($id) {
                    $rows[] = ["id" => $id, "delete" => true];
                }
                continue;
            }
            if ($source === "" && !$id) {
                continue;
            }
            $label = trim(clean_param((string)($data["captionlabel"][$index] ?? ""), PARAM_TEXT));
            $language = trim((string)($data["captionlanguage"][$index] ?? ""));
            $kind = (string)($data["captionkind"][$index] ?? "");
            $url = trim((string)($data["captionurl"][$index] ?? ""));
            $draft = (int)($data["captiondraft"][$index] ?? 0);
            $default = !empty($data["captiondefault"][$index]);
            if ($label === "" || \core_text::strlen($label) > 255 ||
                    !in_array($source, ["file", "url"], true) || !in_array($kind, ["captions", "subtitles"], true)) {
                $errors["captionlabel[$index]"] = get_string("caption_invalid", "mod_supervideo");
            }
            if (!validator::language($language)) {
                $errors["captionlanguage[$index]"] = get_string("caption_invalidlanguage", "mod_supervideo");
            }
            if ($default && ++$defaults > 1) {
                $errors["captiondefault[$index]"] = get_string("caption_singledefault", "mod_supervideo");
            }
            if ($source === "url" && !validator::url($url)) {
                $errors["captionurl[$index]"] = get_string("caption_invalidurl", "mod_supervideo");
            } else if ($source === "file") {
                try {
                    validator::draft($draft);
                } catch (InvalidArgumentException $exception) {
                    $errors["captiondraft[$index]"] = get_string("caption_invalidfile", "mod_supervideo");
                }
            }
            $rows[] = ["id" => $id, "label" => $label, "language" => $language, "kind" => $kind,
                "source" => $source, "url" => $source === "url" ? $url : null, "draft" => $draft,
                "isdefault" => (int)$default, "sortorder" => max(0, min(999, (int)($data["captionorder"][$index] ?? $index)))];
        }
        // A truncated or tampered form must not silently delete or duplicate existing records.
        if (array_diff_key($existing, $seen)) {
            $errors["captionnotice"] = get_string("caption_invalid", "mod_supervideo");
        }
        return [$rows, $errors];
    }

    /**
     * Apply validated caption changes, retaining files on ordinary programmatic updates.
     *
     * @param object $activity Activity submitted through Moodle's module API.
     */
    public static function save($activity): void {
        global $DB;
        if (empty($activity->captionform) || !in_array($activity->origem, ["upload", "link"], true)) {
            return;
        }
        $context = context_module::instance($activity->coursemodule);
        // During add_instance, the core course module exists but its instance is still zero.
        $cm = $DB->get_record("course_modules", ["id" => $context->instanceid], "*", MUST_EXIST);
        $modname = $DB->get_field("modules", "name", ["id" => $cm->module], MUST_EXIST);
        if ($modname !== "supervideo" || (int)$cm->course !== (int)$activity->course ||
                ((int)$cm->instance !== 0 && (int)$cm->instance !== (int)$activity->id)) {
            throw new moodle_exception("caption_invalid", "mod_supervideo");
        }
        require_capability("moodle/course:manageactivities", $context);
        $factory = \core\lock\lock_config::get_lock_factory("mod_supervideo_caption");
        $lock = $factory->get_lock("activity-" . $activity->id, 10);
        if (!$lock) {
            throw new moodle_exception("caption_invalid", "mod_supervideo");
        }
        try {
            self::save_locked($activity, $context);
        } finally {
            $lock->release();
        }
    }

    /**
     * Revalidate and persist the submitted tracks while holding the activity lock.
     *
     * @param object $activity Activity.
     * @param context_module $context Module context.
     */
    private static function save_locked($activity, context_module $context): void {
        global $DB;
        [$rows, $errors] = self::inspect((array)$activity, (int)$activity->id);
        if ($errors) {
            throw new moodle_exception("caption_invalid", "mod_supervideo");
        }
        $transaction = $DB->start_delegated_transaction();
        $fs = get_file_storage();
        foreach ($rows as $row) {
            $id = $row["id"];
            if (!empty($row["delete"])) {
                $fs->delete_area_files($context->id, "mod_supervideo", "captions", $id);
                $DB->delete_records("supervideo_caption", ["id" => $id, "supervideoid" => $activity->id]);
                continue;
            }
            $draft = $row["draft"];
            unset($row["draft"], $row["id"]);
            $record = (object)$row;
            $record->supervideoid = $activity->id;
            $record->timemodified = time();
            if ($id) {
                $record->id = $id;
                $DB->update_record("supervideo_caption", $record);
            } else {
                $record->timecreated = time();
                $id = $DB->insert_record("supervideo_caption", $record);
            }
            if ($record->source === "file") {
                file_save_draft_area_files($draft, $context->id, "mod_supervideo", "captions", $id,
                    ["subdirs" => false, "maxfiles" => 1, "maxbytes" => validator::MAX_BYTES]);
            } else {
                $fs->delete_area_files($context->id, "mod_supervideo", "captions", $id);
            }
        }
        $DB->set_field("supervideo", "transcriptenabled", empty($activity->transcriptenabled) ? 0 : 1, ["id" => $activity->id]);
        $DB->set_field("supervideo", "transcriptdownload",
            !empty($activity->transcriptenabled) && !empty($activity->transcriptdownload) ? 1 : 0, ["id" => $activity->id]);
        $transaction->allow_commit();
    }
}
