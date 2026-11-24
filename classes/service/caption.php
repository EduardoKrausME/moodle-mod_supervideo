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
 * Authenticated local WebVTT access for Moodle App.
 *
 * @package mod_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\service;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_supervideo\caption\repository;
use moodle_exception;

defined('MOODLE_INTERNAL') || die;

// Moodle 4.2+ autoloads these classes. Older supported versions provide the global API.
if (!class_exists(external_api::class)) {
    require_once($CFG->libdir . "/externallib.php");
    class_alias(\external_api::class, external_api::class);
    class_alias(\external_function_parameters::class, external_function_parameters::class);
    class_alias(\external_single_structure::class, external_single_structure::class);
    class_alias(\external_value::class, external_value::class);
}

/**
 * Read local captions using Moodle's existing web service authentication.
 */
final class caption extends external_api {
    /**
     * Parameters for a caption request.
     *
     * @return external_function_parameters
     */
    public static function get_parameters(): external_function_parameters {
        return new external_function_parameters([
            "cmid" => new external_value(PARAM_INT, "Course module id"),
            "captionid" => new external_value(PARAM_INT, "Caption id"),
        ]);
    }

    /**
     * Read only a local track belonging to the authorised activity.
     *
     * @param int $cmid Course module id.
     * @param int $captionid Caption id.
     * @return array
     */
    public static function get($cmid, $captionid): array {
        $params = self::validate_parameters(self::get_parameters(), ["cmid" => $cmid, "captionid" => $captionid]);
        $cm = get_coursemodule_from_id("supervideo", $params["cmid"], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability("mod/supervideo:view", $context);
        $file = repository::file($cm, $context, $params["captionid"]);
        if (!$file) {
            throw new moodle_exception("filenotfound", "mod_supervideo");
        }
        return ["text" => $file->get_content()];
    }

    /**
     * Response schema.
     *
     * @return external_single_structure
     */
    public static function get_returns(): external_single_structure {
        return new external_single_structure(["text" => new external_value(PARAM_RAW, "WebVTT text")]);
    }
}
