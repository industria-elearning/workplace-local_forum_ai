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

use aiprovider_datacurso\httpclient\ai_services_api;

/**
 * Class for AI service communication.
 *
 * @package    local_forum_ai
 * @category   event
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_service {
    /**
     * Sends the payload to the external AI service and returns its reply.
     *
     * @param array $payload Data to send to the AI service.
     * @return string The AI-generated reply.
     * @throws \moodle_exception If the request fails.
     */
    public static function call_ai_service(array $payload): string {
        $client = new ai_services_api();
        $response = $client->request('POST', '/forum/chat', $payload);
        return $response['reply'];
    }
}