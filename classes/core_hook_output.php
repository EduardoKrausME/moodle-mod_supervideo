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
 * Hooks
 *
 * @package   mod_supervideo
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_supervideo;

use mod_supervideo\ottflix\repository as ottflix_repository;

/**
 * Class core_hook_output
 */
class core_hook_output {
    /**
     * Function before_footer_html_generation
     *
     * @throws \Exception
     */
    public static function before_footer_html_generation() {
        global $DB, $CFG, $COURSE, $SITE;
        $theme = $CFG->theme;
        if (isset($_SESSION["SESSION"]->theme)) {
            $theme = $_SESSION["SESSION"]->theme;
        }
        if ($theme != "eadtraining" && $theme != "eadflix" && $theme != "boost_magnific" && $theme != "degrade") {
            return;
        }
        if ($COURSE->id == $SITE->id) {
            return;
        }

        $blocks = [];

        $cache = \cache::make("theme_eadtraining", "css_cache");
        $cachekey = "supervideo_icon_{$COURSE->id}_v2";
        if ($cache->has($cachekey)) {
            $blocks = json_decode($cache->get($cachekey), true);
        } else {
            $sql = "
                SELECT cm.id AS cmid, sv.videourl, sv.origem
                  FROM {course_modules} cm
                  JOIN {modules}        md ON md.id = cm.module
                  JOIN {supervideo}     sv ON sv.id = cm.instance
                 WHERE sv.course = :course
                   AND sv.origem IN('ottflix','youtube')
                   AND md.name   = 'supervideo'";
            $videos = $DB->get_records_sql($sql, ["course" => $COURSE->id]);
            foreach ($videos as $video) {
                if (isset($video->videourl[3])) {
                    $thumb = null;
                    if ($video->origem == "ottflix") {
                        $status = ottflix_repository::getstatus($video->videourl);
                        if (isset($status->data->THUMB)) {
                            $thumb = $status->data->THUMB;
                        }
                    } else if ($video->origem == "youtube") {
                        if ($youtubeid = self::get_youtube_videoid($video->videourl)) {
                            $thumb = "https://i.ytimg.com/vi/{$youtubeid}/mqdefault.jpg";
                        }
                    }

                    if ($thumb) {
                        $blocks[] = ["cmid" => $video->cmid, "thumb" => $thumb];
                    }
                }
            }
        }

        global $PAGE;
        foreach ($blocks as $block) {
            $PAGE->requires->js_call_amd("theme_{$theme}/blocks", "create", [$block["cmid"], $block["thumb"]]);
        }

        $cache->set($cachekey, json_encode($blocks));
    }

    /**
     * get youtube videoid
     *
     * @param $url
     * @return string|null
     */
    private static function get_youtube_videoid($url) {
        $pattern = '/(?:youtube\.com\/.*v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
