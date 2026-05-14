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

namespace local_forum_ai\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Tenant-specific settings form for local_forum_ai.
 *
 * @package     local_forum_ai
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_tenant_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'local_forum_ai_header', get_string('pluginname', 'local_forum_ai'));

        $mform->addElement(
            'advcheckbox',
            'enableforumai',
            get_string('enableforumai', 'local_forum_ai'),
            get_string('enableforumai_desc', 'local_forum_ai')
        );
        $mform->setType('enableforumai', PARAM_INT);

        $mform->addElement(
            'advcheckbox',
            'default_enabled',
            get_string('defaultenableai', 'local_forum_ai'),
            get_string('defaultenableai_desc', 'local_forum_ai')
        );
        $mform->setType('default_enabled', PARAM_INT);

        $mform->addElement(
            'advcheckbox',
            'default_enablediainitconversation',
            get_string('enablediainitconversation', 'local_forum_ai'),
            get_string('enablediainitconversation_help', 'local_forum_ai')
        );
        $mform->setType('default_enablediainitconversation', PARAM_INT);

        $mform->addElement(
            'advcheckbox',
            'default_require_approval',
            get_string('require_approval', 'local_forum_ai'),
            ''
        );
        $mform->setType('default_require_approval', PARAM_INT);

        $mform->addElement(
            'advcheckbox',
            'default_usedelay',
            get_string('usedelay', 'local_forum_ai'),
            get_string('usedelay_help', 'local_forum_ai')
        );
        $mform->setType('default_usedelay', PARAM_INT);

        $mform->addElement(
            'text',
            'default_delayminutes',
            get_string('delayminutes', 'local_forum_ai')
        );
        $mform->setType('default_delayminutes', PARAM_INT);
        $mform->addHelpButton('default_delayminutes', 'delayminutes', 'local_forum_ai');
        $mform->addRule('default_delayminutes', null, 'numeric', null, 'client');

        $questionturnoptions = [
            0 => '0',
            1 => '1',
            2 => '2',
            3 => '3',
        ];
        $mform->addElement(
            'select',
            'default_question_turns',
            get_string('questionturns', 'local_forum_ai'),
            $questionturnoptions
        );
        $mform->setType('default_question_turns', PARAM_INT);
        $mform->addHelpButton('default_question_turns', 'questionturns', 'local_forum_ai');

        $mform->addElement(
            'textarea',
            'default_reply_message',
            get_string('reply_message', 'local_forum_ai'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('default_reply_message', PARAM_TEXT);

        $mform->hideIf('default_enablediainitconversation', 'default_enabled', 'eq', 0);
        $mform->hideIf('default_require_approval', 'default_enabled', 'eq', 0);
        $mform->hideIf('default_usedelay', 'default_enabled', 'eq', 0);
        $mform->hideIf('default_delayminutes', 'default_enabled', 'eq', 0);
        $mform->hideIf('default_question_turns', 'default_enabled', 'eq', 0);
        $mform->hideIf('default_reply_message', 'default_enabled', 'eq', 0);

        $mform->hideIf('default_usedelay', 'default_require_approval', 'eq', 1);
        $mform->hideIf('default_delayminutes', 'default_require_approval', 'eq', 1);
        $mform->hideIf('default_delayminutes', 'default_usedelay', 'eq', 0);

        $this->add_action_buttons();
    }
}
