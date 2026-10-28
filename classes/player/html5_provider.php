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
 * Player provider implementation.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\player;

use mod_supervideo\util\source_url_parser;
use moodle_url;

/**
 * Upload and direct-link player provider.
 */
class html5_provider extends base_provider {
    /**
     * Render this video source.
     *
     * @return player_result
     */
    public function render() {
        global $OUTPUT;

        if ($this->data->supervideo->origem === "upload") {
            $files = supervideo_get_area_files($this->data->context->id);
            $file = reset($files);
            if (!$file) {
                return $this->error(get_string("filenotfound", "mod_supervideo"));
            }
            $path = implode("/", [
                "",
                $this->data->context->id,
                "mod_supervideo/content",
                $file->get_id(),
                $file->get_itemid() . $file->get_filepath() . $file->get_filename(),
            ]);
            $url = moodle_url::make_file_url("/pluginfile.php", $path, false)->out();
            $filename = $file->get_filename();
        } else {
            $url = $this->data->supervideo->videourl;
            if (!source_url_parser::is_http_url($url)) {
                return $this->error(get_string("idnotfound", "mod_supervideo"));
            }
            $filename = source_url_parser::filename($url);
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isaudio = in_array($extension, ["mp3", "aac", "m4a"], true);
        $ishls = $extension === "m3u8";
        $markerconfig = $this->data->marker_config();

        $args = [
            (int)$this->data->view->id,
            (int)$this->data->view->currenttime,
            $this->data->elementid,
        ];
        if ($isaudio) {
            $args[] = $markerconfig;
            $this->js("resource_audio", $args);
        } else {
            $args[] = $ishls;
            $args[] = $markerconfig;
            $this->js("resource_video", $args);
        }

        $html = $OUTPUT->render_from_template("mod_supervideo/embed_div", [
            "elementid" => $this->data->elementid,
            "videourl" => $url,
            "autoplay" => !empty($this->data->supervideo->autoplay) ? "true" : "false",
            "showcontrols" => !empty($this->data->supervideo->showcontrols) ? 1 : 0,
            "controls" => $this->data->config->controls,
            "speed" => $this->data->config->speed,
            "hls" => $ishls,
            "has_audio" => $isaudio,
        ]);
        return new player_result($html, $this->data->supervideo->origem === "link" ? false : null, null, true);
    }
}
