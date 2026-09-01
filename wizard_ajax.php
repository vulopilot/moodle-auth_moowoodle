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
 * Lightweight JSON endpoint used by the Web Service step of the setup wizard, so that
 * selecting a service (or user) in the dropdowns can refresh the token list in place
 * without reloading the page.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use auth_moowoodle\local\settings_handler;
use core\context\system as context_system;

global $CFG;

require_login();
require_sesskey();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('moodle/webservice:createtoken', $context);

$serviceid = required_param('serviceid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$tokens = settings_handler::get_tokens_for_service($serviceid);

$selectedtoken = '';
if ($serviceid > 0 && $userid > 0) {
    $existingtoken = settings_handler::get_existing_token($serviceid, $userid);

    if ($existingtoken && array_key_exists($existingtoken->token, $tokens)) {
        $selectedtoken = $existingtoken->token;
    }
}

$buttonlabel = $serviceid > 0
    ? get_string('webservice_recreate', 'auth_moowoodle')
    : get_string('webservice_create', 'auth_moowoodle');

header('Content-Type: application/json');
echo json_encode([
    'tokens' => $tokens,
    'selectedtoken' => $selectedtoken,
    'existingservice' => $serviceid > 0,
    'buttonlabel' => $buttonlabel,
]);
