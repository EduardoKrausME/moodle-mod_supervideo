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
 * Convert video kapture.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\kapture;

use Exception;
use stdClass;

/**
 * Class convert
 */
class video_converter {

    /**
     * Convert any input to MP4 (H.264 + AAC), then verify result and return stats.
     * Comments in English as requested.
     *
     * @param stdClass $supervideo
     * @param string $pathnamehash
     * @param string $inputpath Path to source file
     * @param string $outputpath Path to target .mp4 file
     * @return array{
     *   ok: bool,
     *   duration_hhmmss?: string,
     *   duration_seconds?: float,
     *   error?: string
     * }
     * @throws Exception
     */
    public function convert_to_mp4(stdClass $supervideo, string $pathnamehash, string $inputpath, string $outputpath): array {
        if (!is_file($inputpath)) {
            return [
                'ok' => false,
                'ffmpeg_convert_log' => '',
                'error' => 'Input file not found',
            ];
        }

        // Ensure output directory exists.
        $outdir = dirname($outputpath);
        if (!is_dir($outdir) && !@mkdir($outdir, 0775, true)) {
            return [
                'ok' => false,
                'ffmpeg_convert_log' => '',
                'error' => 'Unable to create output directory',
            ];
        }

        $kaptureconvert = get_config("supervideo", "kapture_convert");
        if ($kaptureconvert == "ffmpeg") {
            $kaptureffmpeg = get_config("supervideo", "kapture_ffmpeg");
            if (isset($kaptureffmpeg[5])) {
                $videoconverterffmpeg = new video_converter_ffmpeg();
                $status = $videoconverterffmpeg->convert_to_mp4($inputpath, $outputpath);

                if ($status["ok"]) {
                    self::completed($supervideo, $pathnamehash, $outputpath);
                }

                return $status;
            }
        } else if ($kaptureconvert == "qencode") {
            $kaptureqencode = get_config("supervideo", "kapture_qencode");
            if (isset($kaptureqencode[5])) {
                $videoconverterffmpeg = new video_converter_qencode();
                $status = $videoconverterffmpeg->convert_to_mp4($inputpath, $outputpath);

                if ($status["ok"]) {
                    self::completed($supervideo, $pathnamehash, $outputpath);
                }

                return $status;
            }
        }

        return ["ok" => false];
    }

    /**
     * @param stdClass $supervideo
     * @param string $pathnamehash
     * @param string $outputpath
     * @return void
     * @throws Exception
     */
    public static function completed($supervideo, $pathnamehash, $outputpath) {
        global $CFG, $DB;
        require_once( "{$CFG->libdir}/filelib.php");

        $fs = get_file_storage();
        $file = $fs->get_file_by_hash($pathnamehash);
        $filename = str_replace(".webm", ".mp4", $file->get_filename());

        $fileinfo = [
            "contextid" => $file->get_contextid(),
            "component" => "mod_mymodule",
            "filearea" => "content",
            "itemid" => $supervideo->id,
            "filepath" => "/",
            "filename" => $filename,
        ];
        $newfile = $fs->create_file_from_pathname($fileinfo, $outputpath);

        $file->delete();

        $savesupervideo = (object) [
            "id" => $supervideo->id,
            "converted" => 1,
            "videourl" => "[resource-file:{$filename}]",
        ];
        $DB->update_record("supervideo", $savesupervideo);
    }
}
