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
 * Tests for the video source URL parser.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo;

use advanced_testcase;
use mod_supervideo\util\source_url_parser;


/**
 * Tests for the central video source URL parser.
 */
final class source_url_parser_test extends advanced_testcase {
    /**
     * YouTube URLs are normalized and foreign hosts are rejected.
     *
     * @covers \mod_supervideo\util\source_url_parser::youtube_id
     */
    public function test_youtube_formats(): void {
        $this->assertSame("dQw4w9WgXcQ", source_url_parser::youtube_id("https://youtu.be/dQw4w9WgXcQ"));
        $this->assertSame("dQw4w9WgXcQ", source_url_parser::youtube_id("https://www.youtube.com/watch?v=dQw4w9WgXcQ"));
        $this->assertSame("dQw4w9WgXcQ", source_url_parser::youtube_id("https://www.youtube.com/shorts/dQw4w9WgXcQ"));
        $this->assertSame("", source_url_parser::youtube_id("https://example.com/watch?v=dQw4w9WgXcQ"));
    }

    /**
     * Google Drive URLs are normalized.
     *
     * @covers \mod_supervideo\util\source_url_parser::drive_id
     */
    public function test_drive_formats(): void {
        $this->assertSame("abc_123-X", source_url_parser::drive_id("https://drive.google.com/file/d/abc_123-X/view"));
        $this->assertSame("abc_123-X", source_url_parser::drive_id("https://drive.google.com/open?id=abc_123-X"));
        $this->assertSame("", source_url_parser::drive_id("https://example.com/file/d/abc_123-X/view"));
    }

    /**
     * Vimeo unlisted hashes are retained.
     *
     * @covers \mod_supervideo\util\source_url_parser::vimeo
     */
    public function test_vimeo_unlisted_hash(): void {
        $this->assertSame(
            ["id" => "123456789", "hash" => "abcdef"],
            source_url_parser::vimeo("https://vimeo.com/123456789/abcdef")
        );
        $this->assertNull(source_url_parser::vimeo("https://example.com/123456789"));
    }

    /**
     * Public, embedded and unlisted Vimeo URLs are accepted by the source validator.
     *
     * @covers \mod_supervideo\util\source_url_parser::vimeo
     * @covers \mod_supervideo\util\source_url_parser::is_valid
     */
    public function test_vimeo_source_validation(): void {
        $urls = [
            "https://vimeo.com/123456789" => "",
            "https://www.vimeo.com/123456789" => "",
            "https://player.vimeo.com/video/123456789" => "",
            "https://vimeo.com/123456789/abcdef" => "abcdef",
            "https://player.vimeo.com/video/123456789?h=abcdef" => "abcdef",
        ];
        foreach ($urls as $url => $hash) {
            $this->assertTrue(source_url_parser::is_valid("vimeo", $url), $url);
            $this->assertSame(["id" => "123456789", "hash" => $hash], source_url_parser::vimeo($url));
        }
        foreach (["", "https://vimeo.com/", "https://vimeo.com/notanid", "https://example.com/123456789"] as $url) {
            $this->assertFalse(source_url_parser::is_valid("vimeo", $url), $url);
        }
    }

    /**
     * HTTP source validation rejects unsupported schemes and detects HLS.
     *
     * @covers \mod_supervideo\util\source_url_parser::is_http_url
     * @covers \mod_supervideo\util\source_url_parser::is_hls
     */
    public function test_http_source_validation(): void {
        $this->assertTrue(source_url_parser::is_valid("embed", "https://example.com/player"));
        $this->assertFalse(source_url_parser::is_valid("embed", "javascript:alert(1)"));
        $this->assertTrue(source_url_parser::is_hls("https://cdn.example.com/video/playlist.m3u8?token=abc"));
        $this->assertTrue(source_url_parser::is_hls("https://cdn.example.com/stream/PLAYLIST.M3U8?token=abc&expires=123"));
        $this->assertFalse(source_url_parser::is_hls("https://cdn.example.com/video.mp4?format=m3u8"));
    }
}
