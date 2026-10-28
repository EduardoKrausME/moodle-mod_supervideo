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
 * Utilities for parsing video markers.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\util;

use InvalidArgumentException;

/**
 * Utilities for parsing video markers.
 */
class marker_util {

    /**
     * Parse the marker definition stored in the activity.
     *
     * Each non-empty line uses the format `time | label | skip`. The final
     * token is optional. When present, the jump button is shown after that
     * marker and seeks to the following marker.
     *
     * @param string|null $value Marker definition.
     * @param bool $strict Throw when an invalid line is found.
     * @return array Normalised marker points.
     */
    public static function parse($value, $strict = false) {
        if ($value === null || trim($value) === "") {
            return [];
        }

        $markers = [];
        $lines = preg_split('/\R/', $value);
        foreach ($lines as $index => $line) {
            if (trim($line) === "") {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $time = self::parse_time($parts[0] ?? "");
            $label = clean_param($parts[1] ?? "", PARAM_TEXT);
            $action = strtolower($parts[2] ?? "");
            $validaction = $action === "" || $action === "skip";

            if (count($parts) > 3 || $time === null || $label === "" || !$validaction) {
                if ($strict) {
                    throw new InvalidArgumentException("Invalid marker on line " . ($index + 1));
                }
                continue;
            }

            if (isset($markers[$time])) {
                if ($strict) {
                    throw new InvalidArgumentException("Duplicate marker on line " . ($index + 1));
                }
                continue;
            }

            $markers[$time] = [
                "time" => $time,
                "label" => $label,
                "skip" => $action === "skip",
            ];
        }

        ksort($markers, SORT_NUMERIC);
        return array_values($markers);
    }

    /**
     * Convert seconds, MM:SS, or HH:MM:SS into seconds.
     *
     * @param string $value Time value.
     * @return int|null Parsed seconds, or null for an invalid value.
     */
    private static function parse_time($value) {
        if (!preg_match('/^\d+(?::\d{1,2}){0,2}$/', trim($value))) {
            return null;
        }

        $parts = array_map('intval', explode(':', trim($value)));
        if (count($parts) === 1) {
            return $parts[0];
        }
        if (count($parts) === 2) {
            if ($parts[1] > 59) {
                return null;
            }
            return ($parts[0] * 60) + $parts[1];
        }
        if ($parts[1] > 59 || $parts[2] > 59) {
            return null;
        }
        return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
    }
}
