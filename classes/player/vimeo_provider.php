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
 * Vimeo player provider.
 */
class vimeo_provider extends base_provider {
    /**
     * Render this video source.
     *
     * @return player_result
     * @throws coding_exception
     */
    public function render() {
        $vimeo = source_url_parser::vimeo($this->data->supervideo->videourl);
        if ($vimeo === null) {
            return $this->error(get_string("idnotfound", "mod_supervideo"));
        }
        $query = [
            "pip" => 1,
            "title" => !empty($this->data->supervideo->showcontrols) ? 1 : 0,
            "byline" => 0,
            "playsinline" => 1,
            "autoplay" => !empty($this->data->supervideo->autoplay) ? 1 : 0,
            "controls" => !empty($this->data->supervideo->showcontrols) ? 1 : 0,
        ];
        if ($vimeo["hash"] !== "") {
            $query["h"] = $vimeo["hash"];
        }
        $parameters = http_build_query($query, "", "&amp;", PHP_QUERY_RFC3986);
        $vimeoid = $vimeo["id"] . "?" . $parameters;
        $this->js("vimeo", [
            (int)$this->data->view->id,
            (int)$this->data->view->currenttime,
            $this->data->supervideo->videourl,
            $this->data->elementid,
            $this->data->marker_config(),
        ]);
        return new player_result($this->template("embed_vimeo", [
            "elementid" => $this->data->elementid,
            "vimeo_id" => $vimeoid,
            "parametersvimeo" => $parameters,
        ]));
    }
}
