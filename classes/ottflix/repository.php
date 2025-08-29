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
 * repository for OttFlix.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\ottflix;

use Exception;

/**
 * Class repository
 *
 * @package mod_supervideo\ottflix
 */
class repository {
    /**
     * Call for list videos in ottflix.
     *
     * @param int $page
     * @param int $perpage
     * @param string $pathid
     * @param string $searchtitle
     * @param array $extensions
     * @return \stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function listing($page, $perpage, $pathid, $searchtitle, $extensions) {
        global $SESSION, $USER;

        $params = [
            "page" => $page,
            "perpage" => $perpage,
            "search-title" => $searchtitle,
            "path_id" => $pathid,
            "extensions" => implode(",", $extensions),
            "return-folders" => 1,
            "lang" => isset($SESSION->lang) ? $SESSION->lang : $USER->lang,
        ];

        $baseurl = "api/v1/assets";
        $json = self::load_ottfilx($baseurl, $params);

        return json_decode($json);
    }

    /**
     * Call for get player code.
     *
     * @param int $cmid
     * @param string $identifier
     * @param string $safetyplayer
     * @return string
     * @throws Exception
     */
    public static function getplayer($cmid, $identifier, $safetyplayer = "") {
        global $USER;

        $payload = [
            "identifier" => $identifier,
            "enrollment" => $cmid,
            "student_name" => fullname($USER),
            "student_email" => $USER->email,
            "safetyplayer" => $safetyplayer,
        ];

        $baseurl = "api/v1/assets/{$identifier}/player/";
        return self::load_ottfilx($baseurl, $payload);
    }

    /**
     * Call for get status.
     *
     * @param string $identifier
     * @return string
     * @throws Exception
     */
    public static function getstatus($identifier) {
        $baseurl = "api/v1/assets/{$identifier}/status/";
        return json_decode(self::load_ottfilx($baseurl, null));
    }

    /**
     * Function get
     *
     * @param string $metodth
     * @param array $params
     * @return bool|mixed
     * @throws Exception
     */
    public static function load_ottfilx($metodth, $params = []) {
        $config = get_config('supervideo');
        if (is_array($params)) {
            $params = http_build_query($params, '', '&');
        } else if (is_null($params)) {
            $params = "";
        }

        if (isset($config->ottflix_url[10]) && isset($config->ottflix_token[10])) {
            $curl = new \curl();
            $curl->setopt([
                'CURLOPT_HTTPHEADER' => [
                    "authorization:{$config->ottflix_token}",
                ],
            ]);

            $result = $curl->get("{$config->ottflix_url}{$metodth}?{$params}");
            return $result;
        }

        return false;
    }

    /**
     * Function is_enable
     *
     * @return bool
     */
    public static function is_enable() {
        return isset($config->ottflix_url[10]) && isset($config->ottflix_token[10]);
    }

    /**
     * Function ai
     *
     * @param $identifier
     * @param $itens
     * @return object
     * @throws Exception
     */
    public static function ai($identifier, $itens) {
        $baseurl = "api/v1/assets/{$identifier}/ai/{$itens}";

        return json_decode(self::load_ottfilx($baseurl, [
            "itens" => $itens,
        ]));
    }

    /**
     * Function ai
     *
     * @param string $identifier
     * @param string $ottflixia
     * @return object
     * @throws Exception
     */
    public static function h5p($identifier, $ottflixia) {
        $baseurl = "api/v1/assetsh5p/{$identifier}/player";

        return self::load_ottfilx($baseurl, ["ottflix_ia" => $ottflixia]);
    }
}
