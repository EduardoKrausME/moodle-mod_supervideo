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
 * @package    mod_supervideo
 * @copyright  2023 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $settings->add(new admin_setting_configcheckbox('supervideo/showmapa',
        get_string('showmapa', 'mod_supervideo'),
        get_string('showmapa_desc', 'mod_supervideo'), 1));

    $options = array(
        0 => get_string('settings_opcional_desmarcado', 'mod_supervideo'),
        1 => get_string('settings_opcional_marcado', 'mod_supervideo'),
        2 => get_string('settings_obrigatorio_desmarcado', 'mod_supervideo'),
        3 => get_string('settings_obrigatorio_marcado', 'mod_supervideo'),
    );

    $settings->add(new admin_setting_configselect('supervideo/showrel',
        get_string('showrel', 'mod_supervideo'),
        get_string('showrel_desc', 'mod_supervideo'), 0, $options));

    $settings->add(new admin_setting_configselect('supervideo/showcontrols',
        get_string('showcontrols', 'mod_supervideo'),
        get_string('showcontrols_desc', 'mod_supervideo'), 1, $options));

    $settings->add(new admin_setting_configselect('supervideo/showinfo',
        get_string('showinfo', 'mod_supervideo'),
        get_string('showinfo_desc', 'mod_supervideo'), 0, $options));

    $settings->add(new admin_setting_configselect('supervideo/autoplay',
        get_string('autoplay', 'mod_supervideo'),
        get_string('autoplay_desc', 'mod_supervideo'), 0, $options));
}
