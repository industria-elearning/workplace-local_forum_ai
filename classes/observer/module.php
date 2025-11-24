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

require_once(__DIR__ . '/../../locallib.php');

use core\event\course_module_created;
use core\event\course_module_deleted;

/**
 * Observer for course module events.
 *
 * @package    local_forum_ai
 * @category   event
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module {
    /**
     * Handles creation of "single type" forums.
     *
     * @param course_module_created $event The event triggered when a module is created.
     * @return bool True on success or when no action is needed, false on error.
     */
    public static function course_module_created(course_module_created $event): bool {
        global $DB;

        try {
            if ($event->other['modulename'] !== 'forum') {
                return true;
            }

            $forumid = $event->other['instanceid'];
            $forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);

            if ($forum->type !== 'single') {
                return true;
            }

            $maxattempts = 5;
            $discussion = null;

            for ($i = 0; $i < $maxattempts; $i++) {
                $discussion = $DB->get_record(
                    'forum_discussions',
                    ['forum' => $forum->id],
                    '*',
                    IGNORE_MULTIPLE
                );

                if ($discussion) {
                    break;
                }

                sleep(1);
            }

            if (!$discussion) {
                return true;
            }

            $singleevent = \mod_forum\event\discussion_created::create([
            'objectid' => $discussion->id,
            'context' => $event->get_context(),
            'courseid' => $event->courseid,
            'relateduserid' => $discussion->userid,
            'other' => ['forumid' => $forumid],
            ]);

            discussion::discussion_created($singleevent);

            return true;
        } catch (\Throwable $e) {
            debugging('General error in course module created: ' . $e->getMessage(), DEBUG_DEVELOPER);

            \core\notification::add(
                get_string('error_airequest', 'local_forum_ai', $e->getMessage()),
                \core\output\notification::NOTIFY_ERROR
            );

            return true;
        }
    }


    /**
     * Triggered when a course module is deleted.
     *
     * @param course_module_deleted $event
     * @return void
     */
    public static function forum_deleted(course_module_deleted $event): void {
        global $DB;

        if (!isset($event->other['modulename']) || $event->other['modulename'] !== 'forum') {
            return;
        }

        if (!isset($event->other['instanceid'])) {
            debugging('forum_deleted: missing instanceid in event->other', DEBUG_DEVELOPER);
            return;
        }

        $forumid = $event->other['instanceid'];

        $DB->delete_records('local_forum_ai_config', ['forumid' => $forumid]);
        $DB->delete_records('local_forum_ai_pending', ['forumid' => $forumid]);
    }
}
