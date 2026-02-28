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
 * Convert kapture - QEncode.
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
class video_converter_qencode {
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
     */
    public function convert_to_mp4(string $inputvideo, string $outputvideo): array {
        try {
            if (!file_exists($inputvideo) || !is_readable($inputvideo)) {
                throw new \InvalidArgumentException('Input file not found or not readable: ' . $inputvideo);
            }

            $filesize = filesize($inputvideo);
            if ($filesize === false || $filesize <= 0) {
                throw new Exception('Cannot determine input file size.');
            }

            // 1) Get access token
            $token = $this->getAccessToken();

            // 2) Create task (task_token + upload_url)
            [$taskToken, $uploadUrl] = $this->createTask($token);

            // 3) TUS: initiate upload (POST) to get Location
            $locationUrl = $this->tusInitiateUpload($uploadUrl, $taskToken, $inputvideo, $filesize);

            // 4) TUS: upload file data via PATCH
            $this->tusUploadFile($locationUrl, $inputvideo, $filesize);

            // Extract TUS file UUID from Location URL
            $fileUuid = $this->extractTusFileUuid($locationUrl);

            // 5) Start transcoding job to MP4
            $this->startEncode($taskToken, $fileUuid);

            // 6) Poll /v1/status until "status" => "completed"
            [$remoteUrl, $durationseconds] = $this->waitForCompletion($taskToken);

            // 7) Normalize URL (s3:// → https://) and download to $outputvideo
            $downloadUrl = $this->normalizeVideoUrl($remoteUrl);
            $this->downloadFile($downloadUrl, $outputvideo);

            return [
                "ok" => true,
                "duration_seconds" => (float) $durationseconds,
                "task_token" => $taskToken,
                "download_url" => $downloadUrl,
            ];
        } catch (\Throwable $e) {
            return [
                "ok" => false,
                "duration_seconds" => 0.0,
                "error" => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Qencode access token.
     */
    private function getAccessToken(): string {
        $data = $this->post("https://api.qencode.com/v1/access_token", [
            "api_key" => get_config("supervideo", "kapture_qencode"),
        ]);

        if (empty($data["token"])) {
            throw new Exception("Qencode access_token not returned.");
        }

        return $data["token"];
    }

    /**
     * Create transcoding task, returning [task_token, upload_url].
     *
     * @throws Exception
     */
    private function createTask(string $token): array {
        $data = $this->post("https://api.qencode.com/v1/create_task", [
            "token" => $token,
        ]);

        echo '<pre>POST: https://api.qencode.com/v1/create_task';
        print_r($data);
        echo '</pre>';

        if (!isset($data["error"]) || $data["error"] !== 0) {
            $desc = $data["error_description"] ?? "Unknown error";
            throw new Exception("Qencode create_task error: " . $desc);
        }

        if (empty($data["task_token"]) || empty($data["upload_url"])) {
            throw new Exception("Qencode create_task returned invalid response.");
        }

        return [$data["task_token"], $data["upload_url"]];
    }

    /**
     * TUS: initial POST to get upload Location header.
     *
     * @throws Exception
     */
    private function tusInitiateUpload(string $uploadUrl, string $taskToken, string $filePath, int $filesize): string {
        $url = rtrim($uploadUrl, "/") . "/{$taskToken}";
        $metadata = "filename " . base64_encode(basename($filePath));

        echo "<pre>POST: {$url}<br>Header: ";
        print_r([
            "Tus-Resumable: 1.0.0",
            "Upload-Length: {$filesize}",
            "Upload-Metadata: {$metadata}",
        ]);
        echo '</pre>';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_HTTPHEADER => [
                "Tus-Resumable: 1.0.0",
                "Upload-Length: {$filesize}",
                "Upload-Metadata: {$metadata}",
            ], CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        echo '<pre>$response: ';
        print_r(htmlentities($response));
        echo '</pre>';
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("TUS initiate upload error: {$err}");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headersRaw = substr($response, 0, $headerSize);
        $location = $this->extractHeaderValue($headersRaw, "Location");

        if (empty($location)) {
            throw new Exception("TUS server did not return Location header.");
        }

        return trim($location);
    }

    /**
     * TUS: send file content via PATCH to Location URL.
     */
    private function tusUploadFile(string $locationUrl, string $filePath, int $filesize): void {
        $fp = fopen($filePath, "rb");
        if ($fp === false) {
            throw new Exception("Cannot open input file for upload: {$filePath}");
        }

        $ch = curl_init($locationUrl);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => $filesize,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Tus-Resumable: 1.0.0", "Content-Length: {$filesize}",
                "Content-Type: application/offset+octet-stream",
                "Upload-Offset: 0",
            ], CURLOPT_TIMEOUT => 0, // no limit; controlled by overall system timeout
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            throw new Exception("TUS upload error: {$err}");
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($code < 200 || $code >= 300) {
            throw new Exception("TUS upload failed with HTTP {$code}.");
        }
    }

    /**
     * Extract the TUS file UUID from Location URL.
     * Example: https://storage.qencode.com/v1/upload_file/TASKTOKEN/FILEUUID
     */
    private function extractTusFileUuid(string $locationUrl): string {
        $parts = parse_url($locationUrl);
        $path = $parts["path"] ?? "";
        $segments = explode("/", trim($path, "/"));
        $fileUuid = end($segments);

        if (!$fileUuid) {
            throw new Exception("Cannot extract TUS file UUID from Location URL.");
        }

        return $fileUuid;
    }

    /**
     * Start encoding via /v1/start_encode2 with MP4 output.
     */
    private function startEncode(string $taskToken, string $fileUuid): void {
        // Query object: simple "convert to mp4" keeping original size.
        $queryObject = [
            "query" => [
                "source" => "tus:{$fileUuid}",
                "format" => [
                    [
                        "output" => "mp4", // You can add more params like bitrate, size, etc. if needed.
                        "video_codec" => "libx264",
                    ],
                ],
            ],
        ];

        $data = $this->post("https://api.qencode.com/v1/start_encode2", [
            "task_token" => $taskToken, "query" => json_encode($queryObject),
        ]);

        if (!isset($data["error"]) || $data["error"] !== 0) {
            $desc = $data["error_description"] ?? "Unknown error";
            throw new Exception("Qencode start_encode2 error: {$desc}");
        }
    }

    /**
     * Poll /v1/status until status == "completed".
     * Returns [video_url, duration_seconds].
     */
    private function waitForCompletion(string $taskToken, int $timeoutSeconds = 1800, int $sleepSeconds = 10): array {
        $start = time();
        $lastStatus = null;

        do {
            $data = $this->post("https://api.qencode.com/v1/status", [
                "task_tokens" => $taskToken,
            ]);

            if (!isset($data["error"]) || $data["error"] !== 0) {
                $desc = $data["error_description"] ?? "Unknown error";
                throw new Exception("Qencode status error: {$desc}");
            }

            $statuses = $data["statuses"] ?? [];
            if (isset($statuses[$taskToken])) {
                $job = $statuses[$taskToken];
            } else if (!empty($statuses)) {
                // Fallback to first status if key is different for algum motivo.
                $job = reset($statuses);
            } else {
                throw new Exception("Qencode status response contains no jobs.");
            }

            $lastStatus = $job["status"] ?? null;

            if (!empty($job["error"])) {
                $desc = $job["error_description"] ?? "Unknown job error";
                throw new Exception("Qencode job error: {$desc}");
            }

            if ($lastStatus === "completed") {
                $videos = $job["videos"] ?? [];
                if (empty($videos) || !is_array($videos)) {
                    throw new Exception("Job completed but no videos found in status.");
                }

                $videoUrl = null;
                $duration = 0.0;

                foreach ($videos as $video) {
                    if (empty($video["url"])) {
                        continue;
                    }

                    // Prefer MP4 if format info is available.
                    $format = $video["storage"]["format"] ?? null;
                    if ($format === "mp4" || $format === null) {
                        $videoUrl = $video["url"];
                        if (isset($video["duration"])) {
                            $duration = (float) $video["duration"];
                        }
                        break;
                    }
                }

                if ($videoUrl === null) {
                    throw new Exception("No suitable video URL found in completed status.");
                }

                return [$videoUrl, $duration];
            }

            if (time() - $start >= $timeoutSeconds) {
                throw new Exception("Qencode transcoding timeout. Last status: " . ($lastStatus ?? "unknown"));
            }

            sleep($sleepSeconds);
        } while (true);
    }

    /**
     * Normalize output URL (e.g. s3:// → https://).
     */
    private function normalizeVideoUrl(string $url): string {
        if (strpos($url, "s3://") === 0) {
            // Example: s3://us-west.s3.qencode.com/qencode-test/output/480/12345.mp4
            // → https://us-west.s3.qencode.com/qencode-test/output/480/12345.mp4
            $url = "https://" . substr($url, 5);
        }

        return $url;
    }

    /**
     * Download remote file to local path using cURL streaming.
     */
    private function downloadFile(string $url, string $destination): void {
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception("Cannot create directory: {$dir}");
            }
        }

        $fp = fopen($destination, "w+b");
        if ($fp === false) {
            throw new Exception("Cannot open destination file: {$destination}");
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_FAILONERROR => true, CURLOPT_TIMEOUT => 0,
        ]);

        $ok = curl_exec($ch);
        if ($ok === false) {
            $err = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            throw new Exception("Download error: {$err}");
        }

        curl_close($ch);
        fclose($fp);
    }

    /**
     * Generic helper to POST form-urlencoded and decode JSON.
     */
    private function post(string $url, array $params): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/x-www-form-urlencoded",
            ], CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("HTTP POST error: {$err}");
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            throw new Exception("HTTP {$code} from {$url}: {$response}");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception("Invalid JSON from {$url}: {$response}");
        }

        return $data;
    }

    /**
     * Extract header value (case-insensitive).
     */
    private function extractHeaderValue(string $headersRaw, string $headerName): ?string {
        $lines = preg_split("/\r\n|\r|\n/", $headersRaw);
        $headerNameLower = strtolower($headerName);

        foreach ($lines as $line) {
            $parts = explode(":", $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            if (strtolower(trim($parts[0])) === $headerNameLower) {
                return trim($parts[1]);
            }
        }

        return null;
    }
}

