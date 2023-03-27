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
 * Supervideo View implementation for mod_supervideo.
 */

namespace mod_supervideo\report;

use html_writer;
use mod_supervideo\util\url;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   mod_supervideo
 * @copyright 2023 Eduardo Kraus {@link http://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supervideo_view extends \table_sql {

    /**
     * @var int
     */
    public $cm_id = 0;
    /**
     * @var int
     */
    public $user_id = 0;

    /**
     * supervideo_view constructor.
     *
     * @param $uniqueid
     * @param $cm_id
     * @param $supervideo
     *
     * @throws \coding_exception
     */
    public function __construct($uniqueid, $cm_id, $supervideo) {
        parent::__construct($uniqueid);

        $this->cm_id = $cm_id;
        $this->user_id = optional_param('u', false, PARAM_INT);

        $this->is_downloadable(true);
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);

        $download = optional_param('download', null, PARAM_ALPHA);
        if ($download) {
            raise_memory_limit(MEMORY_EXTRA);
            $this->is_downloading($download, 'Visualizações de Vídeos do Plugin Super Vídeo', $supervideo->name);
        }

        if ($this->user_id) {
            $columns = [
                'user_id',
                'fullname',
                'email',
                'currenttime',
                'duration',
                'percent',
                'mapa',
                'timecreated',
                'timemodified',
            ];
            $headers = [
                get_string('report_userid', 'mod_supervideo'),
                get_string('report_nome', 'mod_supervideo'),
                get_string('report_email', 'mod_supervideo'),
                get_string('report_tempo', 'mod_supervideo'),
                get_string('report_duracao', 'mod_supervideo'),
                get_string('report_porcentagem', 'mod_supervideo'),
                get_string('report_mapa', 'mod_supervideo'),
                get_string('report_comecou', 'mod_supervideo'),
                get_string('report_terminou', 'mod_supervideo'),
            ];
            if (url::parse($supervideo->videourl)->engine == "drive") {
                unset($columns[8]);
                unset($columns[6]);
                unset($columns[5]);
                unset($columns[4]);
                unset($columns[3]);

                unset($headers[8]);
                unset($headers[6]);
                unset($headers[5]);
                unset($headers[4]);
                unset($headers[3]);
            }
        } else {
            $columns = [
                'user_id',
                'fullname',
                'email',
                'currenttime',
                'duration',
                'percent',
                'quantidade',
                'timecreated',
            ];
            $headers = [
                get_string('report_userid', 'mod_supervideo'),
                get_string('report_nome', 'mod_supervideo'),
                get_string('report_email', 'mod_supervideo'),
                get_string('report_tempo', 'mod_supervideo'),
                get_string('report_duracao', 'mod_supervideo'),
                get_string('report_porcentagem', 'mod_supervideo'),
                get_string('report_visualizacoes', 'mod_supervideo'),
                get_string('report_assistiu', 'mod_supervideo'),
            ];
            if (url::parse($supervideo->videourl)->engine == "drive") {
                unset($columns[5]);
                unset($columns[4]);
                unset($columns[3]);

                unset($headers[5]);
                unset($headers[4]);
                unset($headers[3]);
            }

            if (!$this->is_downloading()) {
                $columns[] = 'extra';
                $headers[] = '';
            }
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
    }

    /**
     * Fullname is treated as a special columname in tablelib and should always
     * be treated the same as the fullname of a user.
     * @uses $this->useridfield if the userid field is not expected to be id
     * then you need to override $this->useridfield to point at the correct
     * field for the user id.
     *
     * @param object $linha the data from the db containing all fields from the
     *                      users table necessary to construct the full name of the user in
     *                      current language.
     *
     * @return string contents of cell in column 'fullname', for this row.
     * @throws \moodle_exception
     */
    function col_fullname($linha) {
        global $COURSE;

        $name = fullname($linha);
        if ($this->download) {
            return $name;
        }

        if ($COURSE->id == SITEID) {
            $profileurl = new moodle_url('/user/profile.php', array('id' => $linha->user_id));
        } else {
            $profileurl = new moodle_url('/user/view.php',
                array('id' => $linha->user_id, 'course' => $COURSE->id));
        }
        return html_writer::link($profileurl, $name);
    }

    /**
     * @param $linha
     *
     * @return string
     */
    public function col_currenttime($linha) {
        $seconds = $linha->currenttime % 60;
        $minutes = (floor($linha->currenttime / 60)) % 60;
        $hours = floor($linha->currenttime / 3600);

        $hours = substr("0{$hours}", -2);
        $minutes = substr("0{$minutes}", -2);
        $seconds = substr("0{$seconds}", -2);
        return "{$hours}:{$minutes}:{$seconds}";
    }

    /**
     * @param $linha
     *
     * @return string
     */
    public function col_duration($linha) {
        $seconds = $linha->duration % 60;
        $minutes = (floor($linha->duration / 60)) % 60;
        $hours = floor($linha->duration / 3600);

        $hours = substr("0{$hours}", -2);
        $minutes = substr("0{$minutes}", -2);
        $seconds = substr("0{$seconds}", -2);
        return "{$hours}:{$minutes}:{$seconds}";
    }

    /**
     * @param $linha
     *
     * @return string
     */
    public function col_percent($linha) {
        return "{$linha->percent}%";
    }

    /**
     * @param $linha
     *
     * @return string
     */
    public function col_mapa($linha) {
        $htmlMapa = "<div id='mapa-visualizacao' class='report'>";

        $mapas = json_decode($linha->mapa);
        $id = 0;
        foreach ($mapas as $mapa) {
            if ($mapa) {
                $htmlMapa .= "<div id='mapa-visualizacao-" . $id++ . "' style='opacity:1'></div>";
            } else {
                $htmlMapa .= "<div id='mapa-visualizacao-" . $id++ . "'></div>";
            }
        }
        $htmlMapa .= "</div>";
        return $htmlMapa;
    }

    /**
     * @param $linha
     *
     * @return string
     */
    public function col_timecreated($linha) {
        return userdate($linha->timecreated);
    }

    /**
     * @param $linha
     *
     * @return string
     */
    public function col_timemodified($linha) {
        return userdate($linha->timemodified);
    }

    /**
     * @param $linha
     *
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_extra($linha) {
        $profileurl = new \moodle_url('/mod/supervideo/report.php?', ['id' => $linha->cm_id, 'u' => $linha->user_id]);
        return \html_writer::link($profileurl, get_string('report_all', 'mod_supervideo'));
    }


    /**
     * @param int  $pagesize
     * @param bool $useinitialsbar
     *
     * @throws \dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        $params = ["cm_id" => $this->cm_id];

        $sql_where = $this->get_sql_where();
        $where = $sql_where[0] ? "AND {$sql_where[0]}" : "";
        $params = array_merge($params, $sql_where[1]);

        $order = $this->get_sort_for_table($this->uniqueid);
        if (!$order) {
            $order = "sv.user_id";
        }

        if ($this->user_id) {
            $params['user_id'] = $this->user_id;

            $this->sql = "SELECT sv.user_id, sv.currenttime, sv.duration, sv.percent, sv.timecreated, sv.timemodified, sv.mapa,
                                 u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email
                            FROM {supervideo_view} sv
                            JOIN {user} u ON u.id = sv.user_id
                           WHERE sv.cm_id   = :cm_id 
                             AND sv.user_id = :user_id 
                             AND percent    > 0 
                                 {$where}
                        ORDER BY {$order}";

            if ($pagesize != -1) {
                $countsql = "SELECT COUNT(*) FROM (
                                 SELECT COUNT(sv.id) AS cont
                                   FROM {supervideo_view} sv
                                   JOIN {user} u ON u.id = sv.user_id
                                  WHERE sv.cm_id   = :cm_id 
                                    AND sv.user_id = :user_id 
                                    AND percent    > 0 
                                        {$where}
                             ) AS c";
                $total = $DB->get_field_sql($countsql, $params);
                $this->pagesize($pagesize, $total);
            } else {
                $this->pageable(false);
            }
        } else {
            $this->sql = "SELECT sv.user_id, sv.cm_id, MAX(sv.currenttime) currenttime, MAX(sv.duration) duration,
                                 MAX(sv.percent) percent, MAX(sv.timecreated) timecreated,
                                 (SELECT COUNT(*) FROM {supervideo_view} sv1 WHERE sv1.cm_id = sv.cm_id AND sv1.user_id = sv.user_id AND sv1.percent > 0 ) AS quantidade,
                                 u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.email
                            FROM {supervideo_view} sv
                            JOIN {user} u ON u.id = sv.user_id
                           WHERE sv.cm_id = :cm_id {$where}
                        GROUP BY sv.user_id
                        ORDER BY {$order}";

            if ($pagesize != -1) {
                $countsql = "SELECT COUNT(*) FROM (
                                 SELECT COUNT(sv.id) AS cont
                                   FROM {supervideo_view} sv
                                   JOIN {user} u ON u.id = sv.user_id
                                  WHERE sv.cm_id = :cm_id {$where}
                               GROUP BY sv.user_id
                             ) AS c";
                $total = $DB->get_field_sql($countsql, $params);
                $this->pagesize($pagesize, $total);
            } else {
                $this->pageable(false);
            }
        }

        if ($useinitialsbar && !$this->is_downloading()) {
            $this->initialbars(true);
        }

        $this->rawdata = $DB->get_recordset_sql($this->sql, $params, $this->get_page_start(), $this->get_page_size());
    }
}