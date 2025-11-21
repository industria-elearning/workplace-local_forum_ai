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
 * Rubric helper class for forum AI integration.
 *
 * This class is responsible for retrieving and formatting the rubric
 * configuration associated with a forum activity. It extracts the active
 * rubric definition, its criteria and levels, and structures the data
 * into a standardized array format suitable for AI evaluation processing.
 *
 * @package local_forum_ai
 * @copyright 2025 Datacurso
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rubric {
    /**
     * Retrieves the rubric structure for a specific forum module.
     *
     * This method loads the active grading area configured for forum
     * assessments using the "rubric" method and returns a structured
     * representation including title, description, criteria and level
     * definitions. If no valid rubric is found, null is returned.
     *
     * @param int $cmid Course module ID of the forum.
     * @return array|null Structured rubric data or null if not available.
     */
    public static function get(int $cmid): ?array {
        global $DB;

        $context = \context_module::instance($cmid);

        $area = $DB->get_record('grading_areas', [
            'contextid' => $context->id,
            'component' => 'mod_forum',
            'areaname'  => 'forum',
        ]);

        if (!$area) {
            return null;
        }

        $definition = $DB->get_record('grading_definitions', [
            'areaid' => $area->id,
            'method' => 'rubric',
        ]);

        if (!$definition) {
            return null;
        }

        $criteria = $DB->get_records('gradingform_rubric_criteria', [
            'definitionid' => $definition->id,
        ]);

        if (!$criteria) {
            return null;
        }

        $rubric = [
            'title' => $definition->name,
            'description' => $definition->description,
            'criteria' => [],
        ];

        foreach ($criteria as $criterion) {
            $levels = $DB->get_records('gradingform_rubric_levels', [
                'criterionid' => $criterion->id,
            ]);

            $crit = [
                'criterion' => $criterion->description,
                'levels' => [],
            ];

            foreach ($levels as $level) {
                $crit['levels'][] = [
                    'points' => (int) $level->score,
                    'description' => $level->definition,
                ];
            }

            $rubric['criteria'][] = $crit;
        }

        return $rubric;
    }
}
