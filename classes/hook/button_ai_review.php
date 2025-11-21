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

namespace local_forum_ai\hook;

use core\hook\output\before_footer_html_generation;
use html_writer;

/**
 * Hook class responsible for injecting the AI review button into forum pages.
 *
 * This hook listens to the before_footer_html_generation event and conditionally
 * adds a hidden button that triggers the AI review functionality when the forum
 * grading interface is opened.
 *
 * The button is only added on forum view pages and is initialized via an AMD
 * JavaScript module.
 *
 * @package     local_forum_ai
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class button_ai_review {
    /**
     * Callback executed before the footer HTML is generated.
     *
     * This method verifies that the current page corresponds to the forum
     * view and, if so, loads the required AMD JavaScript module and injects
     * a hidden AI review button into the page output.
     *
     * The button will be displayed dynamically when the grader interface
     * becomes available.
     *
     * @param before_footer_html_generation $hook The hook event instance.
     * @return void
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE;

        // Only execute on forum pages.
        if ($PAGE->url->get_path() !== '/mod/forum/view.php') {
            return;
        }

        // Load the AMD JavaScript module.
        $PAGE->requires->js_call_amd('local_forum_ai/analyze', 'init');

        // Hidden by default, will be displayed when the grader is opened.
        $button = html_writer::tag('button', get_string('ai_review_button', 'local_forum_ai'), [
            'id' => 'forum-ai-review-btn',
            'class' => 'btn btn-primary',
            'style' => 'display: none; margin-bottom: 1rem;',
        ]);

        $hook->add_html($button);
    }
}
