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

/**
 * YouTube player provider.
 */
class youtube_provider extends base_provider {
    /**
     * Render this video source.
     *
     * @return player_result
     */
    public function render() {
        global $OUTPUT;

        $videoid = source_url_parser::youtube_id($this->data->supervideo->videourl);
        if ($videoid === "") {
            return $this->error(get_string("idnotfound", "mod_supervideo"));
        }
        if (!isset($this->data->supervideo->playersize[3])) {
            $this->data->supervideo->playersize = supervideo_youtube_size($this->data->supervideo, true);
        }
        $this->js("youtube", [
            (int)$this->data->view->id,
            (int)$this->data->view->currenttime,
            $this->data->elementid,
            $videoid,
            $this->data->supervideo->playersize,
            !empty($this->data->supervideo->showcontrols) ? 1 : 0,
            !empty($this->data->supervideo->autoplay) ? 1 : 0,
            $this->data->marker_config(),
        ]);
        $mustachedata = [
            "elementid" => $this->data->elementid,
        ];
        return new player_result($OUTPUT->render_from_template("mod_supervideo/embed_div", $mustachedata));
    }
}
