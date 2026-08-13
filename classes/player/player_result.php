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

/**
 * Result returned by a player provider.
 */
class player_result {
    /** @var string */
    public $html;
    /** @var bool|null */
    public $freemode;
    /** @var bool|null */
    public $showmap;
    /** @var bool */
    public $mediaerrors;

    /**
     * Construct
     *
     * @param string $html Rendered HTML.
     * @param bool|null $freemode Override distraction free mode.
     * @param bool|null $showmap Override map visibility.
     * @param bool $mediaerrors Whether standard media errors are required.
     */
    public function __construct($html, $freemode = null, $showmap = null, $mediaerrors = false) {
        $this->html = $html;
        $this->freemode = $freemode;
        $this->showmap = $showmap;
        $this->mediaerrors = $mediaerrors;
    }
}
