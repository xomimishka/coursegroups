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

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage(
        'local_coursegroups_settings',
        new lang_string('pluginname', 'local_coursegroups')
    );

    if ($ADMIN->fulltree) {

        // Включить / выключить плагин.
        $settings->add(new admin_setting_configcheckbox(
            'local_coursegroups/isenabled',
            new lang_string('isenabled', 'local_coursegroups'),
            new lang_string('isenabled_desc', 'local_coursegroups'),
            1
        ));

        // Ограничение по дате.
        $settings->add(new admin_setting_configtext(
            'local_coursegroups/ignoreolddate',
            new lang_string('ignoreolddate', 'local_coursegroups'),
            new lang_string('ignoreolddate_desc', 'local_coursegroups'),
            0,
            PARAM_INT
        ));
    }

    $ADMIN->add('localplugins', $settings);
}