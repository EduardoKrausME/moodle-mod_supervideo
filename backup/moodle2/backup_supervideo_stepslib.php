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
 * Backup files
 *
 * @package   mod_supervideo
 * @category  backup
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete supervideo structure for backup, with file and id annotations
 */
class backup_supervideo_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module
     *
     * @return backup_nested_element
     * @throws base_element_struct_exception
     * @throws base_step_exception
     */
    protected function define_structure() {

        // Get know if we are including userinfo.
        $userinfo = $this->get_setting_value("userinfo");

        // Define the root element describing the supervideo instance.
        $supervideo = new backup_nested_element("supervideo",
            ["id"],
            [
                "course",
                "name",
                "intro",
                "introformat",
                "origem",
                "videourl",
                "playersize",
                "showcontrols",
                "autoplay",
                "grade_approval",
                "completionpercent",
            ]);

        // Define data sources.
        $supervideo->set_source_table("supervideo", ["id" => backup::VAR_ACTIVITYID]);

        // Define file annotations.
        $supervideo->annotate_files("mod_supervideo", "intro", null);
        $supervideo->annotate_files("mod_supervideo", "content", null);

        // Return the root element (supervideo), wrapped into standard activity structure.
        return $this->prepare_activity_structure($supervideo);
    }
}
