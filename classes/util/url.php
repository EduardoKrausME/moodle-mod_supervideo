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

    public static function parse($videourl) {
        $url = new url();

        if (strpos($videourl, "[link]:") === 0) {
            $url->videoid = substr($videourl, 7);
            $url->engine = "link";
        } else if (strpos($videourl, "[resource-file") === 0) {
            $item = substr($videourl, 0, -1);
            $url->videoid = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $url->engine = "resource";
        } else if (strpos($videourl, "youtube")) {
            if (preg_match('/[\?|&]v=([a-zA-Z0-9\-_]{11})/', $videourl, $output)) {
                $url->videoid = $output[1];
                $url->engine = "youtube";
            }
        } else if (strpos($videourl, "youtu.be")) {
            if (preg_match('/youtu.be\/([a-zA-Z0-9\-_]{11})/', $videourl, $output)) {
                $url->videoid = $output[1];
                $url->engine = "youtube";
            }
        } else if (strpos($videourl, "vimeo")) {
            if (preg_match('/vimeo.com\/(\d+)/', $videourl, $output)) {
                $url->videoid = $output[1];
                $url->engine = "vimeo";
            }
        } else if (strpos($videourl, "drive.google.com")) {
            if (preg_match('/([a-zA-Z0-9\-_]{33})/', $videourl, $output)) {
                $url->videoid = $output[1];
                $url->engine = "google-drive";
            }
        } else {
            $url->videoid = false;
            $url->engine = "";
        }

        return $url;
    }
}
