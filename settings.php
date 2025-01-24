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
 * @package   mod_supervideo
 * @copyright 2024 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $title = get_string('distractionfreemode', 'mod_supervideo');
    $description = get_string('distractionfreemode_desc', 'mod_supervideo');
    $settings->add(new admin_setting_configcheckbox('supervideo/distractionfreemode',
        $title, $description, 1));

    $title = get_string('showmapa', 'mod_supervideo');
    $description = get_string('showmapa_desc', 'mod_supervideo');
    $settings->add(new admin_setting_configcheckbox('supervideo/showmapa',
        $title, $description, 1));

    $options = [
        0 => get_string('settings_opcional_desmarcado', 'mod_supervideo'),
        1 => get_string('settings_opcional_marcado', 'mod_supervideo'),
        2 => get_string('settings_obrigatorio_desmarcado', 'mod_supervideo'),
        3 => get_string('settings_obrigatorio_marcado', 'mod_supervideo'),
    ];

    $title = get_string('showcontrols', 'mod_supervideo');
    $description = get_string('showcontrols_desc', 'mod_supervideo');
    $settings->add(new admin_setting_configselect('supervideo/showcontrols',
        $title, $description, 1, $options));

    $title = get_string('autoplay', 'mod_supervideo');
    $description = get_string('autoplay_desc', 'mod_supervideo');
    $settings->add(new admin_setting_configselect('supervideo/autoplay',
        $title, $description, 0, $options));

    $title = get_string('maxwidth', 'mod_supervideo');
    $description = get_string('maxwidth_desc', 'mod_supervideo');
    $settings->add(new admin_setting_configtext('supervideo/maxwidth',
        $title, $description, 0, PARAM_INT));


    $title = get_string('ottflix_title', 'mod_supervideo');
    $description = "";
    $settings->add(new admin_setting_heading('supervideo/ottflix', $title, $description));

    // OttFlix.
    $title = get_string('ottflix_url', 'mod_supervideo');
        $description = get_string('ottflix_url_desc', 'mod_supervideo');
    $settings->add(new admin_setting_configtext('supervideo/ottflix_url',
        $title, $description, "https://app.ottflix.com.br/", PARAM_URL));

    $title = get_string('ottflix_token', 'mod_supervideo');
    $description = get_string('ottflix_token_desc', 'mod_supervideo');
    $settings->add(new admin_setting_configtext('supervideo/ottflix_token',
        $title, $description, "HMAC-SHA2048-xxxxxxxx", PARAM_TEXT));
}
