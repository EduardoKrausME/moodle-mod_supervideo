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
 * @copyright 2025 Eduardo Kraus {@link http://eduardokraus.com}
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
        if ($theme != "boost_training") {
            return;
        }

        $css = "";
        if ($COURSE->id != $SITE->id) {

            $cache = \cache::make("theme_boost_training", "css_cache");
            $cachekey = "supervideo_icon_{$COURSE->id}";
            if ($cache->has($cachekey)) {
                $css = $cache->get($cachekey);
                echo "<style>{$css}</style>";
                return;
            }

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
                    if ($video->videourl == "ottflix") {
                        $status = ottflix_repository::getstatus($video->videourl);
                        $thumb = $status->data->THUMB;
                    } else if ($video->videourl == "youtube") {
                        $pattern = '/(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user|shorts)\/))([^\?&\"\'>]+)/';
                        preg_match_all($pattern, $video->videourl, $videos);
                        if (isset($videos[1][0])) {
                            $youtubeid = $videos[1][0];
                            $thumb = "https://i.ytimg.com/vi/{$youtubeid}/mqdefault.jpg";
                        }
                    }

                    if ($thumb) {
                        $formatblockcss = file_get_contents("{$CFG->dirroot}/theme/boost_training/scss/format-block.css");
                        $formatblockcss = str_replace("customiconid", $video->cmid, $formatblockcss);
                        $formatblockcss = str_replace("{imageurl}", $thumb, $formatblockcss);
                        $css .= $formatblockcss;
                    }
                }
            }

            $cache->set($cachekey, $css);
            echo "<style>{$css}</style>";
        }
    }
}
