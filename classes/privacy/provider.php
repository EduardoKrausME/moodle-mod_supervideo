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

namespace mod_supervideo\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for mod_supervideo.
 *
 * @package   mod_supervideo
 * @copyright 2024 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('supervideo_view', [
            'cm_id' => 'privacy:metadata:supervideo_view:cm_id',
            'user_id' => 'privacy:metadata:supervideo_view:user_id',
            'currenttime' => 'privacy:metadata:supervideo_view:currenttime',
            'duration' => 'privacy:metadata:supervideo_view:duration',
            'percent' => 'privacy:metadata:supervideo_view:percent',
            'map' => 'privacy:metadata:supervideo_view:map',
            'timecreated' => 'privacy:metadata:supervideo_view:timecreated',
            'timemodified' => 'privacy:metadata:supervideo_view:timemodified',
        ], 'privacy:metadata:supervideo_view');

        return $collection;
    }

    /**
     * Find module contexts containing data for a user.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm
                    ON cm.id = ctx.instanceid
                   AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {supervideo_view} svv
                    ON svv.cm_id = cm.id
                 WHERE svv.user_id = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'supervideo',
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Add users who have progress data in a module context.
     *
     * @param userlist $userlist Approved context user list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $sql = "SELECT DISTINCT svv.user_id AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {supervideo_view} svv
                    ON svv.cm_id = cm.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'supervideo',
            'cmid' => $context->instanceid,
        ]);
    }

    /**
     * Export all viewing records for the approved user and contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        [$contextsql, $contextparams] = $DB->get_in_or_equal(
            $contextlist->get_contextids(),
            SQL_PARAMS_NAMED
        );
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'supervideo',
            'userid' => $contextlist->get_user()->id,
        ] + $contextparams;
        $sql = "SELECT svv.id, ctx.id AS contextid, sv.name,
                       svv.currenttime, svv.duration, svv.percent, svv.map,
                       svv.timecreated, svv.timemodified
                  FROM {context} ctx
                  JOIN {course_modules} cm
                    ON cm.id = ctx.instanceid
                   AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {supervideo} sv
                    ON sv.id = cm.instance
                  JOIN {supervideo_view} svv
                    ON svv.cm_id = cm.id
                 WHERE ctx.id {$contextsql}
                   AND svv.user_id = :userid
              ORDER BY ctx.id, svv.timecreated, svv.id";
        $records = $DB->get_records_sql($sql, $params);

        $exports = [];
        foreach ($records as $record) {
            $exports[$record->contextid][] = (object)[
                'video' => format_string($record->name),
                'currenttime' => (int)$record->currenttime,
                'duration' => (int)$record->duration,
                'percent' => (int)$record->percent,
                'map' => $record->map,
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified),
            ];
        }

        foreach ($exports as $contextid => $views) {
            $context = context::instance_by_id($contextid);
            writer::with_context($context)->export_data(
                [get_string('privacy:progress', 'mod_supervideo')],
                (object)['views' => $views]
            );
        }
    }

    /**
     * Delete all progress records in a module context.
     *
     * @param context $context Context to clear.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('supervideo', $context->instanceid);
        if ($cm) {
            $DB->delete_records('supervideo_view', ['cm_id' => $cm->id]);
        }
    }

    /**
     * Delete progress for one user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $cmids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_module) {
                $cmids[] = $context->instanceid;
            }
        }
        if (!$cmids) {
            return;
        }

        [$cmidsql, $cmidparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $params = ['userid' => $contextlist->get_user()->id] + $cmidparams;
        $DB->delete_records_select(
            'supervideo_view',
            "user_id = :userid AND cm_id {$cmidsql}",
            $params
        );
    }

    /**
     * Delete progress for several users in one approved context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$context instanceof context_module || !$userids) {
            return;
        }

        $cm = get_coursemodule_from_id('supervideo', $context->instanceid);
        if (!$cm) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = ['cmid' => $cm->id] + $userparams;
        $DB->delete_records_select(
            'supervideo_view',
            "cm_id = :cmid AND user_id {$usersql}",
            $params
        );
    }
}
