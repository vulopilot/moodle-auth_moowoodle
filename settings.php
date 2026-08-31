<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings and setup wizard registration.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

// Setup wizard entry, listed alongside the other authentication plugin pages.
$ADMIN->add(
    'authsettings',
    new admin_externalpage(
        'auth_moowoodle_setup_wizard',
        get_string('setupwizard', 'auth_moowoodle'),
        "$CFG->wwwroot/auth/moowoodle/setup_wizard.php",
        'moodle/site:config'
    )
);

if ($ADMIN->fulltree) {
    require_once(__DIR__ . '/classes/local/settings_handler.php');

    $settings->add(
        new admin_setting_heading(
            'auth_moowoodle/wizardheading',
            '',
            html_writer::tag(
                'div',
                get_string('settings_intro', 'auth_moowoodle') . ' ' .
                html_writer::link(
                    new moodle_url('/auth/moowoodle/setup_wizard.php'),
                    get_string('runsetupwizard', 'auth_moowoodle'),
                    ['class' => 'btn btn-primary ml-2']
                ),
                ['class' => 'auth-moowoodle-settings-intro']
            )
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'auth_moowoodle/wpsiteurl',
            get_string('wpsiteurl', 'auth_moowoodle'),
            get_string('wpsiteurl_message', 'auth_moowoodle'),
            '',
            PARAM_URL
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'auth_moowoodle/encryptkey',
            get_string('key', 'auth_moowoodle'),
            get_string('moowoodle_plugin_message', 'auth_moowoodle'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'auth_moowoodle/timelimit',
            get_string('timelimit', 'auth_moowoodle'),
            get_string('timelimit_message', 'auth_moowoodle'),
            60,
            PARAM_INT
        )
    );

    // Web service / token status, kept in sync with what the setup wizard creates.
    $service = \auth_moowoodle\local\settings_handler::get_existing_service();

    if ($service) {
        $statusmessage = get_string('webservice_status_exists', 'auth_moowoodle', s($service->name));
    } else {
        $statusmessage = get_string('webservice_status_none', 'auth_moowoodle');
    }

    $manageurl = new moodle_url('/admin/settings.php', ['section' => 'webservicetokens']);
    $statushtml = html_writer::tag('p', $statusmessage) .
        html_writer::link($manageurl, get_string('managetokens', 'auth_moowoodle'));

    $settings->add(
        new admin_setting_heading(
            'auth_moowoodle/webservicestatus',
            get_string('webservice_heading', 'auth_moowoodle'),
            $statushtml
        )
    );
}
