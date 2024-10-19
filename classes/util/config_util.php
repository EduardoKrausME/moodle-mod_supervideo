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

/**
 * Util config_util for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config_util {
    /**
     * Function get_config
     *
     * @param $supervideo
     *
     * @return mixed
     * @throws \dml_exception
     */
    public static function get_config($supervideo) {
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

        if ($config->maxwidth >= 500 && !$config->distractionfreemode) {
            $config->maxwidth = intval($config->maxwidth);
        } else {
            $config->maxwidth = false;
        }

        return $config;
    }
}