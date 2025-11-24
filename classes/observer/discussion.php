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

namespace local_forum_ai\observer;

use mod_forum\event\discussion_created;
use mod_forum\event\discussion_deleted;
use local_forum_ai\task\process_ai_discussion;

/**
 * Observer for discussion events.
 *
 * @package    local_forum_ai
 * @category   event
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion {
    /**
     * Handles discussion creation events.
     *
     * @param discussion_created $event The discussion created event.
     * @return bool True on success, false on error.
     */
    public static function discussion_created(discussion_created $event): bool {
        global $DB;

        try {
            $data = $event->get_data();
            $discussionid = $data['objectid'];
            $forumid = $data['other']['forumid'];

            $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST);
            $forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);

            // Get course module.
            $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

            // Prepare data for ad-hoc task.
            $taskdata = new \stdClass();
            $taskdata->discussionid = $discussionid;
            $taskdata->cmid = $cm->id;

            // Create and queue the ad-hoc task.
            $task = new process_ai_discussion();
            $task->set_custom_data($taskdata);
            $task->set_component('local_forum_ai');
            $task->set_userid($discussion->userid);

            \core\task\manager::queue_adhoc_task($task);

            return true;
        } catch (\Throwable $e) {
            debugging('Error queueing AI discussion task: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return true;
        }
    }

    /**
     * Triggered when a discussion is deleted.
     *
     * @param discussion_deleted $event The event triggered when a discussion is deleted.
     * @return void
     */
    public static function discussion_deleted(discussion_deleted $event): void {
        global $DB;

        $discussionid = $event->objectid;
        $DB->delete_records('local_forum_ai_pending', ['discussionid' => $discussionid]);
    }
}
