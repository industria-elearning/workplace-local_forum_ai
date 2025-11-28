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

namespace local_forum_ai\helper;

/**
 * Marking guide helper class for forum AI integration.
 *
 * This class is responsible for retrieving and formatting the marking guide
 * configuration associated with a forum activity. It extracts the active
 * guide definition, its criteria and predefined comments, and structures
 * the data in a normalized array format suitable for AI processing.
 *
 * @package local_forum_ai
 * @copyright 2025 Datacurso
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guide {
    /**
     * Retrieves the marking guide structure for a specific forum module.
     *
     * This method loads the active grading area configured for forum
     * assessments using the "guide" method and returns a structured
     * representation including title, description, criteria and predefined
     * comments. If no valid guide is found, null is returned.
     *
     * @param int $cmid Course module ID of the forum.
     * @return array|null Structured marking guide data or null if not available.
     */
    public static function get(int $cmid): ?array {
        global $DB;

        $context = \context_module::instance($cmid);

        $area = $DB->get_record('grading_areas', [
            'contextid' => $context->id,
            'component' => 'mod_forum',
            'areaname' => 'forum',
        ]);

        if (!$area) {
            return null;
        }

        $definition = $DB->get_record('grading_definitions', [
            'areaid' => $area->id,
            'method' => 'guide',
        ]);

        if (!$definition) {
            return null;
        }

        $criteria = $DB->get_records('gradingform_guide_criteria', [
            'definitionid' => $definition->id,
        ]);

        if (!$criteria) {
            return null;
        }

        $guide = [
            'title' => $definition->name,
            'description' => trim(strip_tags($definition->description)),
            'criteria' => [],
            'predefined_comments' => [],
        ];

        foreach ($criteria as $criterion) {
            $guide['criteria'][] = [
                'criterion' => $criterion->shortname,
                'description_students' => $criterion->description,
                'description_evaluators' => $criterion->descriptionmarkers,
                'maximum_score' => (float) $criterion->maxscore,
            ];
        }

        $comments = $DB->get_records('gradingform_guide_comments', [
            'definitionid' => $definition->id,
        ]);

        if ($comments) {
            foreach ($comments as $comment) {
                if (!empty(trim($comment->description))) {
                    $guide['predefined_comments'][] = trim(strip_tags($comment->description));
                }
            }
        }

        return $guide;
    }
}
