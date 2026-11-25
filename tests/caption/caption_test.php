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
 * Tests for caption validation, persistence, access and backup.
 *
 * @package mod_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\caption;

use advanced_testcase;
use context_module;
use context_user;
use InvalidArgumentException;
use mod_supervideo\service\caption;

/**
 * Regression coverage for activity-owned captions.
 */
final class caption_test extends advanced_testcase {
    /** @var string Test VTT with overlapping cues and safe WebVTT markup. */
    private const VTT = "WEBVTT\n\none\n00:00:01.000 --> 00:00:04.000 align:start\n<b>Olá</b>\nsecond line\n\n" .
        "00:00:02.000 --> 00:00:05.000\n<v Teacher>Example</v>\n";

    /**
     * Validate real WebVTT timestamps and header, including BOM and CRLF.
     *
     * @covers \mod_supervideo\caption\validator
     */
    public function test_webvtt(): void {
        validator::webvtt("\xef\xbb\xbf" . str_replace("\n", "\r\n", self::VTT));
        $this->assertTrue(validator::url("https://cdn.example.com/caption.vtt?signature=abc&expires=123"));
        $this->assertTrue(validator::language("pt-BR"));
        $this->assertTrue(validator::language("zh-Hant-TW"));
        foreach (["javascript:alert(1)", "http://example.com/file.vtt", "https://user:pass@example.com/a.vtt"] as $url) {
            $this->assertFalse(validator::url($url));
        }
        foreach (["", "not a file", "WEBVTT\n\n00:00:05.000 --> 00:00:04.000\nwrong\n",
            "WEBVTT\n\n00:61.000 --> 00:62.000\nwrong\n"] as $invalid) {
            try {
                validator::webvtt($invalid);
                $this->fail("Invalid caption accepted");
            } catch (InvalidArgumentException $exception) {
                $this->assertSame("caption_invalidfile", $exception->getMessage());
            }
        }
    }

    /**
     * Create a local draft in the current user context.
     *
     * @return int
     */
    private function draft(): int {
        global $USER;
        $id = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            "contextid" => context_user::instance($USER->id)->id,
            "component" => "user", "filearea" => "draft", "itemid" => $id,
            "filepath" => "/", "filename" => "caption.vtt",
        ], self::VTT);
        return $id;
    }

    /**
     * Create a fixture with one file and one HTTPS track through the manager.
     *
     * @return array
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module("supervideo", ["course" => $course->id]);
        $cm = get_coursemodule_from_instance("supervideo", $activity->id, $course->id, false, MUST_EXIST);
        $activity->coursemodule = $cm->id;
        $activity->captionform = 1;
        $activity->captioncount = 2;
        $activity->captionid = [0, 0];
        $activity->captionsource = ["file", "url"];
        $activity->captionlabel = ["Português", "Italiano"];
        $activity->captionlanguage = ["pt-BR", "it"];
        $activity->captionkind = ["captions", "subtitles"];
        $activity->captiondraft = [$this->draft(), 0];
        $activity->captionurl = ["", "https://cdn.example.com/italian.vtt?token=abc"];
        $activity->captiondefault = [1, 0];
        $activity->captionorder = [1, 2];
        $activity->transcriptenabled = 1;
        $activity->transcriptdownload = 1;
        manager::save($activity);
        return [$course, $activity, $cm];
    }

    /**
     * Verify local/remote storage, access ownership, flags and explicit deletion.
     *
     * @covers \mod_supervideo\caption\manager
     * @covers \mod_supervideo\caption\repository
     * @covers \mod_supervideo\service\caption
     */
    public function test_crud_and_access(): void {
        global $DB;
        [$course, $activity, $cm] = $this->fixture();
        $context = context_module::instance($cm->id);
        $rows = array_values(repository::all($activity->id));
        $this->assertCount(2, $rows);
        $this->assertSame(self::VTT, repository::file($cm, $context, $rows[0]->id)->get_content());
        $this->assertSame(self::VTT, caption::get($cm->id, $rows[0]->id)["text"]);
        $this->assertNull(repository::file($cm, $context, $rows[1]->id));
        $other = $this->getDataGenerator()->create_module("supervideo", ["course" => $course->id]);
        $othercm = get_coursemodule_from_instance("supervideo", $other->id, $course->id, false, MUST_EXIST);
        $this->assertNull(repository::file($othercm, context_module::instance($othercm->id), $rows[0]->id));

        $stored = $DB->get_record("supervideo", ["id" => $activity->id], "*", MUST_EXIST);
        $payload = repository::playback($stored, $context);
        $this->assertTrue($payload["enabled"]);
        $this->assertTrue($payload["download"]);
        $this->assertSame("https://cdn.example.com/italian.vtt?token=abc", $payload["tracks"][1]["url"]);

        $activity->captionid = [$rows[0]->id, $rows[1]->id];
        $activity->captiondelete = [1, 0];
        $activity->captionlabel[1] = "Italiano updated";
        manager::save($activity);
        $this->assertCount(1, repository::all($activity->id));
        $this->assertNull(repository::file($cm, $context, $rows[0]->id));
        $this->assertSame("Italiano updated", $DB->get_field("supervideo_caption", "label", ["id" => $rows[1]->id]));
        repository::delete_all($activity->id, $context->id);
        $this->assertEmpty(repository::all($activity->id));
    }

    /**
     * Reject multiple defaults, foreign caption ids and truncated edit rows.
     *
     * @covers \mod_supervideo\caption\manager::inspect
     */
    public function test_invalid_edits(): void {
        [$course, $activity, $cm] = $this->fixture();
        $rows = array_values(repository::all($activity->id));
        $activity->captionid = [$rows[0]->id, $rows[1]->id];
        $activity->captiondefault = [1, 1];
        [, $errors] = manager::inspect((array)$activity, $activity->id);
        $this->assertArrayHasKey("captiondefault[1]", $errors);
        $activity->captionid[0] = $rows[1]->id + 999999;
        [, $errors] = manager::inspect((array)$activity, $activity->id);
        $this->assertArrayHasKey("captionlabel[0]", $errors);
        $activity->captioncount = 1;
        [, $errors] = manager::inspect((array)$activity, $activity->id);
        $this->assertNotEmpty($errors);
    }

    /**
     * Restore files under new caption ids when duplicating the activity.
     *
     * @covers \backup_supervideo_activity_structure_step
     * @covers \restore_supervideo_activity_structure_step
     * @covers \mod_supervideo\caption\repository
     */
    public function test_duplicate_captions(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");
        [$course, $activity, $cm] = $this->fixture();
        $old = array_values(repository::all($activity->id));
        $newcm = duplicate_module($course, $cm);
        $this->assertNotNull($newcm);
        $new = array_values(repository::all($newcm->instance));
        $this->assertCount(2, $new);
        $this->assertNotEquals($old[0]->id, $new[0]->id);
        $context = context_module::instance($newcm->id);
        $this->assertSame(self::VTT, repository::file($newcm, $context, $new[0]->id)->get_content());
        $this->assertSame($old[1]->url, $new[1]->url);
        $this->assertEquals(1, $DB->get_field("supervideo", "transcriptenabled", ["id" => $newcm->instance]));
    }

    /**
     * An unenrolled user cannot read a local caption through the mobile service.
     *
     * @covers \mod_supervideo\service\caption::get
     */
    public function test_access_requires_course_permission(): void {
        [$course, $activity, $cm] = $this->fixture();
        $rows = array_values(repository::all($activity->id));
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        caption::get($cm->id, $rows[0]->id);
    }
}
