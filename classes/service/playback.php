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
 * Playback data external service for the Moodle App.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\service;

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_warnings;
use invalid_parameter_exception;
use mod_supervideo\analytics\supervideo_view;
use mod_supervideo\ottflix\repository as ottflix_repository;
use mod_supervideo\pandavideo\repository as panda_repository;
use mod_supervideo\util\config_util;
use mod_supervideo\util\source_url_parser;
use moodle_exception;
use moodle_url;

defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/** Playback data used by the official Moodle App. */
class playback extends external_api {
    /**
     * Describe playback service parameters.
     *
     * @return external_function_parameters
     */
    public static function get_playback_data_parameters() {
        return new external_function_parameters([
            "cmid" => new external_value(PARAM_INT, "Course module id", VALUE_REQUIRED),
            "supervideoid" => new external_value(PARAM_INT, "Super Video instance id", VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Return normalized playback information for the Moodle App.
     *
     * @param int $cmid Course module id.
     * @param int $supervideoid Super Video instance id.
     * @return array
     */
    public static function get_playback_data($cmid, $supervideoid = 0) {
        global $CFG, $DB;
        require_once("{$CFG->dirroot}/mod/supervideo/lib.php");

        $params = self::validate_parameters(self::get_playback_data_parameters(), [
            "cmid" => $cmid,
            "supervideoid" => $supervideoid,
        ]);
        $cm = get_coursemodule_from_id("supervideo", $params["cmid"], 0, false, MUST_EXIST);
        $supervideo = $DB->get_record("supervideo", ["id" => $cm->instance], "*", MUST_EXIST);
        if (!empty($params["supervideoid"]) && (int)$params["supervideoid"] !== (int)$supervideo->id) {
            throw new invalid_parameter_exception("Invalid Super Video instance for this course module.");
        }

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability("mod/supervideo:view", $context);

        $config = config_util::get_config($supervideo);
        $view = supervideo_view::create($cm->id);

        return [
            "status" => true,
            "name" => format_string($supervideo->name, true, ["context" => $context]),
            "content" => self::get_content($cm, $context, $supervideo),
            "config" => [
                "showmap" => self::config_bool($config, "showmap", self::config_bool($config, "showmapa", false)),
                "showmapa" => self::config_bool($config, "showmapa", self::config_bool($config, "showmap", false)),
                "datamap" => base64_encode($view->map),
                "viewid" => (int)$view->id,
                "currenttime" => (int)($view->currenttime ?? 0),
                "showcontrols" => !empty($supervideo->showcontrols) ? 1 : 0,
                "autoplay" => !empty($supervideo->autoplay) ? 1 : 0,
                "playersize" => (string)($supervideo->playersize ?? ""),
            ],
            "warnings" => [],
        ];
    }

    /**
     * Build content for all supported sources using the shared URL parser.
     *
     * @param object $cm Course module.
     * @param context_module $context Module context.
     * @param object $supervideo Activity record.
     * @return array
     */
    private static function get_content($cm, context_module $context, $supervideo) {
        global $USER;
        $source = (string)($supervideo->origem ?? "");

        if ($source === "upload") {
            return self::upload_content($context);
        }

        $url = (string)($supervideo->videourl ?? "");
        if ($source !== "ottflix" && $source !== "pandavideo" && !source_url_parser::is_valid($source, $url)) {
            throw new moodle_exception("idnotfound", "mod_supervideo");
        }

        if ($source === "link") {
            $extension = source_url_parser::extension($url);
            return self::content([
                "type" => "link",
                "source" => $source,
                "fileurl" => $url,
                "filename" => source_url_parser::filename($url),
                "extension" => $extension,
                "isaudio" => source_url_parser::is_audio($url),
                "ishls" => source_url_parser::is_hls($url),
                "trustedorigin" => source_url_parser::origin($url),
            ]);
        }

        if ($source === "youtube") {
            $id = source_url_parser::youtube_id($url);
            $query = http_build_query([
                "playsinline" => 1,
                "rel" => 0,
                "controls" => !empty($supervideo->showcontrols) ? 1 : 0,
                "autoplay" => !empty($supervideo->autoplay) ? 1 : 0,
            ], "", "&", PHP_QUERY_RFC3986);
            return self::content([
                "type" => "youtube",
                "source" => $source,
                "fileurl" => "https://www.youtube.com/embed/" . rawurlencode($id) . "?" . $query,
                "filename" => "youtube-" . $id,
                "providerid" => $id,
                "trustedorigin" => "https://www.youtube.com",
            ]);
        }

        if ($source === "vimeo") {
            $vimeo = source_url_parser::vimeo($url);
            $query = [
                "pip" => 1,
                "title" => 0,
                "byline" => 0,
                "playsinline" => 1,
                "controls" => !empty($supervideo->showcontrols) ? 1 : 0,
                "autoplay" => !empty($supervideo->autoplay) ? 1 : 0,
            ];
            if ($vimeo["hash"] !== "") {
                $query["h"] = $vimeo["hash"];
            }
            return self::content([
                "type" => "vimeo",
                "source" => $source,
                "fileurl" => "https://player.vimeo.com/video/" . rawurlencode($vimeo["id"]) . "?" .
                    http_build_query($query, "", "&", PHP_QUERY_RFC3986),
                "filename" => "vimeo-" . $vimeo["id"],
                "providerid" => $vimeo["id"],
                "trustedorigin" => "https://player.vimeo.com",
            ]);
        }

        if ($source === "drive") {
            $id = source_url_parser::drive_id($url);
            $query = http_build_query([
                "controls" => !empty($supervideo->showcontrols) ? 1 : 0,
                "autoplay" => !empty($supervideo->autoplay) ? 1 : 0,
            ], "", "&", PHP_QUERY_RFC3986);
            return self::content([
                "type" => "drive",
                "source" => $source,
                "fileurl" => "https://drive.google.com/file/d/" . rawurlencode($id) . "/preview?" . $query,
                "filename" => "google-drive-" . $id,
                "providerid" => $id,
                "trustedorigin" => "https://drive.google.com",
            ]);
        }

        if ($source === "pandavideo") {
            $panda = panda_repository::oembed($url);
            $playerurl = preg_replace('/.*src=["\'](.*?)["\'].*/s', '$1', $panda->html);
            if (!source_url_parser::is_http_url($playerurl)) {
                throw new moodle_exception("idnotfound", "mod_supervideo");
            }
            return self::content([
                "type" => "pandavideo",
                "source" => $source,
                "fileurl" => $playerurl,
                "filename" => "panda-" . (isset($panda->id) ? $panda->id : md5($url)),
                "providerid" => isset($panda->id) ? (string)$panda->id : "",
                "trustedorigin" => source_url_parser::origin($playerurl),
                "providerdata" => json_encode([
                    "width" => isset($panda->width) ? (int)$panda->width : 16,
                    "height" => isset($panda->height) ? (int)$panda->height : 9,
                ]),
            ]);
        }

        if ($source === "ottflix") {
            if (!preg_match('/([A-Z0-9_-]{3,255})/i', $url, $matches)) {
                throw new moodle_exception("idnotfound", "mod_supervideo");
            }
            $identifier = $matches[1];
            $isia = !empty($supervideo->ottflix_ia) && isset($supervideo->ottflix_ia[3]);
            $isassetsh5p = strpos($url, "Share/assetsh5p") !== false;
            $identifiers = [$identifier];
            if ($isia || $isassetsh5p) {
                $prefix = $isassetsh5p ? "h5p:" : "video:";
                $response = json_decode(ottflix_repository::h5p($prefix . $identifier, $supervideo->ottflix_ia));
                if (empty($response->data->html)) {
                    throw new moodle_exception("idnotfound", "mod_supervideo");
                }
                $html = $response->data->html;
                if (!empty($response->data->identifiers)) {
                    $identifiers = $response->data->identifiers;
                }
            } else {
                $html = ottflix_repository::getplayer($cm->id, $identifier, $USER->id);
            }
            $playerurl = "";
            if (preg_match('/src=["\'](https?:\/\/[^"\']+)["\']/i', $html, $urlmatch)) {
                $playerurl = html_entity_decode($urlmatch[1]);
            }
            return self::content([
                "type" => "ottflix",
                "source" => $source,
                "fileurl" => $playerurl,
                "filename" => "ottflix-" . $identifier,
                "providerid" => $identifier,
                "trustedorigin" => source_url_parser::origin($playerurl),
                "providerdata" => json_encode(["identifiers" => $identifiers]),
            ]);
        }

        if ($source === "embed") {
            return self::content([
                "type" => "embed",
                "source" => $source,
                "fileurl" => $url,
                "filename" => source_url_parser::filename($url),
                "trustedorigin" => source_url_parser::origin($url),
            ]);
        }

        throw new moodle_exception("idnotfound", "mod_supervideo");
    }

    /**
     * Build playback data for an uploaded file.
     *
     * @param context_module $context Module context.
     * @return array
     */
    private static function upload_content(context_module $context) {
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
            $file->get_itemid() . $file->get_filepath() . $file->get_filename(),
        ]);
        $url = moodle_url::make_file_url("/pluginfile.php", $path, false)->out(false);
        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        return self::content([
            "type" => "file",
            "source" => "upload",
            "fileurl" => $url,
            "filename" => $file->get_filename(),
            "extension" => $extension,
            "mimetype" => (string)$file->get_mimetype(),
            "isaudio" => in_array($extension, ["mp3", "aac", "m4a"], true),
            "ishls" => $extension === "m3u8",
        ]);
    }

    /**
     * Normalize content result keys.
     *
     * @param array $values Content values.
     * @return array
     */
    private static function content(array $values) {
        return [
            "type" => (string)($values["type"] ?? ""),
            "source" => (string)($values["source"] ?? ""),
            "fileurl" => (string)($values["fileurl"] ?? ""),
            "filename" => (string)($values["filename"] ?? "supervideo"),
            "extension" => (string)($values["extension"] ?? ""),
            "mimetype" => (string)($values["mimetype"] ?? ""),
            "isaudio" => !empty($values["isaudio"]),
            "ishls" => !empty($values["ishls"]),
            "providerid" => (string)($values["providerid"] ?? ""),
            "trustedorigin" => (string)($values["trustedorigin"] ?? ""),
            "providerdata" => (string)($values["providerdata"] ?? ""),
        ];
    }

    /**
     * Read a boolean configuration value.
     *
     * @param object $config Configuration object.
     * @param string $name Property name.
     * @param bool $default Default value.
     * @return bool
     */
    private static function config_bool($config, $name, $default) {
        return property_exists($config, $name) ? !empty($config->{$name}) : $default;
    }

    /**
     * Describe playback service return values.
     *
     * @return external_single_structure
     */
    public static function get_playback_data_returns() {
        return new external_single_structure([
            "status" => new external_value(PARAM_BOOL, "Status"),
            "name" => new external_value(PARAM_TEXT, "Activity name"),
            "content" => new external_single_structure([
                "type" => new external_value(PARAM_ALPHANUMEXT, "Playback type"),
                "source" => new external_value(PARAM_ALPHANUMEXT, "Original source"),
                "fileurl" => new external_value(PARAM_RAW, "Playable URL"),
                "filename" => new external_value(PARAM_FILE, "Filename"),
                "extension" => new external_value(PARAM_ALPHANUMEXT, "Extension"),
                "mimetype" => new external_value(PARAM_RAW, "MIME type"),
                "isaudio" => new external_value(PARAM_BOOL, "Audio flag"),
                "ishls" => new external_value(PARAM_BOOL, "HLS flag"),
                "providerid" => new external_value(PARAM_RAW, "Provider identifier"),
                "trustedorigin" => new external_value(PARAM_RAW, "Allowed postMessage origin"),
                "providerdata" => new external_value(PARAM_RAW, "Provider-specific JSON"),
            ]),
            "config" => new external_single_structure([
                "showmap" => new external_value(PARAM_BOOL, "Show map"),
                "showmapa" => new external_value(PARAM_BOOL, "Legacy map flag"),
                "datamap" => new external_value(PARAM_RAW, "Base64 progress map"),
                "viewid" => new external_value(PARAM_INT, "View id"),
                "currenttime" => new external_value(PARAM_INT, "Resume position"),
                "showcontrols" => new external_value(PARAM_INT, "Controls flag"),
                "autoplay" => new external_value(PARAM_INT, "Autoplay flag"),
                "playersize" => new external_value(PARAM_RAW, "Player size"),
            ]),
            "warnings" => new external_warnings(),
        ]);
    }
}
