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
 * Adhoc task: add user to course group based on stGroup profile field.
 *
 * @package     local_coursegroups
 * @copyright   2025 Your Name
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursegroups\task;

defined('MOODLE_INTERNAL') || die();

use core\task\adhoc_task;

require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

class handle_role_assigned extends adhoc_task {

    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $userid   = $data->userid ?? null;
        $courseid = $data->courseid ?? null;

        if (!$userid || !$courseid) {
            return;
        }

        // Проверка существования пользователя
        $user = \core_user::get_user($userid, '*', IGNORE_MISSING);
        if (!$user) {
            return;
        }

        // Контекст курса
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return;
        }

        // Проверка роли "student"
        $roles = get_user_roles($context, $userid, true);
        $isstudent = false;

        foreach ($roles as $role) {
            if ($role->shortname === 'student') {
                $isstudent = true;
                break;
            }
        }

        if (!$isstudent) {
            return;
        }

        // Загрузка пользовательских полей профиля
        profile_load_custom_fields($user);

        $stgroup = $user->profile['stGroup'] ?? null;
        if (empty($stgroup)) {
            return;
        }

        // Поиск группы в курсе
        $group = $DB->get_record('groups', [
            'courseid' => $courseid,
            'name'     => $stgroup,
        ]);

        // Создание группы, если её нет
        if (!$group) {
            $newgroup = (object)[
                'courseid'     => $courseid,
                'name'         => $stgroup,
                'timecreated'  => time(),
                'timemodified' => time(),
            ];

            $groupid = groups_create_group($newgroup);
            if (!$groupid) {
                return;
            }

            $group = $newgroup;
            $group->id = $groupid;
        }

        // Добавление пользователя в группу
        if (!groups_is_member($group->id, $userid)) {
            groups_add_member($group->id, $userid);
            mtrace("local_coursegroups: user {$userid} added to group {$stgroup} in course {$courseid}");
        }
    }
}