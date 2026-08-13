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

use coding_exception;
use mod_supervideo\util\source_url_parser;

/**
 * Generic iframe provider.
 */
class embed_provider extends base_provider {
    /**
     * Render this video source.
     *
     * @return player_result
     * @throws coding_exception
     */
    public function render() {
        $url = $this->data->supervideo->videourl;
        if (!source_url_parser::is_http_url($url)) {
            return $this->error(get_string("idnotfound", "mod_supervideo"));
        }
        $this->js("embed", [
            (int)$this->data->view->id,
            (int)$this->data->view->currenttime,
            $this->data->elementid,
            $this->data->supervideo->playersize,
            $this->data->marker_config(),
            source_url_parser::origin($url),
        ]);
        $currenttime = (int)$this->data->view->currenttime;
        if ($currenttime > 0) {
            $url .= (strpos($url, "?") !== false ? "&" : "?") . "t=" . $currenttime;
        }
        return new player_result($this->template("embed_iframe", [
            "elementid" => $this->data->elementid,
            "videourl" => $url,
        ]));
    }
}
