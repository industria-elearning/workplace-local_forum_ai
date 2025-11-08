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
 * Local forum ai render post
 *
 * @module      local_forum_ai/render_post
 * @copyright   2025 Datacurso
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import { get_string as getString } from 'core/str';

/**
 * Renders an individual post with its corresponding indentation level
 *
 * @param {Object} post Post data
 * @returns {Promise<string>} Post HTML (async because it loads translations if not already cached)
 */
export async function renderPost(post) {
    const [replyTemplate, levelTemplate] = await Promise.all([
        getString('replylevel', 'local_forum_ai'),
        getString('level', 'local_forum_ai'),
    ]);

    const level = post.level;
    const marginLeft = level * 30;

    let borderClass = 'border-left-primary';
    if (level === 1) {
        borderClass = 'border-left-info';
    } else if (level === 2) {
        borderClass = 'border-left-success';
    } else if (level >= 3) {
        borderClass = 'border-left-warning';
    }

    let levelIndicator = '';
    let hasLevel = false;
    if (level > 0) {
        const replyText = replyTemplate.replace('{$a}', level);
        levelIndicator = `${'↳ '.repeat(level)}${replyText}`;
        hasLevel = true;
    }

    const levelText = levelTemplate.replace('{$a}', level);

    return Templates.render('local_forum_ai/render_post', {
        borderclass: borderClass,
        marginleft: marginLeft,
        haslevel: hasLevel,
        levelindicator: levelIndicator,
        subject: post.subject,
        leveltext: levelText,
        author: post.author,
        created: post.created,
        message: post.message,
    });
}
