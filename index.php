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
 * user login in moodle
 * 
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once ( '../../config.php' );
require_once ( $CFG->libdir . '/filelib.php' );
require_once(__DIR__ . '/lib.php');

$SESSION->wantsurl = $CFG->wwwroot . '/';

$passkey = optional_param( 'passkey', '', PARAM_RAW );

if ( $passkey ) {
    $sso_key = get_config('auth_moowoodle', 'encryptkey');

    $requestdata = moowoodle_decrypt_data($passkey, $sso_key);

    if (false === $requestdata) {
        throw new moodle_exception('Invalid SSO token.');
    }

    // Get timestamp.
    $timestamp = $requestdata['timestamp'];

    // Calculate time difference.
    $timedif = time() - $timestamp;

    $userexist = $DB->record_exists('user', ['id' => $requestdata['user_id']]);

    if ( $timedif >= 0 && $timedif < get_config('auth_moowoodle', 'timelimit') * 60 && $userexist ) {

        $user = get_complete_user_data( 'id', $requestdata['user_id'] );

        // Get wordpress request url.
        $requesturl = get_config('auth_moowoodle', 'wpsiteurl') . '/?rest_route=/moowoodle/v1/sso';

        $curl = new curl();

        $request_token = bin2hex(random_bytes(32));

        // Prepare request data.
        $reqdata = [
            'action'        => 'login_verify',
            'redirect_to'   => $requestdata['redirect_url'],
            'mdl_user_id'   => $user->id,
            'mdl_username'  => $user->username,
            'mdl_email'     => $user->email,
            'timestamp'     => $requestdata['timestamp'],
            'course_id'     => $requestdata['course_id'],
            'user_id'       => $requestdata['wp_user_id'],
            'nonce'         => $requestdata['nonce'],
            'request_token' => $request_token,
        ];

        $encrypted_request = moowoodle_encrypt_data($reqdata, $sso_key);

        if (false === $encrypted_request) {
            throw new moodle_exception('Unable to encrypt SSO request.');
        }

        // Send request to wordpress server.
        $response = $curl->post(
            $requesturl,
            [
                'payload' => $encrypted_request,
            ],
            [
                'RETURNTRANSFER' => 1,
                'TIMEOUT'        => 100,
            ]
        );

        $response = json_decode($response, true);

        if ( ! $response ) {
            throw new moodle_exception($curl->error);
        }

        if ( $response['status'] == 'unauthorized' ) {
            throw new moodle_exception('Unauthorized access, contact with site admin.');
        }

        if ( empty($response['request_token']) || ! hash_equals( $request_token, $response['request_token'] ) ) {
            throw new moodle_exception( 'Unauthorized access, request-token mismatch.');
        }

        if ( $response['status'] == 'success' ) {
            $user->loggedin = true;
            $user->site = $CFG->wwwroot;
            unset_user_preference('auth_forcepasswordchange', $user);
            complete_user_login($user);
        }

        if ( !empty($requestdata['redirect_url']) && parse_url($requestdata['redirect_url'], PHP_URL_HOST) === parse_url($CFG->wwwroot, PHP_URL_HOST) ) {
            $SESSION->wantsurl = $requestdata['redirect_url'];
        }
    }
}

redirect($SESSION->wantsurl);
