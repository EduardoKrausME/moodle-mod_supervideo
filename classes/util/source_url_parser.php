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
 * Central video source URL parsing and validation.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\util;

/**
 * Centralised parsing and validation for Super Video sources.
 *
 * @package mod_supervideo
 */
class source_url_parser {

    /**
     * Validate a source URL according to the selected source.
     *
     * @param string $source Source name.
     * @param string $url URL or provider identifier.
     * @return bool
     */
    public static function is_valid($source, $url) {
        $url = trim((string)$url);
        if ($url === "") {
            return false;
        }

        switch ($source) {
            case "youtube":
                return self::youtube_id($url) !== "";
            case "vimeo":
                return self::vimeo($url) !== null;
            case "drive":
                return self::drive_id($url) !== "";
            case "link":
            case "embed":
                return self::is_http_url($url);
            case "pandavideo":
            case "ottflix":
                return strlen($url) >= 3;
            default:
                return false;
        }
    }

    /**
     * Check whether a value is an absolute HTTP(S) URL.
     *
     * @param string $url URL.
     * @return bool
     */
    public static function is_http_url($url) {
        $parts = parse_url(trim((string)$url));
        if (!is_array($parts) || empty($parts["scheme"]) || empty($parts["host"])) {
            return false;
        }
        return in_array(strtolower($parts["scheme"]), ["http", "https"], true);
    }

    /**
     * Extract a YouTube video id from supported URL formats.
     *
     * @param string $url URL.
     * @return string
     */
    public static function youtube_id($url) {
        $url = trim((string)$url);
        if (preg_match('/^[a-z0-9_-]{11}$/i', $url)) {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts["host"])) {
            return "";
        }
        $host = strtolower(preg_replace('/^www\./', '', $parts["host"]));

        if ($host === "youtu.be") {
            $path = trim($parts["path"] ?? "", "/");
            return preg_match('/^[a-z0-9_-]{11}$/i', $path) ? $path : "";
        }

        if (!in_array($host, ["youtube.com", "m.youtube.com", "music.youtube.com", "youtube-nocookie.com"], true)) {
            return "";
        }

        if (!empty($parts["query"])) {
            parse_str($parts["query"], $query);
            if (!empty($query["v"]) && preg_match('/^[a-z0-9_-]{11}$/i', $query["v"])) {
                return $query["v"];
            }
        }

        $segments = array_values(array_filter(explode('/', trim($parts["path"] ?? "", '/'))));
        if (count($segments) >= 2 && in_array($segments[0], ["embed", "live", "shorts"], true)
                && preg_match('/^[a-z0-9_-]{11}$/i', $segments[1])) {
            return $segments[1];
        }

        return "";
    }

    /**
     * Extract Google Drive file id from common URL formats.
     *
     * @param string $url URL.
     * @return string
     */
    public static function drive_id($url) {
        $url = trim((string)$url);
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts["host"])) {
            return "";
        }
        $host = strtolower(preg_replace('/^www\./', '', $parts["host"]));
        if (!in_array($host, ["drive.google.com", "docs.google.com"], true)) {
            return "";
        }
        $path = $parts["path"] ?? "";
        if (preg_match('/\/d\/([a-z0-9_-]+)/i', $path, $matches)) {
            return $matches[1];
        }
        if (!empty($parts["query"])) {
            parse_str($parts["query"], $query);
            if (!empty($query["id"]) && preg_match('/^[a-z0-9_-]+$/i', $query["id"])) {
                return $query["id"];
            }
        }
        return "";
    }

    /**
     * Extract Vimeo id and optional unlisted hash.
     *
     * @param string $url URL.
     * @return array|null
     */
    public static function vimeo($url) {
        $url = trim((string)$url);
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts["host"])) {
            return null;
        }
        $host = strtolower(preg_replace('/^www\./', '', $parts["host"]));
        if (!in_array($host, ["vimeo.com", "player.vimeo.com"], true)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($parts["path"] ?? "", '/'))));
        if ($host === "player.vimeo.com" && isset($segments[0]) && $segments[0] === "video") {
            array_shift($segments);
        }
        if (empty($segments[0]) || !ctype_digit((string)$segments[0])) {
            return null;
        }

        $hash = "";
        if (!empty($segments[1]) && preg_match('/^[a-z0-9]+$/i', $segments[1])) {
            $hash = $segments[1];
        }
        if (!empty($parts["query"])) {
            parse_str($parts["query"], $query);
            if (!empty($query["h"]) && preg_match('/^[a-z0-9]+$/i', $query["h"])) {
                $hash = $query["h"];
            }
        }

        return ["id" => $segments[0], "hash" => $hash];
    }

    /**
     * Get a filename from a URL path.
     *
     * @param string $url URL.
     * @return string
     */
    public static function filename($url) {
        $path = parse_url((string)$url, PHP_URL_PATH);
        $filename = $path ? basename($path) : "supervideo";
        return $filename ?: "supervideo";
    }

    /**
     * Get lowercase file extension from URL path.
     *
     * @param string $url URL.
     * @return string
     */
    public static function extension($url) {
        return strtolower(pathinfo(self::filename($url), PATHINFO_EXTENSION));
    }

    /**
     * Whether a URL points to a supported audio extension.
     *
     * @param string $url URL.
     * @return bool
     */
    public static function is_audio($url) {
        return in_array(self::extension($url), ["mp3", "aac", "m4a"], true);
    }

    /**
     * Whether a URL points to an HLS playlist.
     *
     * @param string $url URL.
     * @return bool
     */
    public static function is_hls($url) {
        return self::extension($url) === "m3u8";
    }

    /**
     * Return URL origin for postMessage checks.
     *
     * @param string $url URL.
     * @return string
     */
    public static function origin($url) {
        $parts = parse_url((string)$url);
        if (!is_array($parts) || empty($parts["scheme"]) || empty($parts["host"])) {
            return "";
        }
        $origin = strtolower($parts["scheme"]) . "://" . $parts["host"];
        if (!empty($parts["port"])) {
            $origin .= ":" . (int)$parts["port"];
        }
        return $origin;
    }
}
