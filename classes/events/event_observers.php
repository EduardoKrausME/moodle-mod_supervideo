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
 * Event observers file
 *
 * @package   mod_supervideo
 * @copyright 2025 Eduardo Kraus {@link http://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\events;

use core\event\base;

/**
 * Class event_observers
 */
class event_observers {
    /**
     * Function process_event
     *
     * @param base $event
     */
    public static function process_event(base $event) {
        $eventname = str_replace("\\\\", "\\", $event->eventname);
        switch ($eventname) {
            case '\core\event\course_module_created':
            case '\core\event\course_module_updated':
                \cache::make("theme_boost_training", "css_cache")->purge();
                break;
        }
    }
}
