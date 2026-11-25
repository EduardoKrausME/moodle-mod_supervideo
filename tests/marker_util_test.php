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
 * Tests for marker parsing.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo;

use advanced_testcase;
use InvalidArgumentException;
use mod_supervideo\util\marker_util;

/**
 * Tests for marker parsing.
 */
final class marker_util_test extends advanced_testcase {

    /**
     * Marker times are normalised and sorted.
     *
     * @covers \mod_supervideo\util\marker_util::parse
     */
    public function test_parse_normalises_markers(): void {
        $value = "02:10 | Next activity\n30 | Question | skip\n01:30 | Answer";

        $this->assertSame([
            ["time" => 30, "label" => "Question", "skip" => true],
            ["time" => 90, "label" => "Answer", "skip" => false],
            ["time" => 130, "label" => "Next activity", "skip" => false],
        ], marker_util::parse($value, true));
    }

    /**
     * Shared marker configuration respects effective controls.
     *
     * @covers \mod_supervideo\util\marker_util::configuration
     */
    public function test_configuration(): void {
        $activity = (object)["markers" => "30 | Question | skip\n90 | Answer", "showcontrols" => 1];
        $config = marker_util::configuration($activity);
        $this->assertTrue($config["allowed"]);
        $this->assertSame(90, $config["points"][1]["time"]);
        $this->assertTrue($config["points"][0]["skip"]);
        $activity->showcontrols = 0;
        $this->assertFalse(marker_util::configuration($activity)["allowed"]);
        $this->assertSame($config["points"], marker_util::configuration($activity)["points"]);
        $activity->showcontrols = 1;
        $activity->origem = "link";
        $this->assertFalse(marker_util::configuration($activity, (object)["controls" => "play"])["allowed"]);
        $this->assertTrue(marker_util::configuration($activity, (object)["controls" => "play,progress"])["allowed"]);
        $activity->origem = "vimeo";
        $this->assertTrue(marker_util::configuration($activity, (object)["controls" => "play"])["allowed"]);
    }

    /**
     * Invalid marker lines fail strict parsing.
     *
     * @covers \mod_supervideo\util\marker_util::parse
     */
    public function test_parse_rejects_invalid_marker(): void {
        $this->expectException(InvalidArgumentException::class);
        marker_util::parse("00:75 | Invalid time", true);
    }

    /**
     * Duplicate marker times fail strict parsing.
     *
     * @covers \mod_supervideo\util\marker_util::parse
     */
    public function test_parse_rejects_duplicate_time(): void {
        $this->expectException(InvalidArgumentException::class);
        marker_util::parse("30 | Question\n00:30 | Answer", true);
    }
}
