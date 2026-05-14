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

/**
 * Tenant settings page for local_forum_ai.
 *
 * @package     local_forum_ai
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

if (!class_exists('\\tool_tenant\\tenancy')) {
    throw new moodle_exception('error');
}

$tenantid = \local_forum_ai\utils::get_current_tenant_id();

$url = new moodle_url('/local/forum_ai/admin/settings_tenant.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_forum_ai'));
$PAGE->set_heading(get_string('pluginname', 'local_forum_ai'));

$form = new \local_forum_ai\form\settings_tenant_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/category.php', ['category' => 'localplugins']));
}

if ($data = $form->get_data()) {
    $values = [
        'enableforumai' => empty($data->enableforumai) ? '0' : '1',
        'default_enabled' => empty($data->default_enabled) ? '0' : '1',
        'default_enablediainitconversation' => empty($data->default_enablediainitconversation) ? '0' : '1',
        'default_require_approval' => empty($data->default_require_approval) ? '0' : '1',
        'default_usedelay' => empty($data->default_usedelay) ? '0' : '1',
        'default_delayminutes' => (string)max(1, (int)$data->default_delayminutes),
        'default_question_turns' => (string)max(0, min(3, (int)$data->default_question_turns)),
        'default_reply_message' => trim((string)$data->default_reply_message),
    ];

    if ($values['default_reply_message'] === '') {
        $values['default_reply_message'] = get_string('default_reply_message', 'local_forum_ai');
    }

    foreach ($values as $name => $value) {
        \local_forum_ai\config\tenant_config::set('local_forum_ai', $tenantid, $name, $value);
    }

    redirect($url, get_string('changessaved'));
}

$defaults = \local_forum_ai\utils::get_default_values($tenantid);
$enabled = \local_forum_ai\utils::get_plugin_setting('enableforumai', 1, $tenantid);

$form->set_data((object)[
    'enableforumai' => (int)$enabled,
    'default_enabled' => (int)$defaults->enabled,
    'default_enablediainitconversation' => (int)$defaults->enablediainitconversation,
    'default_require_approval' => (int)$defaults->require_approval,
    'default_usedelay' => (int)$defaults->usedelay,
    'default_delayminutes' => max(1, (int)$defaults->delayminutes),
    'default_question_turns' => (int)$defaults->questionturns,
    'default_reply_message' => (string)$defaults->reply_message,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_forum_ai'));
$form->display();
echo $OUTPUT->footer();
