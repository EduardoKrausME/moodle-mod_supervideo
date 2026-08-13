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

use Exception;
use mod_supervideo\pandavideo\repository;
use mod_supervideo\util\source_url_parser;

/**
 * Panda Video player provider.
 */
class pandavideo_provider extends base_provider {
    /**
     * Render this video source.
     *
     * @return player_result
     */
    public function render() {
        try {
            $panda = repository::oembed($this->data->supervideo->videourl);
            $playerurl = preg_replace('/.*src=["\'](.*?)["\'].*/s', '$1', $panda->html);
            if (!source_url_parser::is_http_url($playerurl)) {
                return $this->error(get_string("idnotfound", "mod_supervideo"));
            }
            $this->js("pandavideo", [
                (int)$this->data->view->id,
                (int)$this->data->view->currenttime,
                $this->data->elementid,
                ["width" => $panda->width, "height" => $panda->height],
                $this->data->marker_config(),
                source_url_parser::origin($playerurl),
            ]);
            return new player_result($this->template("embed_pandavideo", [
                "elementid" => $this->data->elementid,
                "id" => $panda->id,
                "video_player" => $playerurl,
            ]));
        } catch (Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }
}
