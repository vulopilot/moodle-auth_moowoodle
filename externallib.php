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
 * External library
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// support for previous version of moodle 4.2
require_once("{$CFG->libdir}/externallib.php");

/**
 * External library
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_moowoodle_external extends \external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function auth_moowoodle_get_users_parameters(): \external_function_parameters {
        return new \external_function_parameters(
            [
                'endid'  => new \external_value( PARAM_RAW, 'The Last id to send next batch of user data' ),
                'limit'  => new \external_value( PARAM_RAW, 'The limit to sent batch of user data' ),
                'roles'  => new \external_value( PARAM_RAW, 'The roll ids, comma seperated string of roll ids' ),
            ]
        );
    }
    /**
     * get all users
     * @param int $endid
     * @param int $limit
     */
    public static function auth_moowoodle_get_users($endid, $limit, $roles) {
        global $DB;

        if (is_numeric($limit) && is_numeric($endid)) {

            $limit = (int) $limit + 1;

            // Sanitize role IDs and prepare SQL placeholders.
            $roleids = explode(',', $roles);
            $roleids = array_map('intval', $roleids);

            list($rolesql, $roleparams) = $DB->get_in_or_equal(
                $roleids,
                SQL_PARAMS_NAMED,
                'roleid'
            );

            $sql = "SELECT u.id, u.email, u.username, u.password, u.firstname, u.lastname
                    FROM {user} u
                    JOIN {role_assignments} ra ON u.id = ra.userid
                    WHERE u.id > :endid
                    AND u.deleted = 0
                    AND ra.roleid $rolesql
                ORDER BY u.id ASC";

            $param = array_merge(
                [
                    'endid' => (int) $endid,
                ],
                $roleparams
            );

            if ($limit <= 0) {
                $response = [
                    'status' => 'success',
                    'data' => json_encode($DB->get_records_sql($sql, $param)),
                ];
            } else {
                $response = [
                    'status' => 'success',
                    'data' => json_encode($DB->get_records_sql($sql, $param, 0, $limit)),
                ];
            }
        } else {
            $response = [
                'status' => 'failed',
                'data' => json_encode('Bad Request'),
            ];
        }

        return ($response);
    }
    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function auth_moowoodle_get_users_returns(): \external_single_structure {
        return new \external_single_structure(
            [
                'status' => new \external_value(PARAM_RAW, 'status: success if success'),
                'data'   => new \external_value(PARAM_RAW, 'users: all user data'),
            ]
        );
    }
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function auth_moowoodle_user_sync_parameters(): \external_function_parameters {
        return new \external_function_parameters(
            [
                'userdata'  => new \external_value( PARAM_RAW, 'wordpress user data ' ),
                'setting'   => new \external_value( PARAM_RAW, 'setting information from wordpress' ),
            ]
        );
    }
    /**
     * update user in moodle if something changed in wordpress
     * create a new user if user not present
     *
     * @param object $setting (json object)
     * @param object $userdata (json object)
     * @return  array
     */
    public static function auth_moowoodle_user_sync( $userdata, $setting ) {
        global $DB, $CFG;

        // Validate input parameters.
        $params = self::validate_parameters(
            self::auth_moowoodle_user_sync_parameters(),
            [
                'userdata' => $userdata,
                'setting'  => $setting,
            ]
        );

        $userdata = $params['userdata'];
        $setting  = $params['setting'];

        // Validate context.
        $context = \context_system::instance();
        self::validate_context( $context );

        // Require capability.
        require_capability( 'auth/moowoodle:syncusers', $context );

        // Decode JSON.
        $wpuserdata   = json_decode( $userdata, true );
        $syncsettings = json_decode( $setting, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new invalid_parameter_exception( 'Invalid JSON payload.' );
        }

        if ( is_array( $wpuserdata ) && is_array( $syncsettings ) ) {
            require_once( $CFG->dirroot . '/user/lib.php' );

            $moodleuserdata = $DB->get_record( 'user', [ 'email' => $wpuserdata['email'], 'deleted' => 0 ] );
            $response['created'] = false;

            if ( ! $moodleuserdata->id ) {
                $moodleuserdata = new stdClass();
            }

            $moodleuserdata->email = clean_param($wpuserdata['email'], PARAM_EMAIL);

            if ( in_array( 'username', $syncsettings ) || ! $moodleuserdata->id ) {
                $moodleuserdata->username = clean_param($wpuserdata['username'], PARAM_USERNAME);
            }

            if ( ( in_array( 'password', $syncsettings ) && $wpuserdata['password'] != null ) || ! $moodleuserdata->id ) {

                if ( strpos( $wpuserdata['password'], '$6$rounds=' ) === 0 ) {
                    $moodleuserdata->password = $wpuserdata['password'];
                }
            }

            if ( ( in_array( 'firstname', $syncsettings ) && $wpuserdata['firstname'] != null ) || ! $moodleuserdata->id ) {
                $moodleuserdata->firstname = clean_param($wpuserdata['firstname'] ?? '', PARAM_NOTAGS);
            }

            if ( ( in_array( 'lastname', $syncsettings ) && $wpuserdata['lastname'] != null ) || ! $moodleuserdata->id ) {
                $moodleuserdata->lastname = clean_param($wpuserdata['lastname'] ?? '', PARAM_NOTAGS);
            }

            $moodleuserdata->confirmed  = true;
            $moodleuserdata->mnethostid = $CFG->mnet_localhost_id;

            if ( $moodleuserdata->id ) {
                user_update_user( $moodleuserdata, false, false );
                $userid = $moodleuserdata->id;
            } else {
                $userid = user_create_user( $moodleuserdata, false, false );
                $response['created'] = true;
            }

            $response['id'] = $userid;

            $response = [
                'status' => 'success',
                'data'   => json_encode( $response ),
            ];
        } else {
            $response = [
                'status' => 'failed',
                'data'   => json_encode( 'Bad Request' ),
            ];
        }

        return $response;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function auth_moowoodle_user_sync_returns(): \external_single_structure {
        return new \external_single_structure(
            [
                'status' => new \external_value( PARAM_RAW, 'status: success if success' ),
                'data'   => new \external_value( PARAM_RAW, 'moode user id' ),
            ]
        );
    }
}