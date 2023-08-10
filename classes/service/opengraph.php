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
 * User: Eduardo Kraus
 * Date: 09/08/2023
 * Time: 17:51
 */

namespace mod_supervideo\service;

use external_function_parameters;
use external_single_structure;
use mod_supervideo\analytics\supervideo_view;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * @package   mod_supervideo
 * @copyright 2023 Eduardo Kraus {@link http://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opengraph extends \external_api {
    /**
     * Describes the parameters for save
     *
     * @return external_function_parameters
     */
    public static function getinfo_parameters() {
        return new \external_function_parameters([
            'url' => new \external_value(PARAM_TEXT, 'The URL', VALUE_REQUIRED),
        ]);
    }

    /**
     * Record watch time
     *
     * @param string $url
     *
     * @return array
     * @throws \invalid_parameter_exception
     */
    public static function getinfo($url) {
        $params = self::validate_parameters(self::getinfo_parameters(), [
            'url' => $url,
        ]);

        $opengraph = \mod_supervideo\util\opengraph::fetch($params['url']);

        return [
            'title' => $opengraph->get("title"),
            'url' => $opengraph->get("video:url"),
            'width' => intval($opengraph->get("video:width")),
            'height' => intval($opengraph->get("video:height")),
        ];
    }

    /**
     * Describes the save return value.
     *
     * @return external_single_structure
     */
    public static function getinfo_returns() {
        return new \external_single_structure([
            'title' => new \external_value(PARAM_RAW),
            'url' => new \external_value(PARAM_RAW),
            'width' => new \external_value(PARAM_RAW),
            'height' => new \external_value(PARAM_RAW),
        ]);
    }
}
