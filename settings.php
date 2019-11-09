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
 * setting file
 *
 * @package mod_supervideo
 * @copyright  2015 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $settings->add(new admin_setting_configcheckbox('supervideo/showrel',
        'Show Rel', get_string('showrel', 'supervideo'), 0));

    $settings->add(new admin_setting_configcheckbox('supervideo/showcontrols',
        'Show Controls', get_string('showcontrols', 'supervideo'), 0));

    $settings->add(new admin_setting_configcheckbox('supervideo/showshowinfo',
        'Show Info', get_string('showshowinfo', 'supervideo'), 0));

    $settings->add(new admin_setting_configcheckbox('supervideo/autoplay',
        'Auto Play', get_string('autoplay', 'supervideo'), 0));
}
