<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('local_coursegroups_runall');

global $DB;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('runrebuild', 'local_coursegroups'));

if (!get_config('local_coursegroups', 'isenabled')) {
    echo $OUTPUT->notification(
        get_string('plugindisabled', 'local_coursegroups'),
        'warning'
    );
    echo $OUTPUT->footer();
    exit;
}

$ignoreolddate = (int)get_config('local_coursegroups', 'ignoreolddate');
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

$processed = 0;

$courses = $DB->get_records('course', null, '', 'id, startdate');

foreach ($courses as $course) {

    if ($course->id == SITEID) {
        continue;
    }

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

        $pattern = '/^(?:КТ|ЭП|УЭ)[бса][озв]/u';
        $usergroups = groups_get_all_groups($course->id, $user->id);

        if ($usergroups) {
            foreach ($usergroups as $ugroup) {
                if (preg_match($pattern, $ugroup->name) && $ugroup->name !== $stgroup) {
                    groups_remove_member($ugroup->id, $user->id);
                }
            }
        }

        $group = $DB->get_record('groups', [
            'courseid' => $course->id,
            'name' => $stgroup,
        ]);

        if (!$group) {
            $group = (object)[
                'courseid' => $course->id,
                'name' => $stgroup,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $group->id = groups_create_group($group);
        }

        if (!groups_is_member($group->id, $user->id)) {
            groups_add_member($group->id, $user->id);
        }

        $processed++;
    }
}

echo $OUTPUT->notification(
    "Переопределение локальные групп завершено. Обработано пользователей: {$processed}",
    'success'
);

echo $OUTPUT->footer();