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

defined('MOODLE_INTERNAL') || die();

use mod_forum\event\discussion_created;
use mod_forum\event\discussion_deleted;

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

            $config = $DB->get_record('local_forum_ai_config', ['forumid' => $forumid]);

            $enabled = $config->enabled ?? get_config('local_forum_ai', 'default_enabled');
            $replymessage = $config->reply_message ?? get_config('local_forum_ai', 'default_reply_message');
            $requireapproval = $config->require_approval ?? 1;
            $enablediainitconversation = $config->enablediainitconversation ?? 0;
            $allowedroles = $config->allowedroles ?? '';

            $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST);

            if (!role_checker::user_has_allowed_role($forumid, $discussion->userid, $allowedroles)) {
                return true;
            }

            if (!$enabled || empty($enablediainitconversation)) {
                return true;
            }

            $post = $DB->get_record('forum_posts', ['id' => $discussion->firstpost], '*', MUST_EXIST);
            $forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);

            $payload = [
                'course' => $course->fullname,
                'forum' => $forum->name,
                'discussion' => $discussion->name,
                'discussion_id' => $discussionid,
                'userid' => $discussion->userid,
                'postid' => $post->id,
                'prompt' => $replymessage,
            ];

            try {
                $airesponse = ai_service::call_ai_service($payload);

                $replytext = $airesponse['reply'] ?? '';

                if ($requireapproval) {
                    approval::create_approval_request($discussion, $forum, $replytext, 'pending', $discussion->firstpost);
                } else {
                    approval::create_approval_request($discussion, $forum, $replytext, 'approved', $discussion->firstpost);
                    approval::create_ai_reply($discussion, $replytext, $discussion->firstpost);
                }
            } catch (\Throwable $e) {
                debugging('Error communicating with the AI service: ' . $e->getMessage(), DEBUG_DEVELOPER);

                \core\notification::add(
                    get_string('error_airequest', 'local_forum_ai', $e->getMessage()),
                    \core\output\notification::NOTIFY_ERROR
                );

                return true;
            }

            return true;
        } catch (\Exception $e) {
            debugging('General error in discussion_created: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
