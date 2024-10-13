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

namespace mod_supervideo\output;

use mod_supervideo;

/**
 * Output Mobile for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Function mobile_course_view
     *
     * @param $args
     *
     * @return array
     * @throws \Exception
     */
    public static function mobile_course_view($args) {
        global $CFG;

        $html = "<core-iframe [src]=\"{$CFG->wwwroot}/mod/supervideo/view.php?mobile=1&id={$args['cmid']}\"
            [allowFullscreen]=\"true\"></core-iframe>";

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $html,
                ],
            ],
        ];
    }
}
