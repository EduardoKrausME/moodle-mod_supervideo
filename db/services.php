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
 * services file
 *
 * @package    mod_supervideo
 * @copyright  2023 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'mod_supervideo_services_progress_save' => [
        'classpath' => 'mod/supervideo/classes/service/progress.php',
        'classname' => 'mod_supervideo\service\progress',
        'methodname' => 'save',
        'description' => 'Save progress video.',
        'type' => 'write',
        'ajax' => true,
    ],
    'mod_supervideo_services_opengraph_getinfo' => [
        'classpath' => 'mod/supervideo/classes/service/opengraph.php',
        'classname' => 'mod_supervideo\service\opengraph',
        'methodname' => 'getinfo',
        'description' => 'Save progress video.',
        'type' => 'write',
        'ajax' => true,
    ]
];
