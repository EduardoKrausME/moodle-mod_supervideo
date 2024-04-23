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

namespace mod_supervideo\util;

/**
 * Util url for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class url {

    public $videoid = false;
    public $engine = "";
    public $extra = "";

    public static function parse($videourl) {
        $url = new url();

        if (strpos($videourl, "ottflix.com") > 1) {
            if (preg_match('/\/\w+\/\w+\/([A-Z0-9\-\_]{3,255})/', $videourl, $path)) {
                $url->videoid = $path[1];
                $url->engine = "ottflix";
                $url->extra = "mp4";
                return $url;
            }
        }
        if (strpos($videourl, "[link]:") === 0) {
            $url->videoid = substr($videourl, 7);
            $url->engine = "link";
            $url->extra = "mp4";
            return $url;
        }
        if (strpos($videourl, "[resource-file") === 0) {
            $item = substr($videourl, 0, -1);
            $url->videoid = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $url->engine = "resource";
            return $url;
        }
        if (strpos($videourl, "youtu")) {
            if (preg_match('/youtu(\.be|be\.com)\/(watch\?v=|embed\/|live\/|shorts\/)?([a-z0-9_\-]{11})/i', $videourl, $output)) {
                $url->videoid = $output[3];
                $url->engine = "youtube";
                return $url;
            }
        }
        if (strpos($videourl, "vimeo")) {
            if (preg_match('/vimeo.com\/(\d+)(\/(\w+))?/', $videourl, $output)) {
                $url->engine = "vimeo";
                if (isset($output[3])) {
                    $url->videoid = "{$output[1]}?h={$output[3]}";
                    return $url;
                } else {
                    $url->videoid = $output[1];
                    $url->engine = "vimeo";
                    return $url;
                }
            }
        }
        if (strpos($videourl, "docs.google.com") || strpos($videourl, "drive.google.com")) {
            if (preg_match('/([a-zA-Z0-9\-_]{33})/', $videourl, $output)) {
                $url->videoid = $output[1];
                $url->engine = "google-drive";
                return $url;
            }
        }

        if (preg_match('/^https?.*\.(mp3|mp4|m3u8|webm)/i', $videourl, $output)) {
            $url->videoid = $videourl;
            $url->engine = "link";
            $url->extra = $output[1];
            return $url;
        }

        $url->videoid = false;
        $url->engine = "";
        return $url;
    }
}
