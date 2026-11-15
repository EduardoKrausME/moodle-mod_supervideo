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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace mod_supervideo;

use advanced_testcase;
use context_module;
use mod_supervideo\local\orphaned_views;
use mod_supervideo\privacy\provider;

/**
 * Viewing-history reset, deletion and orphan detection tests.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_lifecycle_test extends advanced_testcase {
    /**
     * Create a Super Video module with no gradebook side effects.
     *
     * @param int $courseid Course ID.
     * @return \stdClass
     */
    private function create_video(int $courseid): \stdClass {
        return $this->getDataGenerator()->create_module('supervideo', [
            'course' => $courseid,
            'name' => 'Video for viewing history tests',
            'origem' => 'link',
            'videourl' => 'https://example.com/video.mp4',
            'ottflix_ia' => [],
            'grade_approval' => 0,
            'completionpercent' => 0,
        ]);
    }

    /**
     * Create a recorded viewing for a user and course module.
     *
     * @param int $cmid Course module ID.
     * @param int $userid User ID.
     * @return int
     */
    private function add_view(int $cmid, int $userid): int {
        global $DB;

        return $DB->insert_record('supervideo_view', (object)[
            'cm_id' => $cmid,
            'user_id' => $userid,
            'currenttime' => 30,
            'duration' => 100,
            'percent' => 30,
            'map' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Resetting one course deletes only its views when selected.
     *
     * @covers ::supervideo_reset_userdata
     */
    public function test_course_reset_respects_option_and_course_boundary(): void {
        global $DB;

        $this->resetAfterTest();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $video1 = $this->create_video($course1->id);
        $video2 = $this->create_video($course2->id);
        $cm1 = get_coursemodule_from_instance('supervideo', $video1->id);
        $cm2 = get_coursemodule_from_instance('supervideo', $video2->id);
        $this->add_view($cm1->id, $user->id);
        $this->add_view($cm2->id, $user->id);

        $this->assertSame(['reset_supervideo_views' => 1], supervideo_reset_course_form_defaults($course1));

        $this->assertSame([], supervideo_reset_userdata((object)[
            'courseid' => $course1->id,
            'reset_supervideo_views' => 0,
        ]));
        $this->assertSame(2, $DB->count_records('supervideo_view'));

        $status = supervideo_reset_userdata((object)[
            'courseid' => $course1->id,
            'reset_supervideo_views' => 1,
        ]);
        $this->assertCount(1, $status);
        $this->assertSame(0, $DB->count_records('supervideo_view', ['cm_id' => $cm1->id]));
        $this->assertSame(1, $DB->count_records('supervideo_view', ['cm_id' => $cm2->id]));
    }

    /**
     * Removing an instance removes its views without touching other modules.
     *
     * @covers ::supervideo_delete_instance
     */
    public function test_delete_instance_removes_views(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $video1 = $this->create_video($course->id);
        $video2 = $this->create_video($course->id);
        $cm1 = get_coursemodule_from_instance('supervideo', $video1->id);
        $cm2 = get_coursemodule_from_instance('supervideo', $video2->id);
        $this->add_view($cm1->id, $user->id);
        $this->add_view($cm2->id, $user->id);

        $this->assertTrue(supervideo_delete_instance($video1->id));
        $this->assertSame(0, $DB->count_records('supervideo_view', ['cm_id' => $cm1->id]));
        $this->assertSame(1, $DB->count_records('supervideo_view', ['cm_id' => $cm2->id]));
    }

    /**
     * Orphan detection catches missing course modules or module instances.
     *
     * @covers \mod_supervideo\local\orphaned_views::count
     * @covers \mod_supervideo\local\orphaned_views::get_batch
     */
    public function test_orphan_detection_and_batch_paging(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $video = $this->create_video($course->id);
        $cm = get_coursemodule_from_instance('supervideo', $video->id);
        $validid = $this->add_view($cm->id, $user->id);
        $orphanid = $this->add_view(987654321, $user->id);

        $this->assertSame(1, orphaned_views::count());
        $this->assertSame([$orphanid], orphaned_views::get_batch(0, 10));
        $this->assertSame([], orphaned_views::get_batch($orphanid, 10));

        $DB->delete_records('supervideo', ['id' => $video->id]);
        $this->assertSame(2, orphaned_views::count());
        $this->assertSame([$validid, $orphanid], orphaned_views::get_batch(0, 10));
    }

    /**
     * Privacy deletion uses the approved context even if its module is missing.
     *
     * @covers \mod_supervideo\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_privacy_deletion_does_not_require_module_record(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $video = $this->create_video($course->id);
        $cm = get_coursemodule_from_instance('supervideo', $video->id);
        $context = context_module::instance($cm->id);
        $this->add_view($cm->id, $user->id);

        $DB->delete_records('supervideo', ['id' => $video->id]);
        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('supervideo_view', ['cm_id' => $cm->id]));
    }
}
