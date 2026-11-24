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
 * Privacy metadata regression tests.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\privacy;

use advanced_testcase;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\external_location;

/**
 * Tests for local and external privacy metadata.
 *
 * @package mod_supervideo
 * @covers \mod_supervideo\privacy\provider::get_metadata
 */
final class provider_test extends advanced_testcase {
    /**
     * Metadata includes the local viewing table and all external data fields.
     */
    public function test_get_metadata(): void {
        $collection = new collection("mod_supervideo");
        $this->assertSame($collection, provider::get_metadata($collection));

        $items = [];
        foreach ($collection->get_collection() as $item) {
            $items[$item->get_name()] = $item;
        }

        $this->assertArrayHasKey("supervideo_view", $items);
        $this->assertInstanceOf(database_table::class, $items["supervideo_view"]);
        $localfields = [
            "cm_id",
            "user_id",
            "currenttime",
            "duration",
            "percent",
            "map",
            "timecreated",
            "timemodified",
        ];
        $this->assertEqualsCanonicalizing($localfields, array_keys($items["supervideo_view"]->get_privacy_fields()));

        $this->assertArrayHasKey("ottflix", $items);
        $external = $items["ottflix"];
        $this->assertInstanceOf(external_location::class, $external);
        $this->assertSame("privacy:metadata:ottflix", $external->get_summary());
        $expected = [
            "enrollment" => "privacy:metadata:ottflix:enrollment",
            "student_name" => "privacy:metadata:ottflix:student_name",
            "student_email" => "privacy:metadata:ottflix:student_email",
            "safetyplayer" => "privacy:metadata:ottflix:safetyplayer",
            "identifier" => "privacy:metadata:ottflix:identifier",
        ];
        $this->assertSame($expected, $external->get_privacy_fields());

        $strings = get_string_manager();
        foreach (array_merge(array_values($expected), [$external->get_summary(), "ottflix_privacy_desc"]) as $key) {
            $this->assertTrue($strings->string_exists($key, "mod_supervideo"), $key);
        }
    }

    /**
     * External metadata is declared even when the integration is not configured.
     */
    public function test_metadata_without_ottflix_configuration(): void {
        $this->resetAfterTest();
        unset_config("ottflix_url", "supervideo");
        unset_config("ottflix_token", "supervideo");

        $collection = provider::get_metadata(new collection("mod_supervideo"));
        $external = [];
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof external_location) {
                $external[] = $item->get_name();
            }
        }
        $this->assertContains("ottflix", $external);
    }
}
