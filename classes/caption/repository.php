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
 * Caption persistence and file access.
 *
 * @package mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\caption;

use context_module;
use moodle_url;
use stored_file;

/**
 * Caption records and associated files.
 */
final class repository {
    /**
     * Return ordered activity-owned tracks.
     *
     * @param int $activityid Activity instance.
     * @return array
     */
    public static function all(int $activityid): array {
        global $DB;
        return $DB->get_records("supervideo_caption", ["supervideoid" => $activityid], "sortorder ASC, id ASC");
    }

    /**
     * Check a local track belongs to the requested course module.
     *
     * @param object $cm Course module.
     * @param context_module $context Module context.
     * @param int $id Caption id.
     * @return stored_file|null
     */
    public static function file($cm, context_module $context, int $id): ?stored_file {
        global $DB;
        if ((int)$context->instanceid !== (int)$cm->id || $cm->modname !== "supervideo") {
            return null;
        }
        $record = $DB->get_record("supervideo_caption", ["id" => $id, "supervideoid" => $cm->instance, "source" => "file"]);
        $activity = $DB->get_record("supervideo", ["id" => $cm->instance], "id, origem");
        if (!$record || !$activity || !in_array($activity->origem, ["upload", "link"], true)) {
            return null;
        }
        $files = get_file_storage()->get_area_files($context->id, "mod_supervideo", "captions", $id, "id", false);
        if (count($files) !== 1) {
            return null;
        }
        $file = reset($files);
        if ($file->get_filesize() > validator::MAX_BYTES ||
                strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) !== "vtt") {
            return null;
        }
        try {
            validator::webvtt($file->get_content());
        } catch (\InvalidArgumentException $exception) {
            return null;
        }
        return $file;
    }

    /**
     * Build metadata without tokens or remote requests.
     *
     * @param object $activity Activity.
     * @param context_module $context Context.
     * @return array
     */
    public static function playback($activity, context_module $context): array {
        $tracks = [];
        if (in_array($activity->origem, ["upload", "link"], true)) {
            foreach (self::all((int)$activity->id) as $record) {
                $url = $record->url ?? "";
                if ($record->source === "file") {
                    $files = get_file_storage()->get_area_files($context->id, "mod_supervideo", "captions",
                        $record->id, "id", false);
                    if (count($files) !== 1) {
                        continue;
                    }
                    $file = reset($files);
                    $url = moodle_url::make_pluginfile_url($context->id, "mod_supervideo", "captions",
                        $record->id, $file->get_filepath(), $file->get_filename())->out(false);
                } else if (!validator::url($url)) {
                    continue;
                }
                $tracks[] = [
                    "id" => (int)$record->id,
                    "label" => $record->label,
                    "language" => $record->language,
                    "kind" => $record->kind,
                    "source" => $record->source,
                    "url" => $url,
                    "isdefault" => (bool)$record->isdefault,
                ];
            }
        }
        return [
            "tracks" => $tracks,
            "enabled" => !empty($activity->transcriptenabled) && !empty($tracks),
            "download" => !empty($activity->transcriptenabled) && !empty($activity->transcriptdownload) && !empty($tracks),
        ];
    }

    /**
     * Remove records and associated files when the activity is deleted.
     *
     * @param int $activityid Activity.
     * @param int $contextid Module context, or zero if already deleted.
     */
    public static function delete_all(int $activityid, int $contextid): void {
        global $DB;
        if ($contextid) {
            get_file_storage()->delete_area_files($contextid, "mod_supervideo", "captions");
        }
        $DB->delete_records("supervideo_caption", ["supervideoid" => $activityid]);
    }
}
