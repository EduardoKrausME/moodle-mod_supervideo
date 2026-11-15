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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Test data generator for Super Video activities.
 *
 * @package   mod_supervideo
 * @category  test
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates Super Video activity instances using the standard Moodle module API.
 *
 * @package   mod_supervideo
 * @category  test
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_supervideo_generator extends testing_module_generator {
    /**
     * Create a Super Video with defaults suitable for tests.
     *
     * @param array|stdClass|null $record Activity options.
     * @param array|null $options Course module options.
     * @return stdClass Created activity including cmid.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $record->origem = $record->origem ?? 'link';
        $record->videourl = $record->videourl ?? 'https://example.com/video.mp4';
        $record->ottflix_ia = $record->ottflix_ia ?? [];
        $record->grade_approval = $record->grade_approval ?? 0;
        $record->completionpercent = $record->completionpercent ?? 0;

        return parent::create_instance($record, $options);
    }
}
