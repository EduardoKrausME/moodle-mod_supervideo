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

namespace mod_supervideo\service;

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_warnings;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * Service view for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_supervideo_parameters() {
        return new external_function_parameters([
                'supervideoid' => new external_value(PARAM_INT, 'supervideo instance id'),
            ]
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $supervideoid the supervideo instance id
     *
     * @return array of warnings and status result
     *
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     */
    public static function view_supervideo($supervideoid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/supervideo/lib.php");

        $params = self::validate_parameters(self::view_supervideo_parameters(),
            ['supervideoid' => $supervideoid]);
        $warnings = [];

        // Request and permission validation.
        $supervideo = $DB->get_record('supervideo', ['id' => $params['supervideoid']], '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($supervideo, 'supervideo');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/supervideo:view', $context);

        supervideo_view($supervideo, $course, $cm, $context);

        $result = [
            'status' => true,
            'warnings' => $warnings,
        ];
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function view_supervideo_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            ]
        );
    }
}
