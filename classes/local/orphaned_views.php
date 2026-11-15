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

namespace mod_supervideo\local;

/**
 * Identifies viewing records that no longer have a valid Super Video activity.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class orphaned_views {
    /**
     * Shared relation and orphan condition for counts and batch reads.
     *
     * @return string
     */
    private static function from_sql(): string {
        return "FROM {supervideo_view} svv
           LEFT JOIN {course_modules} cm ON cm.id = svv.cm_id
           LEFT JOIN {modules} m ON m.id = cm.module AND m.name = :modname
           LEFT JOIN {supervideo} sv ON sv.id = cm.instance AND sv.course = cm.course
           LEFT JOIN {course} c ON c.id = cm.course
          WHERE (cm.id IS NULL OR m.id IS NULL OR sv.id IS NULL OR c.id IS NULL)";
    }

    /**
     * Count invalid viewing records without modifying them.
     *
     * @return int
     */
    public static function count(): int {
        global $DB;

        return (int)$DB->count_records_sql(
            "SELECT COUNT(1) " . self::from_sql(),
            ['modname' => 'supervideo']
        );
    }

    /**
     * Return the next ascending batch of invalid record IDs.
     *
     * @param int $afterid Last record ID already scanned.
     * @param int $limit Maximum number of records to return.
     * @return int[]
     */
    public static function get_batch(int $afterid, int $limit): array {
        global $DB;

        $sql = "SELECT svv.id " . self::from_sql() . "
                AND svv.id > :afterid
            ORDER BY svv.id ASC";
        $records = $DB->get_records_sql($sql, [
            'modname' => 'supervideo',
            'afterid' => $afterid,
        ], 0, $limit);

        return array_map('intval', array_keys($records));
    }
}
