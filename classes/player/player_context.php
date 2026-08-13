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

use mod_supervideo\util\marker_util;

/** Immutable-ish context shared by player providers. */
class player_context {
    /** @var object|string */
    public $cm;
    /** @var object|string */
    public $course;
    /** @var object|string */
    public $supervideo;
    /** @var object|string */
    public $context;
    /** @var object|string */
    public $config;
    /** @var object|string */
    public $view;
    /** @var object|string */
    public $elementid;

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
        $this->cm = $cm;
        $this->course = $course;
        $this->supervideo = $supervideo;
        $this->context = $context;
        $this->config = $config;
        $this->view = $view;
        $this->elementid = $supervideo->origem . "-" . uniqid();
    }

    /**
     * Return marker configuration for JavaScript players.
     *
     * @return array
     */
    public function marker_config() {
        return [
            "points" => marker_util::parse(isset($this->supervideo->markers) ? $this->supervideo->markers : ""),
            "buttonlabel" => get_string("markers_jump", "mod_supervideo"),
        ];
    }
}
