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

namespace mod_supervideo\util;

use core\output\notification;

/**
 * Util config_util for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config_util {
    /**
     * Function get_config
     *
     * @param $supervideo
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public static function get_config($supervideo) {
        global $CFG;

        $config = get_config("supervideo");

        if ($config->showcontrols == 2) {
            $supervideo->showcontrols = 0;
        } else if ($config->showcontrols == 3) {
            $supervideo->showcontrols = 1;
        }

        if ($config->autoplay == 2) {
            $supervideo->autoplay = 0;
        } else if ($config->autoplay == 3) {
            $supervideo->autoplay = 1;
        }

        $theme = isset($_SESSION["SESSION"]->theme) ? $_SESSION["SESSION"]->theme : $CFG->theme;
        if ($theme == "moove") {
            if ($config->distractionfreemode) {
                $message = "The theme is not compatible with <em>Distraction-Free Mode</em>. " .
                    "To resolve this incompatibility, you can either choose a compatible theme or disable this setting.";
                \core\notification::add($message, notification::NOTIFY_ERROR);
            }
            $config->distractionfreemode = false;
        } else if ($theme == "adaptable") {
            $config->distractionfreemode = false;
        } else if ($theme == "snap") {
            $config->distractionfreemode = false;
        } else if ($theme == "trema") {
            global $PAGE;
            $PAGE->add_body_class("support-trema");
        }

        if ($config->maxwidth >= 500 && !$config->distractionfreemode) {
            $config->maxwidth = intval($config->maxwidth);
        } else {
            $config->maxwidth = false;
        }

        return $config;
    }
}
