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
 * Guided setup wizard for connecting Moodle to the MooWoodle WordPress plugin.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

use auth_moowoodle\local\setup_wizard;
use auth_moowoodle\local\settings_handler;
use auth_moowoodle\settings\connection_form;
use auth_moowoodle\settings\webservice_form;
use core\context\system as context_system;

admin_externalpage_setup('auth_moowoodle_setup_wizard');

$context = context_system::instance();

$step = optional_param('step', '', PARAM_ALPHA);

if (!setup_wizard::is_valid_step($step)) {
    $stored = get_config('auth_moowoodle', 'setup_progress');
    $step = $stored ? setup_wizard::get_next_step($stored) : '';

    if ($step === '') {
        $step = setup_wizard::get_first_step();
    }
}

$pageurl = new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => $step]);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('setupwizard', 'auth_moowoodle'));
$PAGE->set_heading(get_string('setupwizard', 'auth_moowoodle'));

// Simple GET+sesskey "continue" actions (steps with nothing to submit).
if (data_submitted() && optional_param('continuestep', 0, PARAM_BOOL)) {
    require_sesskey();
    setup_wizard::mark_step_complete($step);
    $next = setup_wizard::get_next_step($step);
    redirect(new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => $next ?: $step]));
}

if (data_submitted() && optional_param('restartwizard', 0, PARAM_BOOL)) {
    require_sesskey();
    unset_config('setup_progress', 'auth_moowoodle');
    redirect(new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => setup_wizard::get_first_step()]));
}

$content = '';

switch ($step) {

    case 'requirements':
        $checks = settings_handler::get_requirement_checks();

        $content .= $OUTPUT->heading(get_string('step_requirements', 'auth_moowoodle'), 3);
        $content .= html_writer::tag('p', get_string('requirements_intro', 'auth_moowoodle'));

        $rows = '';
        foreach ($checks as $check) {
            $icon = $check['met']
                ? $OUTPUT->pix_icon('i/valid', get_string('requirement_ok', 'auth_moowoodle'))
                : $OUTPUT->pix_icon('i/invalid', get_string('requirement_missing', 'auth_moowoodle'));

            $fixlink = !$check['met']
                ? html_writer::link($check['settingsurl'], get_string('fixthis', 'auth_moowoodle'), ['class' => 'ml-2'])
                : '';

            $rows .= html_writer::tag(
                'li',
                $icon . ' ' . s($check['name']) . $fixlink,
                ['class' => 'mb-2']
            );
        }
        $content .= html_writer::tag('ul', $rows, ['class' => 'list-unstyled']);

        $continueurl = new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => $step, 'continuestep' => 1]);
        $content .= $OUTPUT->single_button($continueurl, get_string('continue'), 'post');
        break;

    case 'connection':
        $form = new connection_form($pageurl);

        if ($data = $form->get_data()) {
            $wpsiteurl = rtrim(trim($data->wpsiteurl), '/');

            set_config('wpsiteurl', $wpsiteurl, 'auth_moowoodle');
            set_config('encryptkey', trim($data->encryptkey), 'auth_moowoodle');
            set_config('timelimit', (int) $data->timelimit, 'auth_moowoodle');

            if (!empty($data->testconnection)) {
                $result = settings_handler::test_connection($wpsiteurl);
                $content .= $OUTPUT->notification($result['message'], $result['success'] ? 'success' : 'warning');
            } else if (!empty($data->saveandcontinue)) {
                setup_wizard::mark_step_complete($step);
                redirect(new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => setup_wizard::get_next_step($step)]));
            }
        } else {
            $form->set_data((object) [
                'wpsiteurl' => get_config('auth_moowoodle', 'wpsiteurl'),
                'encryptkey' => get_config('auth_moowoodle', 'encryptkey') ?: settings_handler::generate_secret_key(),
                'timelimit' => get_config('auth_moowoodle', 'timelimit') ?: 60,
            ]);
        }

        $content .= $OUTPUT->heading(get_string('step_connection', 'auth_moowoodle'), 3);
        $content .= html_writer::tag('p', get_string('connection_intro', 'auth_moowoodle'));
        $content .= $form->render();
        break;

    case 'webservice':
        require_capability('moodle/webservice:createtoken', $context);

        $existingservice = settings_handler::get_existing_service();
        $form = new webservice_form($pageurl, ['existingservice' => (bool) $existingservice]);

        $content .= $OUTPUT->heading(get_string('step_webservice', 'auth_moowoodle'), 3);
        $content .= html_writer::tag('p', get_string('webservice_intro', 'auth_moowoodle'));

        $token = null;

        if ($existingservice) {
            $existingtoken = settings_handler::get_existing_token($existingservice->id, $USER->id);
            $token = $existingtoken ? $existingtoken->token : null;
        }

        if ($data = $form->get_data()) {
            $result = settings_handler::create_external_service($USER->id);
            $content .= $OUTPUT->notification($result['message'], $result['success'] ? 'success' : 'error');

            if ($result['success']) {
                $token = $result['token'];
                setup_wizard::mark_step_complete($step);
            }
        }

        if ($token) {
            $content .= html_writer::tag('p', html_writer::tag('strong', get_string('webservice_token_label', 'auth_moowoodle')));
            $content .= html_writer::tag('pre', s($token));
            $continueurl = new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => setup_wizard::get_next_step($step)]);
            $content .= $OUTPUT->single_button($continueurl, get_string('continue'), 'get');
        }

        $content .= $form->render();
        break;

    case 'finish':
        $service = settings_handler::get_existing_service();
        $token = $service ? settings_handler::get_existing_token($service->id, $USER->id) : false;

        $content .= $OUTPUT->heading(get_string('step_finish', 'auth_moowoodle'), 3);
        $content .= $OUTPUT->notification(get_string('finish_intro', 'auth_moowoodle'), 'success');

        $summary = [
            get_string('finish_wpurl', 'auth_moowoodle') => s(get_config('auth_moowoodle', 'wpsiteurl')),
            get_string('finish_mdlurl', 'auth_moowoodle') => s($CFG->wwwroot),
            get_string('finish_token', 'auth_moowoodle') => $token ? s($token->token) : get_string('webservice_status_none', 'auth_moowoodle'),
        ];

        $rows = '';
        foreach ($summary as $label => $value) {
            $rows .= html_writer::tag('dt', $label) . html_writer::tag('dd', $value);
        }
        $content .= html_writer::tag('dl', $rows);
        $content .= html_writer::tag('p', get_string('finish_copy_note', 'auth_moowoodle'));

        $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'manageauths']);
        $content .= $OUTPUT->single_button($settingsurl, get_string('gotosettings', 'auth_moowoodle'), 'get');

        setup_wizard::mark_step_complete($step);

        $restarturl = new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => $step, 'restartwizard' => 1]);
        $content .= $OUTPUT->single_button($restarturl, get_string('redosetup', 'auth_moowoodle'), 'post');
        break;
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox auth-moowoodle-setup-wizard');
echo setup_wizard::render_progress($step);
echo $content;
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
