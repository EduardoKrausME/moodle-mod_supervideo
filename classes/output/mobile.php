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

namespace mod_supervideo\output;

use mod_supervideo;

/**
 * Output Mobile for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo kraus (http://eduardokraus.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Function mobile_course_view
     *
     * @param $args
     *
     * @return array
     * @throws \Exception
     */
    public static function mobile_course_view($args) {
        global $CFG, $OUTPUT, $USER;

        $cmid = $args['cmid'];
        $token = self::create_embed_token($USER->id);

        $data = [
            'cmid' => $cmid,
            'wwwroot' => $CFG->wwwroot,
            'user_id' => $USER->id,
            'secret' => $token,
            't' => time(),
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_supervideo/mobile_view_page', $data),
                ],
            ],
        ];
    }

    /**
     * Function create_embed_token
     *
     * @param $userid
     *
     * @return bool|string
     * @throws \dml_exception
     */
    private static function create_embed_token($userid) {
        global $DB;

        $secret = sha1(uniqid(0)) . sha1(uniqid(1));
        $tokensupervideo = substr($secret, 0, rand(54, 64));

        $data = (object)[
            'user_id' => $userid,
            'secret' => $tokensupervideo,
            'created_at' => time(),
        ];
        $DB->insert_record('supervideo_auth', $data);

        return $tokensupervideo;
    }

    /**
     * Function valid_token
     *
     * @param $userid
     * @param $secret
     *
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function valid_token($userid, $secret) {
        global $DB;

        // Delete expired.
        $where = ['created_at' => time() - 600];
        $DB->delete_records_select('supervideo_auth', 'created_at < :created_at', $where);

        $auth = $DB->get_record('supervideo_auth', [
            'user_id' => $userid,
            'secret' => $secret,
        ]);

        if ($auth) {
            $user = $DB->get_record_select('user', "id = :id AND deleted <> 1", ['id' => $userid]);
            return $user;
        }

        return false;
    }
}
