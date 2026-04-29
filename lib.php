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

/**
 * Forum AI plugin configuration and hooks.
 *
 * @package    local_forum_ai
 * @category   admin
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends the settings navigation (the "More" menu in forum activities).
 *
 * Adds links to pending and history pages for AI responses in forum module settings.
 *
 * @param settings_navigation $nav The settings navigation object
 * @param context $context The current context
 * @return void
 */
function local_forum_ai_extend_settings_navigation(settings_navigation $nav, context $context) {
    global $PAGE, $USER;

    // Only apply in forum module contexts.
    if ($context->contextlevel != CONTEXT_MODULE || $PAGE->cm->modname !== 'forum') {
        return;
    }

    // Require capability to see AI management options.
    if (!has_capability('local/forum_ai:approveresponses', $context, $USER)) {
        return;
    }

    $forumid = $PAGE->cm->instance;
    $courseid = $PAGE->course->id;

    $urlpending = new moodle_url('/local/forum_ai/pending.php', [
        'courseid' => $courseid,
        'forumid'  => $forumid,
    ]);
    $urlhistory = new moodle_url('/local/forum_ai/history.php', [
        'courseid' => $courseid,
        'forumid'  => $forumid,
    ]);

    $modulesettings = $nav->find('modulesettings', navigation_node::TYPE_SETTING);

    if ($modulesettings) {
        $modulesettings->add(
            get_string('pendingresponses', 'local_forum_ai'),
            $urlpending,
            navigation_node::TYPE_SETTING,
            null,
            'forum_ai_pending',
            new pix_icon('i/warning', '')
        );
        $modulesettings->add(
            get_string('historyresponses', 'local_forum_ai'),
            $urlhistory,
            navigation_node::TYPE_SETTING,
            null,
            'forum_ai_history',
            new pix_icon('i/log', '')
        );
    }
}

/**
 * Extends the course navigation tree (left-hand side menu).
 *
 * Adds links to pending and history pages for AI responses in course navigation.
 *
 * @param navigation_node $navigation The navigation node object
 * @param stdClass $course The course object
 * @param stdClass $context The course context
 * @return void
 */
function local_forum_ai_extend_navigation_course($navigation, $course, $context) {
    global $USER;

    // Only display if the user has the approveresponses capability.
    if (!has_capability('local/forum_ai:approveresponses', $context, $USER)) {
        return;
    }

    $pendingurl = new moodle_url('/local/forum_ai/pending.php', ['courseid' => $course->id]);
    $historyurl = new moodle_url('/local/forum_ai/history.php', ['courseid' => $course->id]);

    $navigation->add(
        get_string('pendingresponses', 'local_forum_ai'),
        $pendingurl,
        navigation_node::TYPE_SETTING,
        null,
        'forum_ai_pending',
        new pix_icon('i/warning', '')
    );

    $navigation->add(
        get_string('historyresponses', 'local_forum_ai'),
        $historyurl,
        navigation_node::TYPE_SETTING,
        null,
        'forum_ai_history',
        new pix_icon('i/log', '')
    );
}

/**
 * Extends the forum module edit form with AI-related fields.
 *
 *
 * @param mod_forum_mod_form $formwrapper The forum module form wrapper
 * @param MoodleQuickForm $mform The Moodle quick form object
 * @return void
 */
function local_forum_ai_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB, $USER;

    // Only for forum.
    if ($formwrapper->get_current()->modulename !== 'forum') {
        return;
    }

    $cm = $formwrapper->get_current();
    $forumid = $cm->instance ?? null;
    $context = context_course::instance($cm->course);
    $tenantid = \tool_tenant\tenancy::get_tenant_id();

    if (!has_capability('local/forum_ai:approveresponses', $context, $USER)) {
        return;
    }

    $defaults = (object)[
        'enabled' => 1,
        'require_approval' => 1,
        'reply_message' => get_string('default_reply_message', 'local_forum_ai'),
        'enablediainitconversation' => 0,
        'allowedroles' => [],
        'allowedroles_saved' => false,
        'graderid' => null,
        'usedelay' => 0,
        'delayminutes' => 60,
    ];

    // Load custom config if exists.
    if ($forumid && $DB->record_exists('local_forum_ai_config', ['forumid' => $forumid])) {
        $record = $DB->get_record('local_forum_ai_config', ['forumid' => $forumid]);

        $defaults->enabled = $record->enabled;
        $defaults->require_approval = $record->require_approval;
        $defaults->reply_message = $record->reply_message;
        $defaults->enablediainitconversation = $record->enablediainitconversation ?? 0;
        $defaults->graderid = $record->graderid ?? null;
        $defaults->allowedroles_saved = true;
        $defaults->usedelay = $record->usedelay ?? 0;
        $defaults->delayminutes = max(1, (int)($record->delayminutes ?? 60));

        if (empty($record->allowedroles)) {
            $defaults->allowedroles = [];
        } else {
            $sql = "SELECT * FROM {local_forum_ai_config}
                    WHERE forumid = :forumid AND tenantid = :tenantid";
            $params = [
                'forumid'  => $forumid,
                'tenantid' => $tenantid,
            ];
        }

        $record = $DB->get_record_sql($sql, $params);

        if (!$record && $tenantid !== null) {
            $record = $DB->get_record('local_forum_ai_config', [
                'forumid' => $forumid,
                'tenantid' => null,
            ]);
        }

        if ($record) {
            $defaults->enabled = (int)$record->enabled;
            $defaults->require_approval = (int)$record->require_approval;
            $defaults->reply_message = $record->reply_message;
            $defaults->enablediainitconversation = (int)($record->enablediainitconversation ?? 0);
            $defaults->graderid = $record->graderid ?? null;
            $defaults->allowedroles_saved = true;

            if (!empty($record->allowedroles)) {
                $defaults->allowedroles = explode(',', $record->allowedroles);
            }
        }
    }

    $mform->addElement(
        'header',
        'local_forum_ai_header',
        get_string('datacurso_custom', 'local_forum_ai')
    );

    // Enabled AI.
    $mform->addElement(
        'select',
        'local_forum_ai_enabled',
        get_string('enabled', 'local_forum_ai'),
        [0 => get_string('no'), 1 => get_string('yes')]
    );
    $mform->setDefault('local_forum_ai_enabled', $defaults->enabled);

    // Enable AI init conversation.
    $mform->addElement(
        'select',
        'enablediainitconversation',
        get_string('enablediainitconversation', 'local_forum_ai'),
        [
            0 => get_string('no', 'local_forum_ai'),
            1 => get_string('yes', 'local_forum_ai'),
        ]
    );
    $mform->setType('enablediainitconversation', PARAM_INT);
    $mform->addHelpButton(
        'enablediainitconversation',
        'enablediainitconversation',
        'local_forum_ai'
    );
    $mform->setDefault('enablediainitconversation', $defaults->enablediainitconversation);

    // Roles allowed to trigger AI.
    $roles = $DB->get_records('role', null, 'sortorder ASC');
    $roleoptions = [];
    $defaultroles = [];

    // Default: student roles.
    $studentroles = $DB->get_records('role', ['archetype' => 'student']);
    foreach ($studentroles as $sr) {
        $defaultroles[] = $sr->id;
    }

    foreach ($roles as $role) {
        $roleoptions[$role->id] = role_get_name($role);
    }

    $mform->addElement(
        'autocomplete',
        'allowedroles',
        get_string('allowedroles', 'local_forum_ai'),
        $roleoptions,
        ['multiple' => true, 'noselectionstring' => get_string('none')]
    );
    $mform->setType('allowedroles', PARAM_RAW);
    $mform->addHelpButton('allowedroles', 'allowedroles', 'local_forum_ai');
    $mform->setDefault(
        'allowedroles',
        $defaults->allowedroles_saved ? $defaults->allowedroles : $defaultroles
    );

    // Require approval.
    $mform->addElement(
        'select',
        'local_forum_ai_require_approval',
        get_string('require_approval', 'local_forum_ai'),
        [1 => get_string('yes'), 0 => get_string('no')]
    );
    $mform->setDefault('local_forum_ai_require_approval', $defaults->require_approval);

    $mform->addElement(
        'select',
        'local_forum_ai_usedelay',
        get_string('usedelay', 'local_forum_ai'),
        [0 => get_string('no'), 1 => get_string('yes')]
    );
    $mform->addHelpButton('local_forum_ai_usedelay', 'usedelay', 'local_forum_ai');
    $mform->setDefault('local_forum_ai_usedelay', $defaults->usedelay);

    $mform->addElement(
        'text',
        'local_forum_ai_delayminutes',
        get_string('delayminutes', 'local_forum_ai')
    );
    $mform->setType('local_forum_ai_delayminutes', PARAM_INT);
    $mform->addRule('local_forum_ai_delayminutes', null, 'numeric', null, 'client');
    $mform->addHelpButton('local_forum_ai_delayminutes', 'delayminutes', 'local_forum_ai');
    $mform->setDefault('local_forum_ai_delayminutes', $defaults->delayminutes);

    // Hide unless delay enabled.
    $mform->hideIf('local_forum_ai_usedelay', 'local_forum_ai_enabled', 'neq', 1);
    $mform->hideIf('local_forum_ai_usedelay', 'local_forum_ai_require_approval', 'eq', 1);

    $mform->hideIf('local_forum_ai_delayminutes', 'local_forum_ai_usedelay', 'neq', 1);
    $mform->hideIf('local_forum_ai_delayminutes', 'local_forum_ai_enabled', 'neq', 1);
    $mform->hideIf('local_forum_ai_delayminutes', 'local_forum_ai_require_approval', 'eq', 1);

    // Users enrolled who can either rate or grade.
    $eligibleusers = [];

    // Get users with forum rating capability.
    $raters = get_enrolled_users($context, 'mod/forum:rate');
    foreach ($raters as $u) {
        $eligibleusers[$u->id] = fullname($u);
    }

    // Get users with forum grading capability.
    $graders = get_enrolled_users($context, 'mod/forum:grade');
    foreach ($graders as $u) {
        $eligibleusers[$u->id] = fullname($u);
    }

    // Sort alphabetically.
    if (!empty($eligibleusers)) {
        \core_collator::asort($eligibleusers);
    }

    // Ensure saved grader appears even if not currently enrolled.
    if ($defaults->graderid && !isset($eligibleusers[$defaults->graderid])) {
        $saveduser = $DB->get_record(
            'user',
            ['id' => $defaults->graderid],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
        );
        if ($saveduser) {
            $eligibleusers[$saveduser->id] = fullname($saveduser);
        }
    }

    $mform->addElement(
        'autocomplete',
        'local_forum_ai_grader',
        get_string('autogradegrader', 'local_forum_ai'),
        $eligibleusers,
        [
            'multiple' => false,
            'maxitems' => 1,
            'noselectionstring' => get_string('none'),
        ]
    );
    $mform->setType('local_forum_ai_grader', PARAM_INT);
    $mform->addHelpButton('local_forum_ai_grader', 'autogradegrader', 'local_forum_ai');

    if ($defaults->graderid) {
        $mform->setDefault('local_forum_ai_grader', (int)$defaults->graderid);
    }

    // Hide grader field unless AI is enabled.
    $mform->hideIf('local_forum_ai_grader', 'local_forum_ai_enabled', 'neq', 1);
    $mform->hideIf('local_forum_ai_grader', 'local_forum_ai_require_approval', 'eq', 1);

    // Reply AI template.
    $mform->addElement(
        'textarea',
        'local_forum_ai_reply_message',
        get_string('reply_message', 'local_forum_ai'),
        'wrap="virtual" rows="3" cols="50"'
    );
    $mform->setType('local_forum_ai_reply_message', PARAM_TEXT);
    $mform->setDefault('local_forum_ai_reply_message', $defaults->reply_message);
}

/**
 * Saves or updates forum AI configuration when editing a forum.
 *
 *
 * @param stdClass $data The form data submitted
 * @param stdClass $course The course object
 * @return stdClass The original data object (unchanged)
 */
function local_forum_ai_coursemodule_edit_post_actions($data, $course) {
    global $DB;

    if ($data->modulename !== 'forum') {
        return $data;
    }

    $tenantid = \tool_tenant\tenancy::get_tenant_id();

    // Search for existing configuration for this forum and tenant.
    if ($tenantid === null) {
        $sql = "SELECT * FROM {local_forum_ai_config}
                WHERE forumid = :forumid AND tenantid IS NULL";
        $params = ['forumid' => $data->instance];
    } else {
        $sql = "SELECT * FROM {local_forum_ai_config}
                WHERE forumid = :forumid AND tenantid = :tenantid";
        $params = [
            'forumid'  => $data->instance,
            'tenantid' => $tenantid,
        ];
    }

    $record = $DB->get_record_sql($sql, $params);

    if (!$record && $tenantid !== null) {
        $legacyrecord = $DB->get_record('local_forum_ai_config', [
            'forumid' => $data->instance,
            'tenantid' => null,
        ]);

        if ($legacyrecord) {
            $legacyrecord->tenantid = $tenantid;
            $legacyrecord->timemodified = time();
            $DB->update_record('local_forum_ai_config', $legacyrecord);
            $record = $legacyrecord;
        }
    }

    // Prepare configuration data.
    $config = new stdClass();
    $config->forumid = $data->instance;
    $config->tenantid = $tenantid;
    $config->enabled = $data->local_forum_ai_enabled ?? 0;
    $config->require_approval = $data->local_forum_ai_require_approval ?? 1;
    $config->reply_message = $data->local_forum_ai_reply_message ?? '';
    $config->enablediainitconversation = $data->enablediainitconversation ?? 0;
    $config->graderid = $data->local_forum_ai_grader ?? null;
    $config->usedelay = $data->local_forum_ai_usedelay ?? 0;
    $config->delayminutes = max(1, (int)($data->local_forum_ai_delayminutes ?? 60));

    // Process allowed roles.
    if (!empty($data->allowedroles) && is_array($data->allowedroles)) {
        $config->allowedroles = implode(',', $data->allowedroles);
    } else {
        $config->allowedroles = null;
    }

    $config->timemodified = time();

    // Update existing record or insert new one.
    if ($record) {
        $config->id = $record->id;
        $DB->update_record('local_forum_ai_config', $config);
    } else {
        $config->timecreated = time();
        $DB->insert_record('local_forum_ai_config', $config);
    }

    return $data;
}
