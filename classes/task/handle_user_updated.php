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

        // Плагин выключен — ничего не делаем
        if (!get_config('local_coursegroups', 'isenabled')) {
            return;
        }

        $ignoreolddate = (int)get_config('local_coursegroups', 'ignoreolddate');

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

        $courses = enrol_get_users_courses($userid, true);

        foreach ($courses as $course) {

            if ($ignoreolddate > 0 && $course->startdate < $ignoreolddate) {
                continue;
            }

            $context = \context_course::instance($course->id);

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

            $usergroups = groups_get_all_groups($course->id, $userid);
            $pattern = '/^(?:КТ|ЭП|УЭ)[бса][озв]/u';

            if ($usergroups) {
                foreach ($usergroups as $ugroup) {
                    if (preg_match($pattern, $ugroup->name) && $ugroup->name !== $stgroup) {
                        groups_remove_member($ugroup->id, $userid);
                    }
                }
            }

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