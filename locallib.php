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
 * Helper functions for the Forum AI plugin.
 *
 * @package    local_forum_ai
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/rating/lib.php');

/**
 * Gets the list of pending responses.
 *
 * @package local_forum_ai
 * @param int $courseid Course ID.
 * @param int $forumid (optional) Forum ID to filter.
 * @return array list of objects with pending data.
 */
function local_forum_ai_get_pending(int $courseid, int $forumid = 0) {
    global $DB;

    $sql = "SELECT p.*, d.name AS discussionname, f.name AS forumname,
                   c.fullname AS coursename, u.firstname, u.lastname,
                   fp.subject AS discussionsubject, fp.message AS discussionmessage, fp.messageformat
              FROM {local_forum_ai_pending} p
              JOIN {forum_discussions} d ON d.id = p.discussionid
              JOIN {forum} f ON f.id = p.forumid
              JOIN {course} c ON c.id = f.course
              JOIN {course_modules} cm ON cm.instance = f.id AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'forum'
              )
              JOIN {user} u ON u.id = p.creator_userid
              JOIN {forum_posts} fp ON fp.id = d.firstpost
             WHERE p.status = :status
               AND f.course = :courseid
               AND cm.deletioninprogress = 0
               AND cm.visible = 1";

    $params = [
        'status' => 'pending',
        'courseid' => $courseid,
    ];

    if ($forumid > 0) {
        $sql .= " AND f.id = :forumid";
        $params['forumid'] = $forumid;
    }

    $sql .= " ORDER BY p.timecreated DESC";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Gets the list of response history.
 *
 * @package local_forum_ai
 * @param int $courseid Course ID.
 * @param int $forumid (optional) Forum ID to filter.
 * @return array list of response objects.
 */
function local_forum_ai_get_history(int $courseid, int $forumid = 0) {
    global $DB;

    $sql = "SELECT p.*, d.name AS discussionname, f.name AS forumname, c.fullname AS coursename,
                   u.firstname, u.lastname
              FROM {local_forum_ai_pending} p
              JOIN {forum_discussions} d ON d.id = p.discussionid
              JOIN {forum} f ON f.id = p.forumid
              JOIN {course} c ON c.id = f.course
              JOIN {course_modules} cm ON cm.instance = f.id AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'forum'
              )
              JOIN {user} u ON u.id = p.creator_userid
             WHERE p.status IN ('approved', 'rejected')
               AND f.course = :courseid
               AND cm.deletioninprogress = 0
               AND cm.visible = 1";

    $params = ['courseid' => $courseid];

    if ($forumid > 0) {
        $sql .= " AND f.id = :forumid";
        $params['forumid'] = $forumid;
    }

    $sql .= " ORDER BY p.timecreated DESC";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Returns the teachers (users with editingteacher role) of a course.
 *
 * @package local_forum_ai
 * @param int $courseid Course ID.
 * @param bool $single Whether to return only one.
 * @return \stdClass|array|null
 */
function local_forum_ai_get_editingteachers(int $courseid, bool $single = false) {
    global $DB;

    $context = \context_course::instance($courseid);

    $sql = "SELECT u.*
              FROM {role_assignments} ra
              JOIN {user} u ON u.id = ra.userid
              JOIN {role} r ON r.id = ra.roleid
             WHERE ra.contextid = :contextid
               AND r.shortname = :rolename
             ORDER BY ra.id ASC";

    $params = [
        'contextid' => $context->id,
        'rolename' => 'editingteacher',
    ];

    if ($single) {
        return $DB->get_record_sql($sql . " LIMIT 1", $params);
    } else {
        return $DB->get_records_sql($sql, $params);
    }
}

/**
 * Cleans pending AI responses from expired forums.
 *
 * @return int Number of deleted records.
 */
function local_forum_ai_cleanup_expired(): int {
    global $DB;

    $now = time();

    $sql = "SELECT p.id
              FROM {local_forum_ai_pending} p
              JOIN {forum} f ON f.id = p.forumid
             WHERE p.status = 'pending'
               AND (
                   (f.cutoffdate > 0 AND f.cutoffdate < :now1)
                   OR (f.cutoffdate = 0 AND f.duedate > 0 AND f.duedate < :now2)
               )";

    $params = [
        'now1' => $now,
        'now2' => $now,
    ];

    $pendings = $DB->get_records_sql($sql, $params);

    if ($pendings) {
        $ids = array_keys($pendings);
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_forum_ai_pending', "id $insql", $inparams);
        return count($ids);
    }

    return 0;
}

/**
 * Adds a rating on behalf of a specific user (for AI forum plugin).
 *
 * This is a custom version of rating_manager::add_rating() that accepts
 * a specific user ID instead of using the global $USER variable.
 *
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $component component name
 * @param string $ratingarea rating area
 * @param int $itemid the item id
 * @param int $scaleid the scale id
 * @param int $userrating the rating value
 * @param int $rateduserid the rated user id
 * @param int $aggregationmethod the aggregation method
 * @param int $rateruserid the user ID who is giving the rating
 * @return stdClass result object with success/error properties
 */
function local_forum_ai_add_rating(
    $cm,
    $context,
    $component,
    $ratingarea,
    $itemid,
    $scaleid,
    $userrating,
    $rateduserid,
    $aggregationmethod,
    $rateruserid
) {
    global $CFG, $DB;

    $result = new stdClass();
    $rm = new rating_manager();

    // Check the module rating permissions for the rater user.
    $pluginpermissionsarray = $rm->get_plugin_permissions_array($context->id, $component, $ratingarea);

    if (!$pluginpermissionsarray['rate']) {
        $result->error = 'ratepermissiondenied';
        return $result;
    }

    // Validate the rating.
    $params = [
        'context'     => $context,
        'component'   => $component,
        'ratingarea'  => $ratingarea,
        'itemid'      => $itemid,
        'scaleid'     => $scaleid,
        'rating'      => $userrating,
        'rateduserid' => $rateduserid,
        'aggregation' => $aggregationmethod,
    ];

    if (!$rm->check_rating_is_valid($params)) {
        $result->error = 'ratinginvalid';
        return $result;
    }

    // Rating options used to update the rating.
    $ratingoptions = new stdClass();
    $ratingoptions->context = $context;
    $ratingoptions->ratingarea = $ratingarea;
    $ratingoptions->component = $component;
    $ratingoptions->itemid  = $itemid;
    $ratingoptions->scaleid = $scaleid;
    $ratingoptions->userid  = $rateruserid;

    if ($userrating != RATING_UNSET_RATING) {
        $rating = new rating($ratingoptions);
        $rating->update_rating($userrating);
    } else {
        // Delete the rating if unset.
        $options = new stdClass();
        $options->contextid = $context->id;
        $options->component = $component;
        $options->ratingarea = $ratingarea;
        $options->userid = $rateruserid;
        $options->itemid = $itemid;

        $rm->delete_ratings($options);
    }

    // Update grades if in module context.
    if ($context->contextlevel == CONTEXT_MODULE) {
        $modinstance = $DB->get_record($cm->modname, ['id' => $cm->instance]);
        if ($modinstance) {
            $modinstance->cmidnumber = $cm->id;
            $functionname = $cm->modname . '_update_grades';
            require_once($CFG->dirroot . "/mod/{$cm->modname}/lib.php");
            if (function_exists($functionname)) {
                $functionname($modinstance, $rateduserid);
            }
        }
    }

    $result->success = true;

    // Retrieve the updated aggregate.
    $item = new stdClass();
    $item->id = $itemid;

    $ratingoptions->items = [$item];
    $ratingoptions->aggregate = $aggregationmethod;

    $items = $rm->get_ratings($ratingoptions);
    $firstrating = $items[0]->rating;

    if ($firstrating->user_can_view_aggregate()) {
        $scalearray = null;
        $aggregatetoreturn = round($firstrating->aggregate, 1);

        if ($firstrating->settings->aggregationmethod == RATING_AGGREGATE_COUNT || $firstrating->count == 0) {
            $aggregatetoreturn = ' - ';
        } else if ($firstrating->settings->scale->id < 0) {
            if ($firstrating->settings->aggregationmethod != RATING_AGGREGATE_SUM) {
                $scalerecord = $DB->get_record('scale', ['id' => -$firstrating->settings->scale->id]);
                if ($scalerecord) {
                    $scalearray = explode(',', $scalerecord->scale);
                    $aggregatetoreturn = $scalearray[$aggregatetoreturn - 1];
                }
            }
        }

        $result->aggregate = $aggregatetoreturn;
        $result->count = $firstrating->count;
        $result->itemid = $itemid;
    }

    return $result;
}
