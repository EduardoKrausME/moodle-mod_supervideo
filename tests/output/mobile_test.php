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
 * Moodle App template regression tests.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\output;

use advanced_testcase;

/**
 * Tests the template returned by the native Moodle App handler.
 *
 * @package mod_supervideo
 * @covers \mod_supervideo\output\mobile
 */
final class mobile_test extends advanced_testcase {
    /**
     * Create an activity and obtain its rendered mobile template.
     *
     * @return array
     */
    private function render_mobile_activity(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $video = $this->getDataGenerator()->create_module("supervideo", [
            "course" => $course->id,
            "name" => "Mobile template test",
            "origem" => "link",
            "videourl" => "https://example.com/video.mp4",
            "ottflix_ia" => [],
            "grade_approval" => 0,
            "completionpercent" => 0,
        ]);
        $cm = get_coursemodule_from_instance("supervideo", $video->id, $course->id, false, MUST_EXIST);
        return [mobile::mobile_course_view(["cmid" => $cm->id]), $cm];
    }

    /**
     * Mustache removes comments and retains the Angular syntax and service bindings.
     */
    public function test_mobile_template_preserves_angular_expressions(): void {
        [$result, $cm] = $this->render_mobile_activity();
        $this->assertSame("main", $result["templates"][0]["id"]);
        $html = trim($result["templates"][0]["html"]);

        $this->assertStringStartsWith('<div class="mod-supervideo-mobile" id="mod-supervideo-mobile-' . $cm->id . '">', $html);
        $this->assertStringNotContainsString("Native Moodle App player", $html);
        $this->assertStringNotContainsString("<%", $html);
        $this->assertStringContainsString("{{ supervideoError }}", $html);
        $this->assertStringContainsString("{{ supervideoPercent }}", $html);
        $this->assertStringContainsString("{{ 'core.loading' | translate }}", $html);
        $this->assertStringContainsString('name="mod_supervideo_get_playback_data"', $html);
        $this->assertStringContainsString('name="mod_supervideo_view_supervideo"', $html);
        $this->assertStringContainsString("{cmid: {$cm->id}, supervideoid: {$cm->instance}}", $html);
        $this->assertStringContainsString("{getFromCache: 0, saveToCache: 0}", $html);
        $this->assertStringContainsString('id="mod-supervideo-api-player-' . $cm->id . '"', $html);
        $this->assertStringContainsString('id="mod-supervideo-frame-' . $cm->id . '"', $html);
        $this->assertStringContainsString('[addSiteReferer]="true"', $html);
        $this->assertSame(["cmid" => $cm->id], $result["otherdata"]);
    }

    /**
     * Browser fallback points to the same Moodle activity without plugin-generated credentials.
     */
    public function test_youtube_browser_fallback(): void {
        [$result, $cm] = $this->render_mobile_activity();
        $html = $result["templates"][0]["html"];
        $url = new \moodle_url("/mod/supervideo/view.php", ["id" => $cm->id]);
        $this->assertStringContainsString("href=\"" . $url->out() . "\"", $html);
        $this->assertStringContainsString("core-link", $html);
        $this->assertStringContainsString("[capture]=\"false\"", $html);
        $this->assertStringContainsString("[inApp]=\"false\"", $html);
        $this->assertStringContainsString("autoLogin=\"check\"", $html);
        $this->assertStringContainsString("supervideoYoutubeError | translate", $html);
        $this->assertStringContainsString("supervideoRetryYoutube()", $html);
        $this->assertStringNotContainsString("token=", $html);
    }

    /**
     * Native video and audio remain within the available player width.
     */
    public function test_native_media_is_responsive(): void {
        [$result, $cm] = $this->render_mobile_activity();
        $html = $result["templates"][0]["html"];

        $this->assertMatchesRegularExpression(
            '/<div class="mod-supervideo-mobile-player"[^>]*style="width:100%; min-width:0; max-width:100%;"[^>]*>/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<video\b[^>]*style="display:block; width:100%; max-width:100%; height:auto;"[^>]*>/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<audio\b[^>]*style="display:block; width:100%; max-width:100%;"[^>]*>/s',
            $html
        );
        $this->assertSame(2, substr_count($html, 'id="mod-supervideo-media-' . $cm->id . '"'));
        $this->assertSame(3, substr_count($html, '[src]="supervideoData.content.fileurl"'));
        $this->assertStringContainsString("playsinline", $html);
        $this->assertStringContainsString("mod-supervideo-hls-" . $cm->id, $html);
        $this->assertStringContainsString("supervideoHlsMode", $html);
        $this->assertStringContainsString("supervideoHlsError | translate", $html);
        $this->assertStringContainsString("[attr.controls]", $html);
        $this->assertStringContainsString("[attr.autoplay]", $html);
    }
}
