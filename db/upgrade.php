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
 * Upgrade file
 *
 * @package    mod_supervideo
 * @copyright  2023 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * function xmldb_supervideo_upgrade
 *
 * @param int $oldversion
 *
 * @return bool
 * @throws ddl_exception
 * @throws ddl_field_missing_exception
 * @throws ddl_table_missing_exception
 * @throws downgrade_exception
 * @throws upgrade_exception
 */
function xmldb_supervideo_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019010303) {

        $tablesupervideo = new xmldb_table('supervideo');

        $fieldurl = new xmldb_field('supervideoid', XMLDB_TYPE_CHAR, 255);
        if ($dbman->field_exists($tablesupervideo, $fieldurl)) {
            $dbman->rename_field($tablesupervideo, $fieldurl, 'url');
        }

        upgrade_plugin_savepoint(true, 2019010303, 'mod', 'supervideo');
    }

    if ($oldversion < 2023032506) {

        $tablesupervideoview = new xmldb_table('supervideo_view');

        $tablesupervideoview->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $tablesupervideoview->add_field('cm_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $tablesupervideoview->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $tablesupervideoview->add_field('currenttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $tablesupervideoview->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $tablesupervideoview->add_field('percent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $tablesupervideoview->add_field('mapa', XMLDB_TYPE_CHAR, 'small', null, XMLDB_NOTNULL);
        $tablesupervideoview->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL);
        $tablesupervideoview->add_field('timemodified', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL);

        $tablesupervideoview->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($tablesupervideoview)) {
            $dbman->create_table($tablesupervideoview);
        }

        $tablesupervideo = new xmldb_table('supervideo');

        $fieldcompletpercent = new xmldb_field('complet_percent', XMLDB_TYPE_INTEGER, 10);
        if (!$dbman->field_exists($tablesupervideo, $fieldcompletpercent)) {
            $dbman->add_field($tablesupervideo, $fieldcompletpercent);
        }

        $fieldvideofile = new xmldb_field('videofile', XMLDB_TYPE_INTEGER, 10);
        if (!$dbman->field_exists($tablesupervideo, $fieldvideofile)) {
            $dbman->add_field($tablesupervideo, $fieldvideofile);
        }

        upgrade_plugin_savepoint(true, 2023032506, 'mod', 'supervideo');
    }

    return true;
}
