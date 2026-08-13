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
 * Secure Open Graph external service.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\service;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_supervideo\util\opengraph_util;
use mod_supervideo\util\source_url_parser;

defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once("{$CFG->libdir}/externallib.php");

/** Secure Open Graph external service. */
class opengraph extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function getinfo_parameters() {
        return new external_function_parameters([
            "url" => new external_value(PARAM_URL, "Public HTTP(S) URL", VALUE_REQUIRED),
        ]);
    }

    /**
     * Fetch Open Graph information from a public URL.
     *
     * @param string $url Public URL.
     * @return array
     */
    public static function getinfo($url) {
        global $USER;
        $params = self::validate_parameters(self::getinfo_parameters(), ["url" => $url]);
        require_login();
        if (isguestuser($USER) || !source_url_parser::is_http_url($params["url"])) {
            throw new invalid_parameter_exception("Invalid URL.");
        }

        $opengraph = opengraph_util::fetch($params["url"]);
        if (!$opengraph) {
            throw new invalid_parameter_exception("Unable to fetch Open Graph metadata from this URL.");
        }
        return [
            "title" => (string)$opengraph->get("title"),
            "url" => (string)$opengraph->get("video:url"),
            "width" => (int)$opengraph->get("video:width"),
            "height" => (int)$opengraph->get("video:height"),
        ];
    }

    /**
     * Describe return values.
     *
     * @return external_single_structure
     */
    public static function getinfo_returns() {
        return new external_single_structure([
            "title" => new external_value(PARAM_RAW),
            "url" => new external_value(PARAM_RAW),
            "width" => new external_value(PARAM_INT),
            "height" => new external_value(PARAM_INT),
        ]);
    }
}
