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
                return $cache->get($cachekey);
            }

            $sql = "
                SELECT cm.id AS cmid, sv.videourl
                  FROM {course_modules} cm
                  JOIN {modules}        md ON md.id = cm.module
                  JOIN {supervideo}     sv ON sv.id = cm.instance
                 WHERE sv.course = :course
                   AND sv.origem = 'ottflix'
                   AND md.name   = 'supervideo'";
            $videos = $DB->get_records_sql($sql, ["course" => $COURSE->id]);
            foreach ($videos as $video) {
                if (isset($video->videourl[3])) {
                    $status = \mod_supervideo\ottflix\repository::getstatus($video->videourl);

                    if (isset($status->data->THUMB)) {
                        $formatblockcss = file_get_contents("{$CFG->dirroot}/theme/boost_training/scss/format-block.css");
                        $formatblockcss = str_replace("customiconid", $video->cmid, $formatblockcss);
                        $formatblockcss = str_replace("{imageurl}", $status->data->THUMB, $formatblockcss);
                        $css .= $formatblockcss;
                    }
                }
            }

            $cache->set($cachekey, $css);
            echo "<style>{$css}</style>";
        }
    }
}
