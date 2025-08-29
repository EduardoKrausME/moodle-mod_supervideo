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
 * repository for Panda Vídeo.
 *
 * @package   mod_supervideo
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\pandavideo;

use Exception;

/**
 * Class Panda Vídeo repository
 *
 * @package mod_supervideo\pandavideo
 */
class repository {
    /**
     * oEmbed function
     *
     * @param $videourl
     * @return mixed
     * @throws Exception
     */
    public static function oembed($videourl) {
        $dashboard = urlencode("https://dashboard.pandavideo.com.br/videos/{$videourl}");
        $baseurl = "https://api-v2.pandavideo.com.br";
        $ch = curl_init("{$baseurl}/oembed?url={$dashboard}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            throw new Exception("Unexpected error.");
        }

        $headersize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $body = substr($response, $headersize);

        curl_close($ch);

        switch ($status) {
            case 200:
                $pandavideo = json_decode($body);
                if (!isset($pandavideo->id)) {
                    $pandavideo->id = md5($videourl);
                }
                return $pandavideo;
            case 400:
                throw new Exception("Bad request. Check the provided parameters.");
            case 401:
                throw new Exception("Unauthorized. Authentication failed or not provided.");
            case 404:
                throw new Exception("Not found. Video were not found.");
            case 500:
                throw new Exception("Internal server error. Please try again later.");
            default:
                throw new Exception("Unexpected error. HTTP code: {$status}");
        }
    }
}
