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

use mod_forum\event\post_created;

/**
 * Observer for post events.
 *
 * @package    local_forum_ai
 * @category   event
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post {

    /**
     * Handles when a user replies to an existing discussion.
     *
     * @param post_created $event
     * @return bool
     */
    public static function post_created(post_created $event): bool {
        global $DB;

        try {
            $data = $event->get_data();
            $postid = $data['objectid'];

            // Get the post and related discussion.
            $post = $DB->get_record('forum_posts', ['id' => $postid], '*', MUST_EXIST);
            $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
            $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);

            // Do not process if the post is the first (already handled in discussion_created).
            if ($post->id == $discussion->firstpost) {
                return true;
            }

            // Load forum configuration.
            $config = $DB->get_record('local_forum_ai_config', ['forumid' => $forum->id]);
            $enabled = $config->enabled ?? get_config('local_forum_ai', 'default_enabled');
            $replymessage = $config->reply_message ?? get_config('local_forum_ai', 'default_reply_message');
            $requireapproval = $config->require_approval ?? 1;
            $allowedroles = $config->allowedroles ?? '';

            // If the post author does not have any allowed role, do nothing.
            if (!role_checker::user_has_allowed_role($forum->id, $post->userid, $allowedroles)) {
                return true;
            }

            if (!$enabled) {
                return true;
            }

            // Create the payload for the AI.
            $payload = [
                'course' => $course->fullname,
                'forum' => $forum->name,
                'discussion' => $discussion->name,
                'discussion_id' => $discussion->id,
                'postid' => $post->id,
                'userid' => $post->userid,
                'prompt' => $replymessage,
            ];

            // Call the AI.
            $airesponse = ai_service::call_ai_service($payload);

            if ($requireapproval) {
                // Save the parentpostid to reply to the correct post.
                approval::create_approval_request($discussion, $forum, $airesponse, 'pending', $post->id);
            } else {
                approval::create_approval_request($discussion, $forum, $airesponse, 'approved', $post->id);
                approval::create_ai_reply($discussion, $airesponse, $post->id);
            }

            return true;
        } catch (\Throwable $e) {
            debugging('Error in post_created AI handler: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return true;
        }
    }
}