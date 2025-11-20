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
use mod_forum\event\post_deleted;
require_once($CFG->dirroot . '/rating/lib.php');

class post {
    /**
     * Handles when a user replies to an existing discussion.
     *
     * @param post_created $event
     * @return bool
     */
    public static function post_created(post_created $event): bool {
        global $DB, $USER;

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
            $graderid = $config->graderid ?? null;

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
                'userid'           => $graderid ?? 2,
                'prompt'           => $replymessage,
                'grading_enabled'  => $gradingenabled,
                'scale'            => $gradingenabled ? $forum->scale : null,
            ];

            $airesponse = ai_service::call_ai_service($payload);

            $replytext = $airesponse['reply'] ?? '';
            $grade = $gradingenabled ? ($airesponse['grade'] ?? null) : null;

            if (!$requireapproval && $gradingenabled && $grade !== null && $graderid) {

                $context = $event->get_context();
                $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

                // Temporarily switch to configured grader user
                $originaluser = $USER;
                $USER = \core_user::get_user($graderid, '*', MUST_EXIST);

                try {
                    $rm = new \rating_manager();

                    $result = $rm->add_rating(
                        $cm,
                        $context,
                        'mod_forum',
                        'post',
                        $post->id,
                        $forum->scale,
                        $grade,
                        $post->userid,
                        $forum->assessed
                    );

                    if (!empty($result->error)) {
                        debugging('Error adding AI rating: ' . $result->error, DEBUG_DEVELOPER);
                    }
                } finally {
                    $USER = $originaluser;
                }
            } else if (!$requireapproval && $gradingenabled && $grade !== null && !$graderid) {
                debugging('Grading enabled but no grader configured for forum ' . $forum->id, DEBUG_DEVELOPER);
            }

            if ($requireapproval) {

                approval::create_approval_request(
                    $discussion,
                    $forum,
                    $replytext,
                    'pending',
                    $post->id,
                    $grade
                );

            } else {

                approval::create_approval_request(
                    $discussion,
                    $forum,
                    $replytext,
                    'approved',
                    $post->id,
                    $grade
                );

                approval::create_ai_reply($discussion, $replytext, $post->id);
            }

            return true;

        } catch (\Throwable $e) {
            debugging('Error in post_created AI handler: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return true;
        }
    }

    /**
     * Triggered when a post is deleted.
     *
     * @param post_deleted $event
     * @return void
     */
    public static function post_deleted(post_deleted $event): void {
        global $DB;

        $postid = $event->objectid;

        $DB->delete_records('local_forum_ai_pending', ['parentpostid' => $postid]);
    }
}
