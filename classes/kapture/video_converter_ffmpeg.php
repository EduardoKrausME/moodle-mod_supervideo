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
 * Convert kapture - FFMPEG.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo\kapture;

use Exception;

/**
 * Class convert
 */
class video_converter_ffmpeg {
    /**
     * Convert any input to MP4 (H.264 + AAC), then verify result and return stats.
     * Comments in English as requested.
     *
     * @param string $inputvideo Path to source file
     * @param string $outputvideo Path to target .mp4 file
     * @return array{
     *   ok: bool,
     *   duration_seconds?: float,
     *   error?: string
     * }
     * @throws Exception
     */
    public function convert_to_mp4(string $inputvideo, string $outputvideo): array {

        $ffmpeg = get_config("supervideo", "kapture_ffmpeg");
        // Build safe command (use escapeshellarg ALWAYS).
        $cmd = sprintf(
            '%s -y -hide_banner -loglevel info -i %s ' . // Video and audio settings (adjust as needed).
            '-c:v libx264 -preset medium -crf 23 ' . '-c:a aac -b:a 192k ' . // Make file streamable.
            '-movflags +faststart ' . // Overwrite output.
            '%s', escapeshellarg($ffmpeg), escapeshellarg($inputvideo), escapeshellarg($outputvideo)
        );

        // Run ffmpeg and capture both STDOUT/STDERR (ffmpeg logs to STDERR).
        [$exitcode, $convertlog] = $this->run($cmd);

        // Check if output file exists and has size > 0 even if exit code looks okay.
        $ok = ($exitcode === 0) && is_file($outputvideo) && filesize($outputvideo) > 0;

        // If not OK, return logs for diagnosis.
        if (!$ok) {
            return [
                'ok' => false,
                'ffmpeg_convert_log' => $convertlog,
                'error' => 'Conversion failed or produced empty file',
            ];
        }

        $ffmpeg = get_config("supervideo", "kapture_ffmpeg");
        // Duration by asking ffmpeg to inspect the output.
        $probecmd = sprintf(
            '%s -hide_banner -i %s -f null -', escapeshellarg($ffmpeg), escapeshellarg($outputvideo)
        );
        [$probeexit, $probelog] = $this->run($probecmd);

        // Parse "Duration: HH:MM:SS.xx" from ffmpeg banner.
        $durationseconds = null;
        if (preg_match('/Duration:\s*(\d{2}):(\d{2}):(\d{2}(?:\.\d+)?)/', $probelog, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            $s = (float) $m[3];
            $durationseconds = $h * 3600 + $i * 60 + $s;

            if ($durationseconds) {
                video_converter::completed($inputvideo, $outputvideo);
            }

            return [
                'ok' => true,
                'duration_seconds' => $durationseconds,
            ];
        }else {
            return [
                'ok' => false,
                'error' => 'Duration not found',
            ];
        }
    }

    /**
     * Run a shell command and return [exitCode, combinedOutput].
     * Uses proc_open to capture STDERR and STDOUT reliably.
     *
     * @param string $command
     * @return array
     */
    private function run(string $command): array {
        // Descriptors: 0=STDIN, 1=STDOUT, 2=STDERR.
        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($command, $desc, $pipes);
        if (!is_resource($proc)) {
            return [1, 'Failed to start process'];
        }

        // Close STDIN (we won't send anything).
        fclose($pipes[0]);

        // Read outputs.
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $status = proc_close($proc);
        // Ffmpeg often writes everything to STDERR; combine for convenience.
        $combined = trim($stdout . "\n" . $stderr);

        return [$status, $combined];
    }
}
