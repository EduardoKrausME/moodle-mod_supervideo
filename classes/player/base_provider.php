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

/** Shared helpers for player providers. */
abstract class base_provider implements provider_interface {
    /** @var player_context */
    protected $data;

    /**
     * Constructor.
     *
     * @param player_context $data Shared player context.
     */
    public function __construct(player_context $data) {
        $this->data = $data;
    }

    /**
     * Force player controls to LTR on RTL pages.
     *
     * @param array $data Template data.
     * @return array
     */
    protected function direction(array $data) {
        if (right_to_left()) {
            $data["direction"] = "ltr";
        }
        return $data;
    }

    /**
     * Render a player template.
     *
     * @param string $name Template name.
     * @param array $data Template data.
     * @return string
     */
    protected function template($name, array $data) {
        global $OUTPUT;
        return $OUTPUT->render_from_template("mod_supervideo/" . $name, $this->direction($data));
    }

    /**
     * Render a provider error.
     *
     * @param string $message Error message.
     * @return player_result
     */
    protected function error($message) {
        global $OUTPUT;
        $html = $OUTPUT->render_from_template("mod_supervideo/error", [
            "elementId" => "message_notfound",
            "type" => "danger",
            "message" => $message,
        ]);
        return new player_result($html, false, false, false);
    }

    /**
     * Queue an AMD player initializer.
     *
     * @param string $method AMD export name.
     * @param array $args AMD arguments.
     * @return void
     */
    protected function js($method, array $args) {
        global $PAGE;
        $PAGE->requires->js_call_amd("mod_supervideo/player_create", $method, $args);
    }
}
