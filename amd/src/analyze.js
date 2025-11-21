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
 * Displays and manages the AI Review button inside the forum grading panel,
 * keeping it persistent and preventing multiple submissions by showing
 * a loading state.
 *
 * @module      local_forum_ai/analyze
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/pubsub', 'core/ajax', 'core/str'], function ($, PubSub, Ajax, Str) {

    /**
     * Initializes the AI button integration for forum grading.
     *
     * @returns {void}
     */
    function init() {

        const $button = $('#forum-ai-review-btn');
        let lastState = null;

        if ($button.length === 0) {
            return;
        }

        /**
         * Injects the AI button into the appropriate grading container
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
         * Handles AI button click event.
         *
         * @param {Event} e Click event.
         */
        $(document).on('click', '#forum-ai-review-btn', async function (e) {
            e.preventDefault();

            const button = this;

            if (button.classList.contains('forum-ai-btnloading')) {
                return;
            }

            await setLoading(button);

            const cmid = new URLSearchParams(window.location.search).get('id');
            const userNode = document.querySelector('[data-region="name"][data-userid]');
            const userid = userNode ? userNode.getAttribute('data-userid') : null;

            if (!cmid || !userid) {
                resetLoading(button);
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

                if (response.type === 'simple') {
                    applySimpleGrade(data);
                }

                if (response.type === 'rubric') {
                    applyRubricGrade(data);
                }

                if (response.type === 'guide') {
                    applyGuideGrade(data);
                }

                resetLoading(button);

            }).fail(function () {
                resetLoading(button);
            });
        });

        /**
         * Observes changes in the user selector to re-inject the button
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
     * Sets loading state on button.
     *
     * @param {HTMLElement} button
     * @returns {Promise<void>}
     */
    async function setLoading(button) {

        const loadingText = await Str.get_string('evaluatingwithai', 'local_forum_ai');

        button.dataset.originalText = button.innerHTML;
        button.classList.add('forum-ai-btnloading');
        button.setAttribute('aria-disabled', 'true');
        button.style.pointerEvents = 'none';
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + loadingText;
    }

    /**
     * Restores button after processing.
     *
     * @param {HTMLElement} button
     */
    function resetLoading(button) {

        const originalText = button.dataset.originalText || '';

        button.classList.remove('forum-ai-btnloading');
        button.removeAttribute('aria-disabled');
        button.style.pointerEvents = '';
        button.innerHTML = originalText;
    }

    /**
     * Applies a direct simple grade.
     *
     * @param {Object} data
     */
    function applySimpleGrade(data) {
        $('input[name="grade"]').val(data.grade);
    }

    /**
     * Applies grading using rubric structure.
     *
     * @param {Array} rubricData
     */
    function applyRubricGrade(rubricData) {

        rubricData.forEach(function (crit) {

            document.querySelectorAll('.criterion').forEach(container => {

                const title = container.querySelector('h5');

                if (!title || title.textContent.trim() !== crit.criterion) {
                    return;
                }

                const selectedLevel = crit.levels[0];

                container.querySelectorAll('label').forEach(label => {
                    if (label.textContent.includes(selectedLevel.description)) {
                        const input = label.previousElementSibling;
                        if (input && input.type === 'radio') {
                            input.checked = true;
                        }
                    }
                });

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
     * @param {Object} guideData
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
