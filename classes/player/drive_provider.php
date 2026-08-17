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
 * Google Drive player provider.
 */
class drive_provider extends base_provider {
    /**
     * Render this video source.
     *
     * @return player_result
     */
    public function render() {
        global $OUTPUT;

        $driveid = source_url_parser::drive_id($this->data->supervideo->videourl);
        if ($driveid === "") {
            return $this->error(get_string("idnotfound", "mod_supervideo"));
        }
        $parameters = implode("&amp;", [
            !empty($this->data->supervideo->showcontrols) ? "controls=1" : "controls=0",
            !empty($this->data->supervideo->autoplay) ? "autoplay=1" : "autoplay=0",
        ]);
        $this->js("drive", [(int)$this->data->view->id, $this->data->elementid, $this->data->supervideo->playersize]);
        return new player_result($OUTPUT->render_from_template("mod_supervideo/embed_drive", [
            "elementid" => $this->data->elementid,
            "driveid" => $driveid,
            "parametersdrive" => $parameters,
        ]), null, false);
    }
}
