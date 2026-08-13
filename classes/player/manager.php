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

/**
 * Selects a source-specific player provider.
 */
class manager {
    /** @var player_context */
    private $data;

    /**
     * Constructor.
     *
     * @param object $cm Course module.
     * @param object $course Course record.
     * @param object $supervideo Activity record.
     * @param object $context Module context.
     * @param object $config Plugin configuration.
     * @param object $view User view record.
     */
    public function __construct($cm, $course, $supervideo, $context, $config, $view) {
        $this->data = new player_context($cm, $course, $supervideo, $context, $config, $view);
    }

    /**
     * Select and render the provider for the current source.
     *
     * @return player_result
     * @throws coding_exception
     */
    public function render() {
        $source = isset($this->data->supervideo->origem) ? $this->data->supervideo->origem : "";
        if ($source !== "upload" && empty($this->data->supervideo->videourl)) {
            return $this->error();
        }

        switch ($source) {
            case "upload":
            case "link":
                $provider = new html5_provider($this->data);
                break;
            case "youtube":
                $provider = new youtube_provider($this->data);
                break;
            case "vimeo":
                $provider = new vimeo_provider($this->data);
                break;
            case "drive":
                $provider = new drive_provider($this->data);
                break;
            case "pandavideo":
                $provider = new pandavideo_provider($this->data);
                break;
            case "ottflix":
                $provider = new ottflix_provider($this->data);
                break;
            case "embed":
                $provider = new embed_provider($this->data);
                break;
            default:
                return $this->error();
        }
        return $provider->render();
    }

    /**
     * Return the common invalid-source result.
     *
     * @return player_result
     */
    private function error() {
        global $OUTPUT;
        $html = $OUTPUT->render_from_template("mod_supervideo/error", [
            "elementId" => "message_notfound",
            "type" => "danger",
            "message" => get_string("idnotfound", "mod_supervideo"),
        ]);
        return new player_result($html, false, false, false);
    }
}
