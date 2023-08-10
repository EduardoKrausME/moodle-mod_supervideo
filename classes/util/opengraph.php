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
 * User: Eduardo Kraus
 * Date: 09/08/2023
 * Time: 17:42
 */

namespace mod_supervideo\util;

use DOMDocument;

class opengraph {
    /**
     * There are base schema's based on type, this is just
     * a map so that the schema can be obtained
     *
     */
    public static $types = array(
        'activity' => array('activity', 'sport'),
        'business' => array('bar', 'company', 'cafe', 'hotel', 'restaurant'),
        'group' => array('cause', 'sports_league', 'sports_team'),
        'organization' => array('band', 'government', 'non_profit', 'school', 'university'),
        'person' => array('actor', 'athlete', 'author', 'director', 'musician', 'politician', 'public_figure'),
        'place' => array('city', 'country', 'landmark', 'state_province'),
        'product' => array('album', 'book', 'drink', 'food', 'game', 'movie', 'product', 'song', 'tv_show'),
        'website' => array('blog', 'website'),
    );

    /**
     * Holds all the Open Graph values we've parsed from a page
     *
     */
    private $values = array();

    /**
     * Fetches a URI and parses it for Open Graph data, returns
     * false on error.
     *
     * @param String $uri URI to page to parse for Open Graph data
     * @return opengraph
     */
    public static function fetch($uri) {
        $ch = curl_init($uri);

        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['action: opengraph']);

        $response = curl_exec($ch);

        curl_close($ch);

        if (!empty($response)) {
            return self::parse($response);
        } else {
            return null;
        }
    }

    /**
     * Parses HTML and extracts Open Graph data, this assumes
     * the document is at least well formed.
     *
     * @param String $html HTML to parse
     * @return opengraph
     */
    private static function parse($html) {
        $oldlibxmlerror = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML($html);

        libxml_use_internal_errors($oldlibxmlerror);

        $tags = $doc->getElementsByTagName('meta');
        if (!$tags || $tags->length === 0) {
            return null;
        }

        $page = new self();

        $nonogdescription = null;

        /** @var \DOMElement $tag */
        foreach ($tags as $tag) {
            if ($tag->hasAttribute('property') &&
                strpos($tag->getAttribute('property'), 'og:') === 0) {
                $key = strtr(substr($tag->getAttribute('property'), 3), '-', '_');
                $page->values[$key] = $tag->getAttribute('content');
            }

            // Added this if loop to retrieve description values from sites like the New York Times who have malformed it.
            if ($tag->hasAttribute('value') && $tag->hasAttribute('property') &&
                strpos($tag->getAttribute('property'), 'og:') === 0) {
                $key = strtr(substr($tag->getAttribute('property'), 3), '-', '_');
                $page->values[$key] = $tag->getAttribute('value');
            }

            if ($tag->hasAttribute('name') && $tag->getAttribute('name') === 'description') {
                $nonogdescription = $tag->getAttribute('content');
            }

        }
        if (!isset($page->values['title'])) {
            $titles = $doc->getElementsByTagName('title');
            if ($titles->length > 0) {
                $page->values['title'] = $titles->item(0)->textContent;
            }
        }
        if (!isset($page->values['description']) && $nonogdescription) {
            $page->values['description'] = $nonogdescription;
        }

        // Fallback to use image_src if ogp::image isn't set.
        if (!isset($page->values['image'])) {
            $domxpath = new \DOMXPath($doc);
            $elements = $domxpath->query("//link[@rel='image_src']");

            if ($elements->length > 0) {
                $domattr = $elements->item(0)->attributes->getNamedItem('href');
                if ($domattr) {
                    $page->values['image'] = $domattr->value;
                    $page->values['image_src'] = $domattr->value;
                }
            }
        }

        if (empty($page->values)) {
            return null;
        }

        return $page;
    }

    /**
     * Helper method to access attributes directly
     * Example:
     * $graph->title
     *
     * @param String $key Key to fetch from the lookup
     * @return int|mixed|string
     */
    public function get($key) {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        if ($key === 'schema') {
            foreach (self::$types as $schema => $types) {
                if (array_search($this->values['type'], $types)) {
                    return $schema;
                }
            }
        }

        return null;
    }

    /**
     * Return all the keys found on the page
     *
     * @return array
     */
    public function keys() {
        return array_keys($this->values);
    }

    /**
     * Helper method to check an attribute exists
     *
     * @param $key
     * @return bool
     */
    public function __isset($key) {
        return array_key_exists($key, $this->values);
    }

    /**
     * Will return true if the page has location data embedded
     *
     * @return boolean Check if the page has location data
     */
    public function haslocation() {
        if (array_key_exists('latitude', $this->values) && array_key_exists('longitude', $this->values)) {
            return true;
        }

        $addresskeys = array('street_address', 'locality', 'region', 'postal_code', 'country_name');
        $validaddress = true;
        foreach ($addresskeys as $key) {
            $validaddress = ($validaddress && array_key_exists($key, $this->values));
        }
        return $validaddress;
    }

    /**
     * Iterator code
     */
    private $position = 0;

    public function rewind() {
        reset($this->values);
        $this->position = 0;
    }

    public function current() {
        return current($this->values);
    }

    public function key() {
        return key($this->values);
    }

    public function next() {
        next($this->values);
        ++$this->position;
    }

    public function valid() {
        return $this->position < count($this->values);
    }
}
