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
 * Handle user enrolment events: adds user to a local course group based on stGroup field.
 *
 * @package     local_coursegroups
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php'); // для пользовательских полей

global $DB;

$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

if (!$userid || !$courseid) {
    die();
}

// подгрузка полей пользователя
$user = core_user::get_user($userid);
if (!$user) {
    die();
}
profile_load_custom_fields($user);

$stgroup = $user->profile['stGroup'] ?? null;

// роли пользователя в контексте курса
$context = context_course::instance($courseid);
$roles = get_user_roles($context, $userid, true);

$rolenames = [];
if (!empty($roles)) {
    foreach ($roles as $r) {
        $localname = role_get_name($r, $context);
        $rolenames[] = "{$localname} (shortname: {$r->shortname})";
    }
}

echo "<p><strong>ФИО:</strong> " . fullname($user) . "</p>";
echo "<p><strong>ID:</strong> {$userid}</p>";
echo "<p><strong>stGroup:</strong> " . (!empty($stgroup) ? s($stgroup) : 'не указана') . "</p>";
echo "<p><strong>Локальные роли в курсе:</strong> " . (!empty($rolenames) ? implode(', ', $rolenames) : 'нет ролей в курсе') . "</p>";