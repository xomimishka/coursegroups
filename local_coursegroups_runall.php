<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

// Проверка включён ли плагин
if (!get_config('local_coursegroups', 'isenabled')) {
    throw new moodle_exception('plugindisabled', 'local_coursegroups');
}

$ignoreolddate = (int)get_config('local_coursegroups', 'ignoreolddate');

// Роль student
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

// Все студенты в системе
$students = get_role_users(
    $studentrole->id,
    context_system::instance(),
    false,
    'u.*',
    'u.id'
);

foreach ($students as $user) {

    profile_load_custom_fields($user);
    $stgroup = $user->profile['stGroup'] ?? null;
    if (empty($stgroup)) {
        continue;
    }

    // Курсы пользователя
    $courses = enrol_get_users_courses($user->id, true);

    foreach ($courses as $course) {

        if ($ignoreolddate > 0 && $course->startdate < $ignoreolddate) {
            continue;
        }

        $usergroups = groups_get_all_groups($course->id, $user->id);
        $pattern = '/^(?:КТ|ЭП|УЭ)[бса][озв]/u';

        if ($usergroups) {
            foreach ($usergroups as $ugroup) {
                if (preg_match($pattern, $ugroup->name) && $ugroup->name !== $stgroup) {
                    groups_remove_member($ugroup->id, $user->id);
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

        if (!groups_is_member($group->id, $user->id)) {
            groups_add_member($group->id, $user->id);
        }
    }
}

redirect(
    new moodle_url('/admin/settings.php', ['section' => 'local_coursegroups_settings']),
    get_string('runrebuilddone', 'local_coursegroups'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);