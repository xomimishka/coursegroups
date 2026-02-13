<?php

namespace local_coursegroups\task;

defined('MOODLE_INTERNAL') || die();

use core\task\adhoc_task;

require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

class handle_user_updated extends adhoc_task {

    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $userid = $data->userid ?? null;

        if (!$userid) {
            return;
        }

        $user = \core_user::get_user($userid, '*', IGNORE_MISSING);
        if (!$user) {
            return;
        }

        profile_load_custom_fields($user);
        $stgroup = $user->profile['stGroup'] ?? null;

        if (empty($stgroup)) {
            return;
        }

        // Получаем все курсы пользователя
        $courses = enrol_get_users_courses($userid, true);

        foreach ($courses as $course) {

            $context = \context_course::instance($course->id);

            // Проверяем роль student
            $roles = get_user_roles($context, $userid, true);
            $isstudent = false;

            foreach ($roles as $role) {
                if ($role->shortname === 'student') {
                    $isstudent = true;
                    break;
                }
            }

            if (!$isstudent) {
                continue;
            }

            // Удаление из старой лок группы
            $usergroups = groups_get_all_groups($course->id, $userid);

            if ($usergroups) {

                $pattern = '/^(?:КТ|ЭП|УЭ)[бса][озв]/u';

                foreach ($usergroups as $ugroup) {

                    if (preg_match($pattern, $ugroup->name)) {
                        groups_remove_member($ugroup->id, $userid);
                    }
                }
            }

            // Ищем или создаём группу
            $group = $DB->get_record('groups', [
                'courseid' => $course->id,
                'name'     => $stgroup,
            ]);

            if (!$group) {
                $newgroup = (object)[
                    'courseid'     => $course->id,
                    'name'         => $stgroup,
                    'timecreated'  => time(),
                    'timemodified' => time(),
                ];

                $groupid = groups_create_group($newgroup);
                if (!$groupid) {
                    continue;
                }

                $group = $newgroup;
                $group->id = $groupid;
            }

            if (!groups_is_member($group->id, $userid)) {
                groups_add_member($group->id, $userid);
            }
        }
    }
}