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
 * Structure step to restore one supervideo activity
 */
class restore_supervideo_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {

        $paths = [];
        $paths[] = new restore_path_element("supervideo", "/activity/supervideo");
        $paths[] = new restore_path_element("supervideo_caption", "/activity/supervideo/captions/caption");

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data
     *
     * @param array $data parsed element data
     *
     * @throws dml_exception
     * @throws base_step_exception
     */
    protected function process_supervideo($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        if (empty($data->timecreated)) {
            $data->timecreated = time();
        } else {
            $data->timecreated = $this->apply_date_offset($data->timecreated);
        }

        if (empty($data->timemodified)) {
            $data->timemodified = time();
        } else {
            $data->timemodified = $this->apply_date_offset($data->timemodified);
        }

        if (isset($data->grade) && $data->grade < 0) {
            // Scale found, get mapping.
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        // Create the supervideo instance.
        $newitemid = $DB->insert_record('supervideo', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore each caption and retain the old-to-new file mapping.
     *
     * @param array $data Caption data.
     */
    protected function process_supervideo_caption($data) {
        global $DB;
        $record = (object)$data;
        $oldid = $record->id;
        unset($record->id);
        $record->supervideoid = $this->get_new_parentid("supervideo");
        $record->timecreated = $this->apply_date_offset($record->timecreated);
        $record->timemodified = $this->apply_date_offset($record->timemodified);
        $newid = $DB->insert_record("supervideo_caption", $record);
        $this->set_mapping("supervideo_caption", $oldid, $newid, true);
    }

    /**
     * Post-execution actions
     */
    protected function after_execute() {
        global $DB;

        // Add supervideo related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_supervideo', 'intro', null);
        $this->add_related_files("mod_supervideo", "content", null);
        $this->add_related_files("mod_supervideo", "captions", "supervideo_caption");

        $fs = get_file_storage();
        $contextid = $this->task->get_contextid();
        $activityid = $this->task->get_activityid();
        $files = $DB->get_records("files", [
            "contextid" => $contextid, "component" => "mod_supervideo", "filearea" => "content",
        ]);
        foreach ($files as $file) {
            if ((int)$file->itemid === (int)$activityid) {
                continue;
            }
            $file->itemid = $activityid;
            $file->pathnamehash = $fs->get_pathname_hash(
                $contextid, $file->component, $file->filearea, $file->itemid, $file->filepath, $file->filename);

            $DB->update_record("files", $file);
        }
    }
}
