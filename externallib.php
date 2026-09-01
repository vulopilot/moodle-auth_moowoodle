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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External library
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_moowoodle_external extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function auth_moowoodle_get_users_parameters(): external_function_parameters {
        return new external_function_parameters([
            'endid' => new external_value(PARAM_RAW, 'The last id to send the next batch of user data'),
            'limit' => new external_value(PARAM_RAW, 'The limit for the batch of user data'),
            'roles' => new external_value(PARAM_RAW, 'The role ids, a comma separated string of role ids'),
        ]);
    }

    /**
     * Get all users, batched by id, restricted to the given roles.
     *
     * @param int $endid
     * @param int $limit
     * @param string $roles Comma separated role ids.
     * @return array
     */
    public static function auth_moowoodle_get_users($endid, $limit, $roles) {
        global $DB;

        if (!is_numeric($limit) || !is_numeric($endid)) {
            return [
                'status' => 'failed',
                'data' => json_encode('Bad Request'),
            ];
        }

        $limit = (int) $limit + 1;

        // Sanitize role ids and prepare SQL placeholders.
        $roleids = explode(',', $roles);
        $roleids = array_map('intval', $roleids);

        [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'roleid');

        $sql = "SELECT u.id, u.email, u.username, u.password, u.firstname, u.lastname
                  FROM {user} u
                  JOIN {role_assignments} ra ON u.id = ra.userid
                 WHERE u.id > :endid
                   AND u.deleted = 0
                   AND ra.roleid $rolesql
              ORDER BY u.id ASC";

        $params = array_merge(['endid' => (int) $endid], $roleparams);

        $records = $limit <= 0
            ? $DB->get_records_sql($sql, $params)
            : $DB->get_records_sql($sql, $params, 0, $limit);

        return [
            'status' => 'success',
            'data' => json_encode($records),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function auth_moowoodle_get_users_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_RAW, 'status: success if success'),
            'data' => new external_value(PARAM_RAW, 'users: all user data'),
        ]);
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function auth_moowoodle_user_sync_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userdata' => new external_value(PARAM_RAW, 'WordPress user data'),
            'setting' => new external_value(PARAM_RAW, 'Sync setting information from WordPress'),
        ]);
    }

    /**
     * Update the user in Moodle if something changed in WordPress, or create a new
     * user if one doesn't exist yet.
     *
     * @param string $userdata JSON encoded WordPress user data.
     * @param string $setting JSON encoded list of fields WordPress is allowed to sync.
     * @return array
     */
    public static function auth_moowoodle_user_sync($userdata, $setting) {
        // Validate input parameters.
        $params = self::validate_parameters(
            self::auth_moowoodle_user_sync_parameters(),
            [
                'userdata' => $userdata,
                'setting' => $setting,
            ]
        );

        // Validate context.
        $context = \core\context\system::instance();
        self::validate_context($context);

        // Require capability.
        require_capability('auth/moowoodle:syncusers', $context);

        // Decode JSON.
        $wpuserdata = json_decode($params['userdata'], true);
        $syncsettings = json_decode($params['setting'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \invalid_parameter_exception('Invalid JSON payload.');
        }

        if (!is_array($wpuserdata) || !is_array($syncsettings)) {
            return [
                'status' => 'failed',
                'data' => json_encode('Bad Request'),
            ];
        }

        $response = self::sync_moodle_user($wpuserdata, $syncsettings);

        return [
            'status' => 'success',
            'data' => json_encode($response),
        ];
    }

    /**
     * Create or update the Moodle account matching the given WordPress user data.
     *
     * @param array $wpuserdata WordPress user data, decoded from JSON.
     * @param array $syncsettings Fields WordPress is allowed to overwrite on an existing user.
     * @return array ['created' => bool, 'id' => int]
     */
    private static function sync_moodle_user(array $wpuserdata, array $syncsettings): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/user/lib.php');

        $moodleuserdata = $DB->get_record('user', ['email' => $wpuserdata['email'], 'deleted' => 0]);
        $response = ['created' => false];

        if (!$moodleuserdata) {
            $moodleuserdata = new \stdClass();
        }

        $isnewuser = !$moodleuserdata->id;

        $moodleuserdata->email = clean_param($wpuserdata['email'], PARAM_EMAIL);

        self::apply_username($moodleuserdata, $wpuserdata, $syncsettings, $isnewuser);
        self::apply_password($moodleuserdata, $wpuserdata, $syncsettings, $isnewuser);
        self::apply_namefield($moodleuserdata, $wpuserdata, $syncsettings, $isnewuser, 'firstname');
        self::apply_namefield($moodleuserdata, $wpuserdata, $syncsettings, $isnewuser, 'lastname');

        $moodleuserdata->confirmed = true;
        $moodleuserdata->mnethostid = $CFG->mnet_localhost_id;

        if ($moodleuserdata->id) {
            user_update_user($moodleuserdata, false, false);
            $userid = $moodleuserdata->id;
        } else {
            $userid = user_create_user($moodleuserdata, false, false);
            $response['created'] = true;
        }

        $response['id'] = $userid;

        return $response;
    }

    /**
     * Set the username from WordPress data, if allowed or the account is new.
     *
     * @param \stdClass $moodleuserdata
     * @param array $wpuserdata
     * @param array $syncsettings
     * @param bool $isnewuser
     */
    private static function apply_username(
        \stdClass $moodleuserdata,
        array $wpuserdata,
        array $syncsettings,
        bool $isnewuser
    ): void {
        if (in_array('username', $syncsettings) || $isnewuser) {
            $moodleuserdata->username = clean_param($wpuserdata['username'], PARAM_USERNAME);
        }
    }

    /**
     * Set the password hash from WordPress data, if allowed or the account is new.
     *
     * Only accepted when it looks like a WordPress-style bcrypt/SHA-2 hash
     * ('$6$rounds=' prefix); anything else is silently left untouched.
     *
     * @param \stdClass $moodleuserdata
     * @param array $wpuserdata
     * @param array $syncsettings
     * @param bool $isnewuser
     */
    private static function apply_password(
        \stdClass $moodleuserdata,
        array $wpuserdata,
        array $syncsettings,
        bool $isnewuser
    ): void {
        if ((in_array('password', $syncsettings) && $wpuserdata['password'] != null) || $isnewuser) {
            if (strpos($wpuserdata['password'], '$6$rounds=') === 0) {
                $moodleuserdata->password = $wpuserdata['password'];
            }
        }
    }

    /**
     * Set a name field (firstname/lastname) from WordPress data, if allowed or the account is new.
     *
     * @param \stdClass $moodleuserdata
     * @param array $wpuserdata
     * @param array $syncsettings
     * @param bool $isnewuser
     * @param string $field Either 'firstname' or 'lastname'.
     */
    private static function apply_namefield(
        \stdClass $moodleuserdata,
        array $wpuserdata,
        array $syncsettings,
        bool $isnewuser,
        string $field
    ): void {
        if ((in_array($field, $syncsettings) && $wpuserdata[$field] != null) || $isnewuser) {
            $moodleuserdata->$field = clean_param($wpuserdata[$field] ?? '', PARAM_NOTAGS);
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function auth_moowoodle_user_sync_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_RAW, 'status: success if success'),
            'data' => new external_value(PARAM_RAW, 'Moodle user id'),
        ]);
    }
}
