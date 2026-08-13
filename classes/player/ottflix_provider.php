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

use mod_supervideo\ottflix\repository;
use mod_supervideo\util\source_url_parser;

/** OTTFlix player provider. */
class ottflix_provider extends base_provider {
    /**
     * Render this video source.
     *
     * @return player_result
     */
    public function render() {
        global $USER;

        if (!preg_match('/([A-Z0-9_-]{3,255})/i', $this->data->supervideo->videourl, $matches)) {
            return $this->error(get_string("idnotfound", "mod_supervideo"));
        }
        $identifier = $matches[1];
        $isia = !empty($this->data->supervideo->ottflix_ia) && isset($this->data->supervideo->ottflix_ia[3]);
        $isassetsh5p = strpos($this->data->supervideo->videourl, "Share/assetsh5p") !== false;
        $identifiers = [$identifier];
        $freemode = null;

        if ($isia || $isassetsh5p) {
            $freemode = !empty($this->data->config->distractionfreemode_h5p);
            $prefix = $isassetsh5p ? "h5p:" : "video:";
            $response = json_decode(repository::h5p($prefix . $identifier, $this->data->supervideo->ottflix_ia));
            if (empty($response->data->html)) {
                return $this->error(get_string("idnotfound", "mod_supervideo"));
            }
            $html = $response->data->html;
            if (!empty($response->data->identifiers)) {
                $identifiers = $response->data->identifiers;
            }
        } else {
            $html = repository::getplayer($this->data->cm->id, $identifier, $USER->id);
        }

        $origins = [];
        if (preg_match_all('/src=["\'](https?:\/\/[^"\']+)["\']/i', $html, $urls)) {
            foreach ($urls[1] as $url) {
                $origin = source_url_parser::origin(html_entity_decode($url));
                if ($origin !== "") {
                    $origins[$origin] = $origin;
                }
            }
        }
        $this->js("ottflix", [
            (int)$this->data->view->id,
            (int)$this->data->view->currenttime,
            $this->data->elementid,
            $identifiers,
            array_values($origins),
        ]);
        $wrapped = '<div id="' . s($this->data->elementid) . '">' . $html . '</div>';
        return new player_result($wrapped, $freemode);
    }
}
