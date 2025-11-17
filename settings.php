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
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $setting = new admin_setting_configcheckbox("supervideo/distractionfreemode",
        get_string("distractionfreemode", "mod_supervideo"),
        get_string("distractionfreemode_desc", "mod_supervideo"),
        1);
    $settings->add($setting);

    $setting = new admin_setting_configcheckbox("supervideo/showmapa",
        get_string("showmapa", "mod_supervideo"),
        get_string("showmapa_desc", "mod_supervideo"),
        1);
    $settings->add($setting);

    $options = [
        "play" => get_string("controls_play", "mod_supervideo"),
        "pause" => get_string("controls_pause", "mod_supervideo"),
        "restart" => get_string("controls_restart", "mod_supervideo"),
        "progress" => get_string("controls_progress", "mod_supervideo"),
        "current-time" => get_string("controls_current-time", "mod_supervideo"),
        "mute" => get_string("controls_mute", "mod_supervideo"),
        "volume" => get_string("controls_volume", "mod_supervideo"),
        "pip" => get_string("controls_pip", "mod_supervideo"),
        "duration" => get_string("controls_duration", "mod_supervideo"),
        "rewind" => get_string("controls_rewind", "mod_supervideo"),
        "fastForward" => get_string("controls_fastForward", "mod_supervideo"),
        "settings" => get_string("controls_settings", "mod_supervideo"),
        "captions" => get_string("controls_captions", "mod_supervideo"),
        "fullscreen" => get_string("controls_fullscreen", "mod_supervideo"),
    ];
    $optionsdefault = ["play", "pause", "progress", "current-time", "pip", "duration", "settings", "fullscreen"];
    $setting = new admin_setting_configmultiselect("supervideo/controls",
        get_string("controls", "mod_supervideo"),
        get_string("controls_desc", "mod_supervideo"),
        $optionsdefault, $options);
    $settings->add($setting);

    $options = [
        "0.5" => get_string("speed_0_5", "mod_supervideo"),
        "0.75" => get_string("speed_0_75", "mod_supervideo"),
        "1" => get_string("speed_1", "mod_supervideo"),
        "1.25" => get_string("speed_1_25", "mod_supervideo"),
        "1.5" => get_string("speed_1_5", "mod_supervideo"),
        "1.75" => get_string("speed_1_75", "mod_supervideo"),
        "2" => get_string("speed_2", "mod_supervideo"),
        "4" => get_string("speed_4", "mod_supervideo"),
    ];
    $setting = new admin_setting_configmultiselect("supervideo/speed",
        get_string("speed", "mod_supervideo"),
        get_string("speed_desc", "mod_supervideo"),
        array_keys($options), $options);
    $settings->add($setting);

    $options = [
        0 => get_string("settings_opcional_desmarcado", "mod_supervideo"),
        1 => get_string("settings_opcional_marcado", "mod_supervideo"),
        2 => get_string("settings_obrigatorio_desmarcado", "mod_supervideo"),
        3 => get_string("settings_obrigatorio_marcado", "mod_supervideo"),
    ];
    $setting = new admin_setting_configselect("supervideo/showcontrols",
        get_string("showcontrols", "mod_supervideo"),
        get_string("showcontrols_desc", "mod_supervideo"),
        1, $options);
    $settings->add($setting);

    $setting = new admin_setting_configselect("supervideo/autoplay",
        get_string("autoplay", "mod_supervideo"),
        get_string("autoplay_desc", "mod_supervideo"),
        0, $options);
    $settings->add($setting);

    $setting = new admin_setting_configtext("supervideo/maxwidth",
        get_string("maxwidth", "mod_supervideo"),
        get_string("maxwidth_desc", "mod_supervideo"),
        0, PARAM_INT);
    $settings->add($setting);

    // OttFlix.
    $title = get_string("ottflix_title", "mod_supervideo");
    $setting = new admin_setting_heading("supervideo/ottflix", $title, "");
    $settings->add($setting);

    $setting = new admin_setting_configtext("supervideo/ottflix_url",
        get_string("ottflix_url", "mod_supervideo"),
        get_string("ottflix_url_desc", "mod_supervideo"),
        "https://app.ottflix.com.br/", PARAM_URL);
    $settings->add($setting);

    $setting = new admin_setting_configpasswordunmask("supervideo/ottflix_token",
        get_string("ottflix_token", "mod_supervideo"),
        get_string("ottflix_token_desc", "mod_supervideo"),
        "HMAC-SHA2048-xxxxxxxx");
    $settings->add($setting);

    $title = get_string("distractionfreemode_h5p", "mod_supervideo");
    $description = get_string("distractionfreemode_h5p_desc", "mod_supervideo");
    $setting = new admin_setting_configcheckbox("supervideo/distractionfreemode_h5p",
        $title, $description, 1);
    $settings->add($setting);

    // Kapture converter.
    $options = [
        "none" => get_string("kapture_convert_none", "mod_supervideo"),
        "ffmpeg" => get_string("kapture_convert_ffmpeg", "mod_supervideo"),
        "qencode" => get_string("kapture_convert_qencode", "mod_supervideo"),
    ];

    // Conversion method selector.
    $setting = new admin_setting_configselect("supervideo/kapture_convert",
        get_string("kapture_convert", "mod_supervideo"),
        get_string("kapture_convert_desc", "mod_supervideo"),
        "none",
        $options
    );
    $settings->add($setting);

    // FFmpeg path (only used when "ffmpeg" is selected).
    $setting = new admin_setting_configtext("supervideo/kapture_ffmpeg",
        get_string("kapture_ffmpeg", "mod_supervideo"),
        get_string("kapture_ffmpeg_desc", "mod_supervideo"),
        "/usr/bin/ffmpeg",
        PARAM_PATH
    );
    $settings->add($setting);

    // Qencode API key (only used when "qencode" is selected).
    $setting = new admin_setting_configpasswordunmask("supervideo/kapture_qencode",
        get_string("kapture_qencode", "mod_supervideo"),
        get_string("kapture_qencode_desc", "mod_supervideo"),
        ""
    );
    $settings->add($setting);
}
