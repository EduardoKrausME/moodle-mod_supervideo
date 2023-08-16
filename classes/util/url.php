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
 * External implementation for mod_supervideo.
 */

namespace mod_supervideo\util;

class url {

    public $videoid = false;
    public $engine = "";
    public $extra = "";

    public static function parse($videourl) {
        $url = new url();

        if (strpos($videourl, "[link]:") === 0) {
            $url->videoid = substr($videourl, 7);
            $url->engine = "link";
            $url->extra = "mp4";
        } else if (strpos($videourl, "[resource-file") === 0) {
            $item = substr($videourl, 0, -1);
            $url->videoid = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $url->engine = "resource";
        } else if (strpos($videourl, "youtu")) {
            if (preg_match('/youtu(\.be|be\.com)\/(watch\?v=|embed\/|live\/|shorts\/)?([a-z0-9_\-]{11})/i', $videourl, $output)) {
                $url->videoid = $output[3];
                $url->engine = "youtube";
            }
        } else if (strpos($videourl, "vimeo")) {
            if (preg_match('/vimeo.com\/(\d+)/', $videourl, $output)) {
                $url->videoid = $output[1];
                $url->engine = "vimeo";
            }
        } else if (strpos($videourl, "docs.google.com")) {
            if (preg_match('/([a-zA-Z0-9\-_]{33})/', $videourl, $output)) {
                $url->videoid = $output[1];
                $url->engine = "google-drive";
            }
        } else {
            if (preg_match('/^https?.*\.(mp3|mp4)/i', $videourl, $output)) {
                $url->videoid = $videourl;
                $url->engine = "link";
                $url->extra = $output[1];
            }

            $url->videoid = false;
            $url->engine = "";
        }

        return $url;
    }
}
