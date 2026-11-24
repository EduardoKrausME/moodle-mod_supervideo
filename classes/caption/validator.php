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
 * WebVTT and caption metadata validation.
 *
 * @package mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\caption;

use context_user;
use InvalidArgumentException;
use stored_file;

/**
 * Validate caption metadata and local draft files without making remote requests.
 */
final class validator {
    /** @var int Maximum number of caption tracks. */
    public const MAX_TRACKS = 20;
    /** @var int Maximum bytes per track. */
    public const MAX_BYTES = 5242880;
    /** @var int Maximum number of cues. */
    public const MAX_CUES = 50000;

    /**
     * Validate an HTTPS URL without resolving or requesting it.
     *
     * @param string $url Address.
     * @return bool
     */
    public static function url(string $url): bool {
        if ($url === "" || strlen($url) > 8192 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts) && strtolower($parts["scheme"] ?? "") === "https" &&
            !empty($parts["host"]) && !isset($parts["user"]) && !isset($parts["pass"]) &&
            !isset($parts["fragment"]) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate a language tag, including common script, region and extension subtags.
     *
     * @param string $language Language tag.
     * @return bool
     */
    public static function language(string $language): bool {
        return strlen($language) <= 63 &&
            preg_match('/^(?:[a-z]{2,8}|x)(?:-[a-z0-9]{1,8})*$/iD', $language) === 1;
    }

    /**
     * Convert a valid WebVTT timestamp to milliseconds.
     *
     * @param string $value Timestamp.
     * @return int
     */
    private static function timestamp(string $value): int {
        if (!preg_match('/^(?:(\d{2,6}):)?([0-5]\d):([0-5]\d)\.(\d{3})$/D', $value, $match)) {
            throw new InvalidArgumentException("caption_invalidfile");
        }
        return (((int)$match[1] * 60 + (int)$match[2]) * 60 + (int)$match[3]) * 1000 + (int)$match[4];
    }

    /**
     * Check the WebVTT header, UTF-8, block boundaries and cue timing.
     * Native browser parsing remains responsible for cue styling and layout.
     *
     * @param string $text File contents.
     * @return void
     */
    public static function webvtt(string $text): void {
        if (strlen($text) > self::MAX_BYTES || !preg_match('//u', $text) || strpos($text, "\0") !== false) {
            throw new InvalidArgumentException("caption_invalidfile");
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (substr($text, 0, 3) === "\xef\xbb\xbf") {
            $text = substr($text, 3);
        }
        if (!preg_match('/^WEBVTT(?:[ \t][^\n]*)?(?:\n|$)/D', $text)) {
            throw new InvalidArgumentException("caption_invalidfile");
        }
        $blocks = preg_split('/\n[ \t]*\n+/', trim($text, "\n"));
        if (strpos($blocks[0], "-->") !== false) {
            throw new InvalidArgumentException("caption_invalidfile");
        }
        array_shift($blocks);
        $count = 0;
        foreach ($blocks as $block) {
            if ($block === "" || preg_match('/^(?:NOTE(?:[ \t\n]|$)|STYLE(?:\n|$)|REGION(?:\n|$))/', $block)) {
                continue;
            }
            $lines = explode("\n", $block);
            $timing = strpos($lines[0], "-->") !== false ? $lines[0] : ($lines[1] ?? "");
            if (!preg_match('/^(\S+)[ \t]+-->[ \t]+(\S+)(?:[ \t].*)?$/D', $timing, $matches) ||
                    self::timestamp($matches[2]) <= self::timestamp($matches[1]) || ++$count > self::MAX_CUES) {
                throw new InvalidArgumentException("caption_invalidfile");
            }
        }
        if (!$count) {
            throw new InvalidArgumentException("caption_invalidfile");
        }
    }

    /**
     * Get and validate a single file in the current user's draft area.
     *
     * @param int $draftid Draft item identifier.
     * @return stored_file
     */
    public static function draft(int $draftid): stored_file {
        global $USER;
        $files = get_file_storage()->get_area_files(context_user::instance($USER->id)->id,
            "user", "draft", $draftid, "id", false);
        if ($draftid <= 0 || count($files) !== 1) {
            throw new InvalidArgumentException("caption_invalidfile");
        }
        $file = reset($files);
        if ($file->get_filesize() > self::MAX_BYTES || strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) !== "vtt") {
            throw new InvalidArgumentException("caption_invalidfile");
        }
        self::webvtt($file->get_content());
        return $file;
    }
}
