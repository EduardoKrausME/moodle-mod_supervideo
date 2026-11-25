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
 * Regression tests for Super Video activity form submission.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo;

use advanced_testcase;
use context_course;
use context_user;
use mod_supervideo_mod_form;
use stdClass;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot . "/course/modlib.php");
require_once($CFG->dirroot . "/mod/supervideo/mod_form.php");

/**
 * Exercises the actual Moodle form, including core draft file validation.
 *
 * @package mod_supervideo
 * @covers \mod_supervideo_mod_form
 */
final class mod_form_test extends advanced_testcase {
    /**
     * Prepare a course and page for the real activity settings form.
     *
     * @return stdClass
     */
    private function prepare_course(): stdClass {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 0]);
        $PAGE->set_course($course);
        $PAGE->set_context(context_course::instance($course->id));
        $PAGE->set_url("/course/modedit.php", ["add" => "supervideo", "course" => $course->id, "section" => 0]);
        return $course;
    }

    /**
     * Submit a real activity form using the same preparation as course/modedit.php.
     *
     * @param stdClass $course Course.
     * @param array $values Submitted values.
     * @param stdClass|null $cm Existing course module when editing.
     * @return mod_supervideo_mod_form
     */
    private function submit_form(stdClass $course, array $values, ?stdClass $cm = null): mod_supervideo_mod_form {
        $_POST = [];
        $_GET = [];
        if ($cm) {
            [$cm, , , $data, $section] = get_moduleinfo_data($cm, $course);
            $data->update = $cm->id;
            $data->return = 0;
        } else {
            [, , $section, $cm, $data] = prepare_new_moduleinfo_data($course, "supervideo", 0);
            $data->add = "supervideo";
            $data->return = 0;
        }

        $submitted = array_replace((array)$data, [
            "name" => "Super Video regression",
            "origem" => "vimeo",
            "playersize" => "16x9",
            "grade_approval" => 0,
            "gradepass" => 0,
            "cmidnumber" => "",
            "availabilityconditionsjson" => "",
            "ottflix_ia" => [],
            "submitbutton2" => 1,
        ], $values);
        mod_supervideo_mod_form::mock_submit($submitted);
        $form = new mod_supervideo_mod_form($data, $section->section, $cm, $course);
        $form->set_data($data);
        return $form;
    }

    /**
     * A valid Vimeo URL must not require a local file during creation.
     */
    public function test_create_vimeo_without_upload(): void {
        $course = $this->prepare_course();
        $draftid = file_get_unused_draft_itemid();
        $url = "https://vimeo.com/123456789/abcdef";
        $form = $this->submit_form($course, ["videourl_vimeo" => $url, "videofile" => $draftid]);
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertNotNull($data);
        $this->assertSame("vimeo", $data->origem);
        $this->assertSame($url, $data->videourl_vimeo);
    }

    /**
     * Existing Vimeo activities may be edited without an uploaded file.
     */
    public function test_edit_vimeo_without_upload(): void {
        $course = $this->prepare_course();
        $video = $this->getDataGenerator()->create_module("supervideo", [
            "course" => $course->id,
            "origem" => "vimeo",
            "videourl" => "https://vimeo.com/123456789",
        ]);
        $cm = get_coursemodule_from_instance("supervideo", $video->id, $course->id, false, MUST_EXIST);
        $url = "https://player.vimeo.com/video/987654321?h=abcdef";
        $form = $this->submit_form($course, ["videourl_vimeo" => $url], $cm);
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertNotNull($data);
        $this->assertSame($url, $data->videourl_vimeo);
        $this->assertSame((int)$cm->id, (int)$data->coursemodule);
    }

    /**
     * Empty and foreign-host Vimeo URLs are rejected by the real form.
     */
    public function test_invalid_vimeo_urls(): void {
        $course = $this->prepare_course();
        foreach (["", "https://example.com/123456789"] as $url) {
            $form = $this->submit_form($course, [
                "videourl_vimeo" => $url,
                "videofile" => file_get_unused_draft_itemid(),
            ]);
            $this->assertFalse($form->is_validated(), $url);
            $this->assertNull($form->get_data());
        }
    }

    /**
     * Other external sources also work with an empty local draft.
     */
    public function test_other_external_sources_without_upload(): void {
        $course = $this->prepare_course();
        $sources = [
            "youtube" => "https://www.youtube.com/watch?v=M7lc1UVf-VE",
            "drive" => "https://drive.google.com/file/d/abc_123-X/view",
        ];
        foreach ($sources as $source => $url) {
            $form = $this->submit_form($course, [
                "origem" => $source,
                "videourl_{$source}" => $url,
                "videofile" => file_get_unused_draft_itemid(),
            ]);
            $this->assertTrue($form->is_validated(), $source);
            $this->assertNotNull($form->get_data());
        }
    }

    /**
     * Upload requires a real file in the current user's draft area.
     */
    public function test_upload_requires_file(): void {
        global $USER;
        $course = $this->prepare_course();
        $draftid = file_get_unused_draft_itemid();
        $values = ["origem" => "upload", "videofile" => $draftid];
        $form = $this->submit_form($course, $values);
        $this->assertFalse($form->is_validated());

        get_file_storage()->create_file_from_string([
            "contextid" => context_user::instance($USER->id)->id,
            "component" => "user",
            "filearea" => "draft",
            "itemid" => $draftid,
            "filepath" => "/",
            "filename" => "test.mp4",
        ], "test media fixture");
        $form = $this->submit_form($course, $values);
        $this->assertTrue($form->is_validated());
        $this->assertNotNull($form->get_data());
    }
}
