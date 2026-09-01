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
 * Sync WordPress with Moodle when a user changes in Moodle.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_moowoodle\event;

/**
 * Sync WordPress with Moodle when a user changes in Moodle.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moowoodle_realtime_user_sync {
    /**
     * Push the affected user's data to the WordPress site whenever a relevant
     * Moodle user event fires.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function moowoodle_user_sync_observer(\core\event\base $event): void {
        $userdata = get_complete_user_data('id', $event->get_data()['relateduserid']);

        $userdataarray = [
            'email' => $userdata->email,
            'username' => $userdata->username,
            'password' => $userdata->password,
        ];

        // Only send names that are actually set.
        if ($userdata->firstname != null) {
            $userdataarray['firstname'] = $userdata->firstname;
        }

        if ($userdata->lastname != null) {
            $userdataarray['lastname'] = $userdata->lastname;
        }

        $userdataarray['passkey'] = get_config('auth_moowoodle', 'encryptkey');

        $requesturl = get_config('auth_moowoodle', 'wpsiteurl') . '/?rest_route=/moowoodle/v1/user-sync';

        $client = new \core\http_client(['timeout' => 100, 'http_errors' => false]);

        try {
            $client->post($requesturl, ['form_params' => $userdataarray]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            // Best-effort sync; a network failure here must not disrupt the
            // Moodle event that triggered it.
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
