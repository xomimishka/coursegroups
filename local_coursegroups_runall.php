<?php
require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/lib/sessionlib.php');

global $DB;

error_log('local_coursegroups_runall STARTED: ' . date('Y-m-d H:i:s'));

// Проверка включён ли плагин
if (!get_config('local_coursegroups', 'isenabled')) {
    error_log('local_coursegroups_runall: plugin disabled');
    redirect(new moodle_url('/admin/settings.php?section=local_coursegroups_settings'), 
             'Плагин выключен', null, \core\output\notification::WARNING);
}

$ignoreolddate = (int)get_config('local_coursegroups', 'ignoreolddate');

// Роль student
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

// Все студенты в системе
$courses = $DB->get_records('course', null, '', 'id, startdate');

foreach ($courses as $course) {

    if ($ignoreolddate > 0 && $course->startdate < $ignoreolddate) {
        continue;
    }

    $context = context_course::instance($course->id, IGNORE_MISSING);
    if (!$context) {
        continue;
    }

    $students = get_role_users(
        $studentrole->id,
        $context,
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
            $group = (object)[
                'courseid' => $course->id,
                'name'     => $stgroup,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $group->id = groups_create_group($group);
        }

        if (!groups_is_member($group->id, $user->id)) {
            groups_add_member($group->id, $user->id);
        }
    }
}