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
 * repository for Panda.
 *
 * @package   mod_supervideo
 * @copyright 2025 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\panda;

use dml_exception;
use Exception;

/**
 * Class Panda repository
 *
 * @package mod_supervideo\panda
 */
class repository {

    /** @var string */
    private static $baseurl = "https://api-v2.pandavideo.com.br";

    /** @var string */
    private static $basedataurl = "https://data.pandavideo.com";

    /**
     * oEmbed function
     *
     * @param $videoid
     * @return mixed
     * @throws dml_exception
     */
    public static function oembed($videoid) {
        $dashboard = urlencode("https://dashboard.pandavideo.com.br/videos/{$videoid}");
        $endpoint = "/oembed?url={$dashboard}";

        $ch = curl_init(self::$baseurl . $endpoint);
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
                return json_decode($body);
            case 400:
                throw new Exception("Bad request. Check the provided parameters.");
            case 401:
                throw new Exception("Unauthorized. Authentication failed or not provided.");
            case 404:
                throw new Exception("Not found. Videos or the API were not found.");
            case 500:
                throw new Exception("Internal server error. Please try again later.");
            default:
                throw new Exception("Unexpected error. HTTP code: {$status}");
        }
    }

    /**
     * List videos
     *
     * @param $page
     * @param $limit
     * @param $title
     *
     * @return array
     *
     * @throws dml_exception
     * @throws Exception
     */
    public static function get_videos($page = 0, $limit = 100, $title = "") {
        $params = [];
        if ($page) {
            $params[] = "page={$page}";
        }
        if ($limit) {
            $params[] = "limit={$limit}";
        }
        if ($title) {
            $params[] = "title=" . urlencode($title);
        }
        if ($params) {
            $endpoint = "/videos?" . implode("&", $params);
        } else {
            $endpoint = "/videos";
        }

        $response = self::http_get($endpoint, self::$baseurl);
        return $response;
    }

    /**
     * Get video properties
     *
     * @param string $videoid
     *
     * @return \stdClass
     *
     * @throws dml_exception
     */
    public static function get_video_properties($videoid) {
        $endpoint = "/videos/{$videoid}";
        $response = self::http_get($endpoint, self::$baseurl);
        return $response;
    }

    /**
     * Get analytics from video
     *
     * @param $videoid
     * @return mixed
     * @throws dml_exception
     */
    public static function get_analytics_from_video($videoid) {
        $endpoint = "/general/{$videoid}";
        $response = self::http_get($endpoint, self::$basedataurl);
        return $response;
    }

    /**
     * Get Bandwidth by Video
     *
     * @param $videoid
     * @param $startdate
     * @param $enddate
     * @return mixed
     * @throws dml_exception
     */
    public static function get_bandwidth_by_video($videoid, $startdate, $enddate) {
        $endpoint = "/analytics/traffic";
        $response = self::http_get($endpoint, self::$baseurl);
        return $response;
    }

    /**
     * http_get
     *
     * @param string $endpoint
     *
     * @throws \dml_exception
     * @throws Exception
     */
    private static function http_get($endpoint,  $baseurl) {

        $config = get_config("supervideo");

        if (!isset($config->panda_token[20])) {
            throw new Exception("<h2>Token is missing!</h2>" . get_string("panda_token_desc", "mod_supervideo"));
        }

        $url = self::$baseurl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "accept: application/json",
            "Authorization: {$config->panda_token}",
        ]);

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
                return json_decode($body);
            case 400:
                throw new Exception("Bad request. Check the provided parameters.");
            case 401:
                throw new Exception("Unauthorized. Authentication failed or not provided.");
            case 404:
                throw new Exception("Not found. Videos or the API were not found.");
            case 500:
                throw new Exception("Internal server error. Please try again later.");
            default:
                throw new Exception("Unexpected error. HTTP code: {$status}");
        }
    }
}
