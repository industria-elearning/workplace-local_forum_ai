<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_forum_ai\task;

use core\task\adhoc_task;
use local_forum_ai\ai_service;
use local_forum_ai\approval;
use local_forum_ai\role_checker;

/**
 * Ad-hoc task to process AI responses and grading for forum posts.
 *
 * @package local_forum_ai
 * @copyright 2025 Datacurso
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_ai_post extends adhoc_task {
    /**
     * Executes the queued ad-hoc task.
     *
     * @return void
     */
    public function execute() {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/rating/lib.php');

        $data = $this->get_custom_data();
        $postid = $data->postid;

        try {
            $post = $DB->get_record('forum_posts', ['id' => $postid], '*', MUST_EXIST);
            $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
            $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);

            // Skip first post.
            if ($post->id == $discussion->firstpost) {
                return;
            }

            $config = $DB->get_record('local_forum_ai_config', ['forumid' => $forum->id]);
            $enabled = $config->enabled ?? get_config('local_forum_ai', 'default_enabled');
            $replymessage = $config->reply_message ?? get_config('local_forum_ai', 'default_reply_message');
            $requireapproval = $config->require_approval ?? 1;
            $allowedroles = $config->allowedroles ?? '';
            $graderid = $config->graderid ?? null;

            if (!$enabled) {
                return;
            }

            if (!role_checker::user_has_allowed_role($forum->id, $post->userid, $allowedroles)) {
                return;
            }

            $gradingenabled = ($forum->assessed != 0);

            $postmessage = format_text($post->message, $post->format, [
            'context' => \context_module::instance($data->cmid),
            ]);

            $postmessage = strip_tags($postmessage);
            $postmessage = trim($postmessage);

            $payload = [
                'course' => $course->fullname,
                'forum' => $forum->name,
                'discussion' => $discussion->name,
                'discussion_id' => $discussion->id,
                'postid' => $post->id,
                'post' => $postmessage,
                'userid' => $graderid ?? 2,
                'prompt' => $replymessage,
                'grading_enabled' => $gradingenabled,
                'scale' => $gradingenabled ? $forum->scale : null,
            ];

            $airesponse = ai_service::call_ai_service($payload);
            $replytext = $airesponse['reply'] ?? '';
            $grade = $gradingenabled ? ($airesponse['grade'] ?? null) : null;

            if (!$requireapproval && $gradingenabled && $grade !== null && $graderid) {
                $context = \context_module::instance($data->cmid);
                $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

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

            approval::create_approval_request(
                $discussion,
                $forum,
                $replytext,
                $requireapproval ? 'pending' : 'approved',
                $post->id,
                $grade
            );

            if (!$requireapproval) {
                approval::create_ai_reply($discussion, $replytext, $post->id);
            }
        } catch (\Throwable $e) {
            debugging('Error in process_ai_post task: ' . $e->getMessage(), DEBUG_DEVELOPER);
            throw $e;
        }
    }
}
