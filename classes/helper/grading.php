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
 * Grading helper class for forum AI integration.
 *
 * This class provides utility methods to retrieve grading configuration
 * and active grading definitions associated with a specific forum module.
 * It is used to determine which grading method is currently enabled
 * (rubric, marking guide, or simple grading) for forum posts.
 *
 * @package local_forum_ai
 * @copyright 2025 Datacurso
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading {
    /**
     * Retrieves the active grading definition for a forum activity.
     *
     * This method obtains the grading area related to forum posts and
     * returns the active grading definition (status = 20) if one exists.
     * If no grading area or active definition is found, null is returned.
     *
     * @param int $cmid Course module ID of the forum.
     * @return object|null Grading definition record or null if not found.
     */
    public static function get_definition(int $cmid) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/grade/grading/form/lib.php');

        $context = \context_module::instance($cmid);

        $area = $DB->get_record('grading_areas', [
            'contextid' => $context->id,
            'component' => 'mod_forum',
            'areaname' => 'posts',
        ]);

        if (!$area) {
            return null;
        }

        return $DB->get_record('grading_definitions', [
            'areaid' => $area->id,
            'status' => \gradingform_controller::DEFINITION_STATUS_READY,
        ]);
    }
}
