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

            // Retrieve original objects.
            $post = $DB->get_record('forum_posts', ['id' => $postid], '*', MUST_EXIST);
            $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
            $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);

            // Skip first post of the discussion.
            if ($post->id == $discussion->firstpost) {
                return true;
            }

            // Load plugin configuration.
            $config = $DB->get_record('local_forum_ai_config', ['forumid' => $forum->id]);
            $enabled = $config->enabled ?? get_config('local_forum_ai', 'default_enabled');
            $replymessage = $config->reply_message ?? get_config('local_forum_ai', 'default_reply_message');
            $requireapproval = $config->require_approval ?? 1;
            $allowedroles = $config->allowedroles ?? '';

            // Skip if user has no permissions.
            if (!role_checker::user_has_allowed_role($forum->id, $post->userid, $allowedroles)) {
                return true;
            }

            if (!$enabled) {
                return true;
            }

            $gradingenabled = ($forum->assessed != 0);

            $payload = [
                'course'           => $course->fullname,
                'forum'            => $forum->name,
                'discussion'       => $discussion->name,
                'discussion_id'    => $discussion->id,
                'postid'           => $post->id,
                'userid'           => $post->userid,
                'prompt'           => $replymessage,
                'grading_enabled'  => $gradingenabled,
                'scale'            => $gradingenabled ? $forum->scale : null,
            ];

            $airesponse = ai_service::call_ai_service($payload);

            $replytext = $airesponse['reply'] ?? '';
            $grade = $airesponse['grade'] ?? null;

            if ($gradingenabled && !is_null($grade)) {
                $context = $event->get_context();
                $cmid = $context->instanceid;

                $rating = (object)[
                    'contextid'    => $context->id,
                    'component'    => 'mod_forum',
                    'ratingarea'   => 'post',
                    'itemid'       => $post->id,
                    'scaleid'      => $forum->scale,
                    'rating'       => $grade,
                    'userid'       => 2,
                    'timecreated'  => time(),
                    'timemodified' => time(),
                ];

                $DB->insert_record('rating', $rating);
            }

            if ($requireapproval) {
                approval::create_approval_request($discussion, $forum, $replytext, 'pending', $post->id);
            } else {
                approval::create_approval_request($discussion, $forum, $replytext, 'approved', $post->id);
                approval::create_ai_reply($discussion, $replytext, $post->id);
            }

            return true;
        } catch (\Throwable $e) {
            debugging('Error in post_created AI handler: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return true;
        }
    }
}
