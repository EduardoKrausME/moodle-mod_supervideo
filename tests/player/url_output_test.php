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
 * Regression tests for contextual URL escaping in player output.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\player;

use advanced_testcase;
use DOMDocument;
use DOMElement;

/**
 * Test the actual Moodle Mustache output, not an approximation of its escape function.
 */
final class url_output_test extends advanced_testcase {
    /**
     * Read the rendered iframe element as the browser parses it.
     *
     * @param string $html Rendered HTML.
     * @return DOMElement
     */
    private function iframe(string $html): DOMElement {
        $document = new DOMDocument();
        $this->assertTrue($document->loadHTML(
            "<meta charset=\"UTF-8\">" . $html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        ));
        $this->assertSame(1, $document->getElementsByTagName("iframe")->length);
        return $document->getElementsByTagName("iframe")->item(0);
    }

    /**
     * A URL is escaped at the HTML boundary, even if a caller bypasses validation.
     *
     * @covers \mod_supervideo\player\embed_provider::render
     * @covers \mod_supervideo\player\pandavideo_provider::render
     */
    public function test_iframe_attributes(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $this->setAdminUser();
        $url = "https://cdn.example.com/player?signature=a%2Fb%3D&literal=&amp;&name=O'Reilly";
        $invalid = "https://example.com/player\" data-test=\"unexpected";
        foreach (["embed_iframe" => "videourl", "embed_pandavideo" => "video_player"] as $template => $key) {
            foreach ([$url, $invalid] as $value) {
                $html = $OUTPUT->render_from_template("mod_supervideo/" . $template, [
                    "elementid" => "supervideo-test",
                    $key => $value,
                ]);
                $iframe = $this->iframe($html);
                $this->assertSame($value, $iframe->getAttribute("src"));
                $this->assertFalse($iframe->hasAttribute("data-test"));
            }
        }
    }

    /**
     * Drive and Vimeo build raw query strings and let Mustache escape them once.
     *
     * @covers \mod_supervideo\player\drive_provider::render
     * @covers \mod_supervideo\player\vimeo_provider::render
     */
    public function test_provider_queries(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_course($course);
        $PAGE->set_url("/mod/supervideo/view.php");
        $cm = (object)["id" => 1];
        $view = (object)["id" => 1, "currenttime" => 0];
        $activity = (object)["videourl" => "https://vimeo.com/123456789/abcdef", "showcontrols" => 1,
            "autoplay" => 0, "playersize" => "16x9", "origem" => "vimeo"];
        $context = new player_context($cm, $course, $activity, \context_course::instance($course->id), new \stdClass(), $view);
        $vimeo = new vimeo_provider($context);
        $url = $this->iframe($vimeo->render()->html)->getAttribute("src");
        $this->assertStringStartsWith("https://player.vimeo.com/video/123456789?", $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame("abcdef", $query["h"]);
        $this->assertSame("1", $query["controls"]);
        $this->assertSame("1", $query["playsinline"]);
        $this->assertStringNotContainsString("&amp;", $url);

        $activity->videourl = "https://drive.google.com/file/d/abc_123-X/view";
        $activity->origem = "drive";
        $drive = new drive_provider($context);
        $url = $this->iframe($drive->render()->html)->getAttribute("src");
        $this->assertSame("https://drive.google.com/file/d/abc_123-X/preview?controls=1&autoplay=0", $url);
    }

    /**
     * Direct media URLs remain raw when read back from their HTML data attribute.
     *
     * @covers \mod_supervideo\player\html5_provider::render
     */
    public function test_media_data_attribute(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $this->setAdminUser();
        $url = "https://cdn.example.com/movie.m3u8?signature=a%2Fb%3D&expires=123";
        $html = $OUTPUT->render_from_template("mod_supervideo/embed_div", [
            "elementid" => "supervideo-test",
            "videourl" => $url,
        ]);
        $document = new DOMDocument();
        $this->assertTrue($document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING));
        $xpath = new \DOMXPath($document);
        $this->assertSame($url, $xpath->query("//*[@data-videourl]")->item(0)->getAttribute("data-videourl"));
    }
}
