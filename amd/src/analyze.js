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

/**
 * Forum AI grading integration.
 *
 * Detects when the forum grading panel is opened and displays the
 * "AI Review" button, keeping it visible even when switching between
 * students during the grading process.
 *
 * @module      local_forum_ai/analyze
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/log', 'core/pubsub', 'core/ajax'], function ($, Log, PubSub, Ajax) {

    /**
     * Initializes the AI button integration for forum grading.
     *
     * Sets up observers, event listeners and DOM manipulation logic to
     * ensure the AI review button is correctly injected and remains
     * visible while navigating between students.
     *
     * @returns {void}
     */
    function init() {
        Log.debug('Forum AI Review: Initialized');

        const $button = $('#forum-ai-review-btn');
        let lastState = null;

        if ($button.length === 0) {
            Log.error('Forum AI Review: Button not found');
            return;
        }

        /**
         * Injects the AI button into the appropriate grading container
         * depending on the active grading method.
         * The button is automatically re-injected whenever Moodle rebuilds the DOM.
         *
         * @returns {void}
         */
        const injectButtonIntoGrader = function () {
            const simpleInput = document.querySelector('input[name="grade"]');
            const rubricForm = document.querySelector('form[id^="gradingform_rubric"]');
            const guideForm = document.querySelector('form[id^="gradingform_guide"]');

            let target = null;

            if (simpleInput) {
                target = simpleInput.closest('form');
            } else if (rubricForm) {
                target = rubricForm;
            } else if (guideForm) {
                target = guideForm;
            }

            if (!target || !$(target).is(':visible')) {
                if (lastState !== 'hidden') {
                    $button.hide();
                    lastState = 'hidden';
                }
                return;
            }

            // Always move the button to the active container
            if (!$button.parent().is(target)) {
                $button.detach().prependTo(target).show();
                lastState = 'visible';
            } else if (!$button.is(':visible')) {
                $button.show();
                lastState = 'visible';
            }
        };

        PubSub.subscribe('drawer-opened', function () {
            setTimeout(injectButtonIntoGrader, 300);
        });

        observeUserChange();
        setInterval(injectButtonIntoGrader, 800);

        /**
         * Click event handler for the AI button.
         *
         * @param {Event} e Click event.
         */
        $(document).on('click', '#forum-ai-review-btn', function (e) {
            e.preventDefault();

            const cmid = new URLSearchParams(window.location.search).get('id');
            const userNode = document.querySelector('[data-region="name"][data-userid]');
            const userid = userNode ? userNode.getAttribute('data-userid') : null;

            if (!cmid || !userid) {
                alert('Unable to detect the forum or the student');
                return;
            }

            Ajax.call([{
                methodname: 'local_forum_ai_process_review',
                args: {
                    cmid: parseInt(cmid, 10),
                    userid: parseInt(userid, 10)
                }
            }])[0].done(function (response) {

                const data = JSON.parse(response.data);

                switch (response.type) {
                    case 'simple':
                        applySimpleGrade(data);
                        break;
                    case 'rubric':
                        applyRubricGrade(data);
                        break;
                    case 'guide':
                        applyGuideGrade(data);
                        break;
                }

            }).fail(function (error) {
                Log.error('Forum AI Review error:', error);
                alert('AI Error: ' + (error.message || 'Unknown error'));
            });
        });

        /**
         * Observes changes in the user selector to re-inject the button
         * when Moodle dynamically switches the evaluated student.
         *
         * @returns {void}
         */
        function observeUserChange() {
            const container = document.querySelector('[data-region="user_picker"]');

            if (!container) {
                return;
            }

            const observer = new MutationObserver(function () {
                setTimeout(injectButtonIntoGrader, 300);
            });

            observer.observe(container, { childList: true, subtree: true });
        }
    }

    /**
     * Applies a direct simple grade.
     *
     * @param {Object} data Data returned by the AI.
     * @param {number} data.grade Final numeric grade.
     */
    function applySimpleGrade(data) {
        $('input[name="grade"]').val(data.grade);
    }

    /**
     * Applies grading using rubric structure.
     *
     * @param {Array} rubricData Rubric data returned by the AI.
     */
    function applyRubricGrade(rubricData) {

        rubricData.forEach(function (crit) {

            document.querySelectorAll('.criterion').forEach(container => {

                const title = container.querySelector('h5');

                if (!title || title.textContent.trim() !== crit.criterion) {
                    return;
                }

                // Select level by description
                const selectedLevel = crit.levels[0];

                container.querySelectorAll('label').forEach(label => {
                    if (label.textContent.includes(selectedLevel.description)) {
                        const input = label.previousElementSibling;
                        if (input && input.type === 'radio') {
                            input.checked = true;
                        }
                    }
                });

                // Inject feedback into textarea
                const textarea = container.querySelector('textarea');
                if (textarea && crit.reply) {
                    textarea.value = crit.reply;
                }

            });

        });
    }

    /**
     * Applies grading using marking guide structure.
     *
     * @param {Object} guideData Object containing criteria evaluated by AI.
     */
    function applyGuideGrade(guideData) {

        document.querySelectorAll('[data-gradingform-guide-role="criterion"]').forEach(container => {

            const title = container.querySelector('h5').textContent.trim();

            if (!guideData[title]) {
                return;
            }

            const result = guideData[title];
            const inputScore = container.querySelector('input[type="number"]');
            const textarea = container.querySelector('textarea');

            if (inputScore) {
                inputScore.value = result.grade;
            }

            if (textarea) {
                textarea.value = result.reply.join('\n');
            }
        });
    }

    return {
        init: init
    };
});
