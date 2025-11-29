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

namespace local_coursegroups;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

class observer {

    /**
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function local_coursegroups_handle_role_assigned(\core\event\role_assigned $event) {
        global $DB;

        // Проверка включён ли плагин
        $isenabled = get_config('local_coursegroups', 'isenabled');
        if (!$isenabled) {
            return;
        }

        // Проверка даты начала курса
        $ignoreolddate = (int)get_config('local_coursegroups', 'ignoreolddate');
        if ($ignoreolddate > 0) {
            $course = $DB->get_record('course', ['id' => $event->courseid], 'id,startdate');
            if ($course && $course->startdate < $ignoreolddate) {
                return;
            }
        }

        $data = $event->get_data();
        $userid = $data['relateduserid'] ?? null;
        $courseid = $data['courseid'] ?? null;

        if (!$userid || !$courseid) {
            return;
        }

        // проверка роли
        $context = \context_course::instance($courseid);
        $roles = get_user_roles($context, $userid, true);

        $isstudent = false;
        if (!empty($roles)) {
            foreach ($roles as $role) {
                if ($role->shortname === 'student') {
                    $isstudent = true;
                    break;
                }
            }
        }

        if (!$isstudent) {
            return;
        }

        // подгрузка полей пользователя
        $user = \core_user::get_user($userid);
        profile_load_custom_fields($user);

        $stgroup = $user->profile['stGroup'] ?? null;
        if (empty($stgroup)) {
            return; // если у пользователя не указано stGroup, то ничего не делаем
        }

        // существует ли группа с таким же названием в курсе
        $existinggroup = $DB->get_record('groups', ['courseid' => $courseid, 'name' => $stgroup]);

        if ($existinggroup) {
            $groupid = $existinggroup->id;
        } else {
            // Создаём новую группу
            $newgroup = new \stdClass();
            $newgroup->courseid = $courseid;
            $newgroup->name = $stgroup;
            $newgroup->timecreated = time();
            $newgroup->timemodified = time();

            $groupid = groups_create_group($newgroup);
            if (!$groupid) {
                return;
            }
        }

        // добавление пользователя в группу если его там нет
        if (!groups_is_member($groupid, $userid)) {
            groups_add_member($groupid, $userid);
        }
    }
}
