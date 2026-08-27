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
use auth_moowoodle\settings\general_form;
use auth_moowoodle\settings\webservice_form;
use core\context\system as context_system;

global $CFG, $OUTPUT, $PAGE, $USER;

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

// Small "Copy" button behaviour for the read-only site URL / token fields.
$copyjs = "document.addEventListener('click', function(event) {\n" .
    "    var button = event.target.closest('.auth-moowoodle-copy');\n" .
    "    if (!button) {\n" .
    "        return;\n" .
    "    }\n" .
    "    var target = document.getElementById(button.getAttribute('data-copy-target'));\n" .
    "    if (!target) {\n" .
    "        return;\n" .
    "    }\n" .
    "    var text = 'value' in target ? target.value : target.textContent;\n" .
    "    if (navigator.clipboard && navigator.clipboard.writeText) {\n" .
    "        navigator.clipboard.writeText(text);\n" .
    "    } else {\n" .
    "        target.select();\n" .
    "        document.execCommand('copy');\n" .
    "    }\n" .
    "});";
$PAGE->requires->js_init_code($copyjs, true);

// Refresh the Web Service step's Token list when the service or user dropdown changes,
// via a small JSON fetch, instead of reloading the page. No page navigation means no
// "leave this page?" prompt from Moodle's unsaved-changes warning, and the "Name for
// the Web Service" field's own show/hide already happens client-side via hideIf().
$ajaxurl = json_encode((new moodle_url('/auth/moowoodle/wizard_ajax.php'))->out(false));
$tokenplaceholder = json_encode(get_string('webservice_selecttoken', 'auth_moowoodle'));
$reloadjs = <<<JS
(function() {
    var serviceselect = document.getElementById('auth_moowoodle_serviceid');
    var userselect = document.getElementById('id_userid');
    var tokenselect = document.getElementById('auth_moowoodle_token');
    var button = document.getElementById('id_updateservice');

    if (!serviceselect || !tokenselect) {
        return;
    }

    var refreshTokens = function() {
        if (serviceselect.value === '') {
            return;
        }

        var params = new URLSearchParams({
            sesskey: M.cfg.sesskey,
            serviceid: serviceselect.value,
            userid: userselect ? userselect.value : 0
        });

        fetch({$ajaxurl} + '?' + params.toString(), {credentials: 'same-origin'})
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                tokenselect.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = {$tokenplaceholder};
                tokenselect.appendChild(placeholder);

                Object.keys(data.tokens).forEach(function(token) {
                    var option = document.createElement('option');
                    option.value = token;
                    option.textContent = data.tokens[token];
                    option.selected = (token === data.selectedtoken);
                    tokenselect.appendChild(option);
                });

                if (button) {
                    button.value = data.buttonlabel;
                }
            })
            .catch(function() {
                // Leave the current token list as-is on a network error.
            });
    };

    serviceselect.addEventListener('change', refreshTokens);
    if (userselect) {
        userselect.addEventListener('change', refreshTokens);
    }
})();
JS;
$PAGE->requires->js_init_code($reloadjs, true);

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
        $form = new general_form($pageurl);

        if ($data = $form->get_data()) {
            settings_handler::save_general_settings($data);
            $content .= $OUTPUT->notification(get_string('settingssaved', 'auth_moowoodle'), 'success');

            if (!empty($data->saveandcontinue)) {
                setup_wizard::mark_step_complete($step);
                redirect(new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => setup_wizard::get_next_step($step)]));
            }
        } else {
            $protocols = !empty($CFG->webserviceprotocols) ? explode(',', $CFG->webserviceprotocols) : [];

            $form->set_data((object) [
                'enablewebservices' => (bool) $CFG->enablewebservices,
                'restprotocol' => in_array('rest', $protocols, true),
                'passwordpolicy' => (bool) $CFG->passwordpolicy,
                'extendedusernamechars' => (bool) $CFG->extendedusernamechars,
            ]);
        }

        $content .= $OUTPUT->heading(get_string('step_requirements', 'auth_moowoodle'), 3);
        $content .= html_writer::tag('p', get_string('requirements_intro', 'auth_moowoodle'));
        $content .= $form->render();
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

        $services = settings_handler::get_existing_services();
        $users = settings_handler::get_selectable_users();

        if (empty($users)) {
            $users = [$USER->id => fullname($USER)];
        }

        // The service currently selected in the dropdown (possibly not yet saved), so the
        // Token list can be refreshed for it when the dropdown change reloads the page.
        $rawserviceid = optional_param('serviceid', '', PARAM_RAW);
        $viewserviceid = $rawserviceid !== '' ? (int) $rawserviceid : (int) get_config('auth_moowoodle', 'webservice_id');
        $tokens = settings_handler::get_tokens_for_service($viewserviceid);

        $form = new webservice_form($pageurl, [
            'services' => $services,
            'users' => $users,
            'tokens' => $tokens,
            'existingservice' => (bool) $viewserviceid,
        ]);

        // Only treat this as a real create/update when the actual submit button was
        // clicked. A plain dropdown-change reload (see the JS above) posts the form
        // without any submit button's name/value, so it never reaches this branch.
        $realsubmit = optional_param('updateservice', '', PARAM_RAW) !== '';
        $justcreated = false;

        if ($realsubmit && ($data = $form->get_data())) {
            // Grant every known function automatically; the admin isn't asked to pick.
            settings_handler::save_sync_functions(settings_handler::get_selectable_sync_functions());

            $result = settings_handler::create_or_update_service(
                (int) $data->serviceid,
                (int) $data->userid,
                $data->newservicename ?? ''
            );

            $content .= $OUTPUT->notification($result['message'], $result['success'] ? 'success' : 'error');

            if ($result['success']) {
                setup_wizard::mark_step_complete($step);

                $justcreated = true;
                $createduserid = (int) $data->userid;

                // Refresh the service/token lists and rebuild the form in place, instead of
                // redirecting, so the newly created service and token show up immediately.
                $viewserviceid = (int) $result['serviceid'];
                $services = settings_handler::get_existing_services();
                $tokens = settings_handler::get_tokens_for_service($viewserviceid);

                // Discard the just-processed submission before rebuilding the form: once a
                // moodleform detects it was submitted, it renders those posted values (e.g.
                // serviceid "0" for "create new") instead of the set_data() defaults below,
                // which would otherwise leave the dropdown stuck on "Create new web service"
                // instead of switching to the service that was just created.
                $_POST = [];

                $form = new webservice_form($pageurl, [
                    'services' => $services,
                    'users' => $users,
                    'tokens' => $tokens,
                    'existingservice' => true,
                ]);
            }
        }

        // Preserve the admin's in-progress "Select user" choice across a reload triggered by
        // changing the service dropdown, instead of resetting it back to the default each time.
        if ($justcreated) {
            $selecteduserid = $createduserid;
        } else {
            $rawuserid = optional_param('userid', 0, PARAM_INT);
            if ($rawuserid && array_key_exists($rawuserid, $users)) {
                $selecteduserid = $rawuserid;
            } else {
                $selecteduserid = array_key_exists((int) $USER->id, $users) ? (int) $USER->id : (int) array_key_first($users);
            }
        }

        $selectedtoken = '';

        if ($viewserviceid) {
            $existingtoken = settings_handler::get_existing_token($viewserviceid, $selecteduserid);

            if ($existingtoken && array_key_exists($existingtoken->token, $tokens)) {
                $selectedtoken = $existingtoken->token;
            }
        }

        $form->set_data((object) [
            'serviceid' => $viewserviceid,
            'newservicename' => optional_param('newservicename', '', PARAM_TEXT),
            'userid' => $selecteduserid,
            'langcode' => $CFG->lang,
            'siteurl' => $CFG->wwwroot,
            'token' => $selectedtoken,
        ]);

        $content .= $OUTPUT->heading(get_string('step_webservice', 'auth_moowoodle'), 3);
        $content .= html_writer::tag('p', get_string('webservice_intro', 'auth_moowoodle'));
        $content .= $form->render();

        $nexturl = new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => setup_wizard::get_next_step($step)]);
        $content .= $OUTPUT->single_button($nexturl, get_string('next'), 'get');
        break;

    case 'summary':
        $summary = settings_handler::get_summary();

        $connectiontest = !empty($summary['wordpressurl'])
            ? settings_handler::test_connection($summary['wordpressurl'])
            : ['success' => false, 'message' => get_string('testconnection_invalidurl', 'auth_moowoodle')];

        $content .= $OUTPUT->heading(get_string('step_summary', 'auth_moowoodle'), 3);
        $content .= html_writer::tag('p', get_string('summary_intro', 'auth_moowoodle'));

        $statuscell = static function (bool $ok) use ($OUTPUT): string {
            return $ok
                ? $OUTPUT->pix_icon('i/valid', get_string('enabled', 'auth_moowoodle')) . ' ' . get_string('enabled', 'auth_moowoodle')
                : $OUTPUT->pix_icon('i/invalid', get_string('disabled', 'auth_moowoodle')) . ' ' . get_string('disabled', 'auth_moowoodle');
        };

        $content .= $OUTPUT->heading(get_string('summary_general_heading', 'auth_moowoodle'), 4);

        $generaltable = new html_table();
        $generaltable->attributes['class'] = 'table table-sm auth-moowoodle-summary-table';
        $generaltable->data = [
            [get_string('req_restprotocol', 'auth_moowoodle'), $statuscell($summary['restprotocol'])],
            [get_string('req_webservices', 'auth_moowoodle'), $statuscell($summary['webservices'])],
            [get_string('req_passwordpolicy', 'auth_moowoodle'), $statuscell($summary['passwordpolicy'])],
            [get_string('req_extendedchars', 'auth_moowoodle'), $statuscell($summary['extendedusernamechars'])],
            [get_string('summary_webservicefunctions', 'auth_moowoodle'), $statuscell($summary['webservicefunctions'])],
            [get_string('summary_capability', 'auth_moowoodle'), $statuscell($summary['capability'])],
        ];
        $content .= html_writer::table($generaltable);

        $content .= $OUTPUT->heading(get_string('summary_connection_heading', 'auth_moowoodle'), 4);

        $connectionstepurl = new moodle_url('/auth/moowoodle/setup_wizard.php', ['step' => 'connection']);

        $connectionstatus = $connectiontest['success']
            ? $OUTPUT->pix_icon('i/valid', '') . ' ' . get_string('connectionok', 'auth_moowoodle')
            : $OUTPUT->pix_icon('i/invalid', '') . ' ' . s($connectiontest['message']) . ' '
                . html_writer::link($connectionstepurl, get_string('checkmoredetails', 'auth_moowoodle'));

        $connectiontable = new html_table();
        $connectiontable->attributes['class'] = 'table table-sm auth-moowoodle-summary-table';
        $connectiontable->data = [
            [get_string('summary_moodleurl', 'auth_moowoodle'), s($summary['moodleurl'])],
            [get_string('summary_webservicename', 'auth_moowoodle'), $summary['webservicename'] !== '' ? s($summary['webservicename']) : get_string('summary_notset', 'auth_moowoodle')],
            [get_string('webservice_token_label', 'auth_moowoodle'), $summary['token'] !== '' ? s($summary['token']) : get_string('summary_notset', 'auth_moowoodle')],
            [get_string('summary_wordpressurl', 'auth_moowoodle'), $summary['wordpressurl'] !== '' ? s($summary['wordpressurl']) : get_string('summary_notset', 'auth_moowoodle')],
            [get_string('summary_connectionstatus', 'auth_moowoodle'), $connectionstatus],
            [get_string('summary_langcode', 'auth_moowoodle'), s($summary['langcode'])],
        ];
        $content .= html_writer::table($connectiontable);
        $content .= html_writer::tag('p', get_string('summary_copy_note', 'auth_moowoodle'));

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
