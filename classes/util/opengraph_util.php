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
 * Secure Open Graph metadata reader.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\util;

use curl;
use DOMDocument;

/**
 * Secure Open Graph metadata reader.
 */
class opengraph_util {
    /** @var array */
    private $values = [];

    /**
     * Fetch metadata using Moodle's SSRF-aware curl wrapper.
     *
     * @param string $uri Public HTTP(S) URL.
     * @return opengraph_util|null
     */
    public static function fetch($uri) {
        global $CFG;

        if (!source_url_parser::is_http_url($uri)) {
            return null;
        }
        require_once($CFG->libdir . "/filelib.php");

        // Moodle's curl wrapper applies core\files\curl_security_helper to every
        // request and redirect. Do not set ignoresecurity and do not disable TLS.
        $curl = new curl();
        $curl->setHeader([
            "Accept: text/html,application/xhtml+xml",
            "action: opengraph",
        ]);
        $curl->setopt([
            "CURLOPT_TIMEOUT" => 15,
            "CURLOPT_CONNECTTIMEOUT" => 5,
            "CURLOPT_FOLLOWLOCATION" => true,
            "CURLOPT_MAXREDIRS" => 5,
            "CURLOPT_USERAGENT" => "Moodle mod_supervideo OpenGraph",
        ]);
        $html = $curl->get($uri);
        if ($curl->errno || !is_string($html) || $html === "") {
            return null;
        }

        // Avoid parsing unexpectedly large responses returned by a remote endpoint.
        if (strlen($html) > 2 * 1024 * 1024) {
            return null;
        }
        return self::parse($html);
    }

    /**
     * Parse Open Graph meta tags independent of attribute order.
     *
     * @param string $html HTML.
     * @return opengraph_util
     */
    private static function parse($html) {
        $result = new self();
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        foreach ($document->getElementsByTagName("meta") as $meta) {
            $property = strtolower(trim($meta->getAttribute("property")));
            if (strpos($property, "og:") !== 0) {
                continue;
            }
            $key = substr($property, 3);
            if ($key !== "" && !array_key_exists($key, $result->values)) {
                $result->values[$key] = trim($meta->getAttribute("content"));
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $result;
    }

    /**
     * Return one parsed Open Graph value.
     *
     * @param string $key Open Graph key without the og: prefix.
     * @return mixed|null
     */
    public function get($key) {
        return array_key_exists($key, $this->values) ? $this->values[$key] : null;
    }
}
