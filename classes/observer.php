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

class observer {

    /**
     * @param \core\event\role_assigned $event
     * @return void
     */
    // Событии назначения роли пользователю
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

        if (empty($data['relateduserid']) || empty($data['courseid'])) {
            return;
        }

        // Создание задачи для добавления пользователя в группу
        $task = new \local_coursegroups\task\handle_role_assigned();
        $task->set_custom_data([
            'userid'   => $data['relateduserid'],
            'courseid'=> $data['courseid'],
        ]);

        \core\task\manager::queue_adhoc_task($task);
    }

    public static function local_coursegroups_handle_user_updated(\core\event\user_updated $event) {
    global $DB;

    // Проверка включён ли плагин
    $isenabled = get_config('local_coursegroups', 'isenabled');
    if (!$isenabled) {
        return;
    }

    $task = new \local_coursegroups\task\handle_user_updated();
    $task->set_custom_data([
        'userid' => $event->objectid,
    ]);

    \core\task\manager::queue_adhoc_task($task);
}
}
