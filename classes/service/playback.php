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

namespace mod_supervideo\service;

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_warnings;
use invalid_parameter_exception;
use mod_supervideo\analytics\supervideo_view;
use mod_supervideo\util\config_util;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/**
 * Mobile playback data service for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class playback extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_playback_data_parameters(): external_function_parameters {
        return new external_function_parameters([
            "cmid" => new external_value(PARAM_INT, "Course module id", VALUE_REQUIRED),
            "supervideoid" => new external_value(PARAM_INT, "SuperVideo instance id", VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns all data the mobile app needs to decide how to play the video.
     *
     * @param int $cmid Course module id.
     * @param int $supervideoid SuperVideo instance id.
     * @return array
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function get_playback_data(int $cmid, int $supervideoid = 0): array {
        global $CFG, $DB;

        require_once("{$CFG->dirroot}/mod/supervideo/lib.php");

        $params = self::validate_parameters(self::get_playback_data_parameters(), [
            "cmid" => $cmid,
            "supervideoid" => $supervideoid,
        ]);

        $cm = get_coursemodule_from_id("supervideo", $params["cmid"], 0, false, MUST_EXIST);
        $course = $DB->get_record("course", ["id" => $cm->course], "*", MUST_EXIST);
        $supervideo = $DB->get_record("supervideo", ["id" => $cm->instance], "*", MUST_EXIST);

        if (!empty($params["supervideoid"]) && (int)$params["supervideoid"] !== (int)$supervideo->id) {
            throw new invalid_parameter_exception("Invalid SuperVideo instance for this course module.");
        }

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability("mod/supervideo:view", $context);

        $config = config_util::get_config($supervideo);
        $view = supervideo_view::create($cm->id);
        $content = self::get_content($cm, $context, $supervideo);

        return [
            "status" => true,
            "name" => format_string($supervideo->name, true, ["context" => $context]),
            "content" => $content,
            "config" => [
                "showmap" => self::get_config_bool($config, "showmap", self::get_config_bool($config, "showmapa", false)),
                "showmapa" => self::get_config_bool($config, "showmapa", self::get_config_bool($config, "showmap", false)),
                "datamap" => base64_encode($view->map),
                "viewid" => $view->id,
                "currenttime" => (int)($view->currenttime ?? 0),
                "showcontrols" => !empty($supervideo->showcontrols) ? 1 : 0,
                "autoplay" => !empty($supervideo->autoplay) ? 1 : 0,
                "playersize" => (string)($supervideo->playersize ?? ""),
            ],
            "warnings" => [],
        ];
    }

    /**
     * Builds the content payload according to the configured video source.
     *
     * @param stdClass $cm Course module.
     * @param context_module $context Module context.
     * @param stdClass $supervideo SuperVideo record.
     * @return array
     * @throws moodle_exception
     */
    private static function get_content(stdClass $cm, context_module $context, stdClass $supervideo): array {
        $source = (string)($supervideo->origem ?? "");
        $webviewurl = (new moodle_url("/mod/supervideo/view-mobile.php", ["id" => $cm->id]))->out(false);

        if ($source === "upload") {
            return self::get_upload_content($context, $webviewurl);
        }

        if ($source === "link") {
            $fileurl = (string)($supervideo->videourl ?? "");
            $filename = self::filename_from_url($fileurl);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            return self::content_structure([
                "type" => "link",
                "source" => $source,
                "fileurl" => $fileurl,
                "filename" => $filename,
                "extension" => $extension,
                "mimetype" => "",
                "isaudio" => in_array($extension, ["mp3", "aac", "m4a"], true),
                "ishls" => $extension === "m3u8",
                "webviewurl" => $webviewurl,
            ]);
        }

        if ($source === "youtube") {
            $videoid = self::youtube_id((string)($supervideo->videourl ?? ""));
            if (!$videoid) {
                throw new moodle_exception("idnotfound", "mod_supervideo");
            }

            return self::content_structure([
                "type" => "youtube",
                "source" => $source,
                "fileurl" => "https://www.youtube.com/watch?v={$videoid}",
                "filename" => "youtube-{$videoid}",
                "extension" => "",
                "mimetype" => "",
                "isaudio" => false,
                "ishls" => false,
                "webviewurl" => $webviewurl,
            ]);
        }

        if ($source === "drive") {
            $driveid = self::drive_id((string)($supervideo->videourl ?? ""));
            if (!$driveid) {
                throw new moodle_exception("idnotfound", "mod_supervideo");
            }

            $parameters = http_build_query([
                "controls" => !empty($supervideo->showcontrols) ? 1 : 0,
                "autoplay" => !empty($supervideo->autoplay) ? 1 : 0,
            ], "", "&", PHP_QUERY_RFC3986);

            return self::content_structure([
                "type" => "google-drive",
                "source" => $source,
                "fileurl" => "https://drive.google.com/file/d/" . rawurlencode($driveid) . "/preview?{$parameters}",
                "filename" => "google-drive-{$driveid}",
                "extension" => "",
                "mimetype" => "",
                "isaudio" => false,
                "ishls" => false,
                "webviewurl" => $webviewurl,
            ]);
        }

        if ($source === "vimeo") {
            $vimeo = self::vimeo_embed_url((string)($supervideo->videourl ?? ""), $supervideo);
            if (!$vimeo) {
                throw new moodle_exception("idnotfound", "mod_supervideo");
            }

            return self::content_structure([
                "type" => "vimeo",
                "source" => $source,
                "fileurl" => $vimeo["url"],
                "filename" => "vimeo-{$vimeo["id"]}",
                "extension" => "",
                "mimetype" => "",
                "isaudio" => false,
                "ishls" => false,
                "webviewurl" => $webviewurl,
            ]);
        }

        return self::content_structure([
            "type" => in_array($source, ["ottflix", "pandavideo"], true) ? $source : "webview",
            "source" => $source,
            "fileurl" => $webviewurl,
            "filename" => $source ?: "supervideo",
            "extension" => "",
            "mimetype" => "",
            "isaudio" => false,
            "ishls" => false,
            "webviewurl" => $webviewurl,
        ]);
    }

    /**
     * Builds upload file content data.
     *
     * @param context_module $context Module context.
     * @param string $webviewurl Webview fallback URL.
     * @return array
     * @throws moodle_exception
     */
    private static function get_upload_content(context_module $context, string $webviewurl): array {
        $files = supervideo_get_area_files($context->id);
        $file = reset($files);
        if (!$file) {
            throw new moodle_exception("filenotfound", "mod_supervideo");
        }

        $path = implode("/", [
            "",
            $context->id,
            "mod_supervideo/content",
            $file->get_id(),
            "{$file->get_itemid()}{$file->get_filepath()}{$file->get_filename()}",
        ]);
        $fileurl = moodle_url::make_file_url("/pluginfile.php", $path, false)->out(false);
        $filename = $file->get_filename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return self::content_structure([
            "type" => "file",
            "source" => "upload",
            "fileurl" => $fileurl,
            "filename" => $filename,
            "extension" => $extension,
            "mimetype" => $file->get_mimetype(),
            "isaudio" => in_array($extension, ["mp3", "aac", "m4a"], true),
            "ishls" => $extension === "m3u8",
            "webviewurl" => $webviewurl,
        ]);
    }

    /**
     * Normalizes content payload fields.
     *
     * @param array $content Content data.
     * @return array
     */
    private static function content_structure(array $content): array {
        return [
            "type" => (string)($content["type"] ?? "webview"),
            "source" => (string)($content["source"] ?? ""),
            "fileurl" => (string)($content["fileurl"] ?? ""),
            "filename" => (string)($content["filename"] ?? "supervideo"),
            "extension" => (string)($content["extension"] ?? ""),
            "mimetype" => (string)($content["mimetype"] ?? ""),
            "isaudio" => !empty($content["isaudio"]),
            "ishls" => !empty($content["ishls"]),
            "webviewurl" => (string)($content["webviewurl"] ?? ""),
        ];
    }

    /**
     * Extracts a YouTube video id from common URL formats.
     *
     * @param string $url Video URL.
     * @return string
     */
    private static function youtube_id(string $url): string {
        if (preg_match('/youtu(\.be|be\.com)\/(watch\?v=|embed\/|live\/|shorts\/)?([a-z0-9_\-]{11})/i', $url, $matches)) {
            return $matches[3];
        }
        return "";
    }

    /**
     * Extracts a Google Drive file id.
     *
     * @param string $url Video URL.
     * @return string
     */
    private static function drive_id(string $url): string {
        if (preg_match('/\/d\/([^\/]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/[?&]id=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }
        return "";
    }

    /**
     * Builds a Vimeo embed URL.
     *
     * @param string $url Video URL.
     * @param stdClass $supervideo SuperVideo record.
     * @return array|null
     */
    private static function vimeo_embed_url(string $url, stdClass $supervideo): ?array {
        if (!preg_match('/vimeo\.com\/(\d+)(\/(\w+))?/i', $url, $matches)) {
            return null;
        }

        $query = [
            "pip" => 1,
            "title" => 0,
            "byline" => 0,
            "playsinline" => 1,
            "controls" => !empty($supervideo->showcontrols) ? 1 : 0,
            "autoplay" => !empty($supervideo->autoplay) ? 1 : 0,
        ];

        if (!empty($matches[3])) {
            $query["h"] = $matches[3];
        }

        return [
            "id" => $matches[1],
            "url" => "https://player.vimeo.com/video/{$matches[1]}?" . http_build_query($query, "", "&", PHP_QUERY_RFC3986),
        ];
    }

    /**
     * Gets a filename from a URL.
     *
     * @param string $url File URL.
     * @return string
     */
    private static function filename_from_url(string $url): string {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = $path ? basename($path) : "supervideo";
        return $filename ?: "supervideo";
    }

    /**
     * Reads a boolean config value safely.
     *
     * @param object $config Config object.
     * @param string $name Config key.
     * @param bool $default Default value.
     * @return bool
     */
    private static function get_config_bool(object $config, string $name, bool $default): bool {
        return property_exists($config, $name) ? !empty($config->{$name}) : $default;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function get_playback_data_returns(): external_single_structure {
        return new external_single_structure([
            "status" => new external_value(PARAM_BOOL, "Status: true if success"),
            "name" => new external_value(PARAM_TEXT, "Formatted activity name"),
            "content" => new external_single_structure([
                "type" => new external_value(PARAM_ALPHANUMEXT, "Playback type for the mobile app"),
                "source" => new external_value(PARAM_ALPHANUMEXT, "Original SuperVideo source"),
                "fileurl" => new external_value(PARAM_RAW, "Playable URL or webview URL"),
                "filename" => new external_value(PARAM_FILE, "Filename or synthetic filename"),
                "extension" => new external_value(PARAM_ALPHANUMEXT, "File extension"),
                "mimetype" => new external_value(PARAM_RAW, "MIME type"),
                "isaudio" => new external_value(PARAM_BOOL, "Whether the content should use an audio tag"),
                "ishls" => new external_value(PARAM_BOOL, "Whether the content is an HLS playlist"),
                "webviewurl" => new external_value(PARAM_RAW, "Moodle mobile fallback page URL without token"),
            ]),
            "config" => new external_single_structure([
                "showmap" => new external_value(PARAM_BOOL, "Whether to show the view map"),
                "showmapa" => new external_value(PARAM_BOOL, "Legacy map flag"),
                "datamap" => new external_value(PARAM_RAW, "Base64 encoded watched map"),
                "viewid" => new external_value(PARAM_INT, "View row id"),
                "currenttime" => new external_value(PARAM_INT, "Last watched position"),
                "showcontrols" => new external_value(PARAM_INT, "Show controls flag"),
                "autoplay" => new external_value(PARAM_INT, "Autoplay flag"),
                "playersize" => new external_value(PARAM_RAW, "Player size"),
            ]),
            "warnings" => new external_warnings(),
        ]);
    }
}
