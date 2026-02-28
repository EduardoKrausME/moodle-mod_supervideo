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
 * Task kapture video converter.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\task;

use core\task\scheduled_task;
use mod_supervideo\kapture\video_converter;

/**
 * Class kapture_video_converter
 */
class kapture_video_converter extends scheduled_task {

    /**
     * Get a descriptive name for the task
     *
     * @return string
     */
    public function get_name() {
        return "Kapture video converter";
    }

    /**
     * Do the job.
     */
    public function execute() {
        global $DB, $CFG;

        $sql = "
            SELECT *
              FROM {supervideo}
             WHERE origem   LIKE 'upload'
               AND converted   = 0
               AND videourl LIKE '%\.webm%'
          ORDER BY RAND()
             LIMIT 1";
        $supervideo = $DB->get_record_sql($sql);
        echo '<pre>$supervideo: ';
        print_r($supervideo);
        echo '</pre>';
        if ($supervideo) {
            $sql = "
                SELECT *
                  FROM {files}
                 WHERE itemid = {$supervideo->id}
                   AND component   = 'mod_supervideo'
                   AND filearea    = 'content'
                   AND filename LIKE '%webm'";
            $file = $DB->get_record_sql($sql);
            echo '<pre>$file: ';
            print_r($file);
            echo '</pre>';

            if ($file) {
                $filepath = "{$CFG->dataroot}/filedir/" .
                    substr($file->contenthash, 0, 2) . "/" .
                    substr($file->contenthash, 2, 2) . "/" .
                    $file->contenthash;

                $tempdir = make_temp_directory("supervideo");
                $tempdirwebm = "{$tempdir}/{$supervideo->id}.webm";
                $tempdirmp4 = "{$tempdir}/{$supervideo->id}.mp4";
                copy($filepath, $tempdirwebm);

                $videoconverter = new video_converter();
                $status = $videoconverter->convert_to_mp4($supervideo, $file->pathnamehash, $tempdirwebm, $tempdirmp4);
                echo '<pre>$status: ';
                var_dump($status);
                echo '</pre>';
            }
        }
    }
}
