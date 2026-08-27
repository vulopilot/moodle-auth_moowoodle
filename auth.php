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
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');

/**
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_plugin_moowoodle extends auth_plugin_base {

    /**
     * constructor
     */
    public function __construct() {
        $this->authtype = 'moowoodle';
        $this->config = get_config('auth_moowoodle');
    }

    /**
     * Authenticate a Moodle-local user by username and password.
     *
     * @param string $username
     * @param string|null $password
     * @return bool
     */
    public function user_login($username, $password = null) {
        global $CFG, $DB;

        if (empty($password)) {
            return false;
        }

        $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);

        if (empty($user) || !empty($user->suspended)) {
            return false;
        }

        return validate_internal_user_password($user, $password);
    }

    /**
     * Whether users of this authentication method can reset their password.
     *
     * @return bool
     */
    public function can_reset_password() {
        return false;
    }

    /**
     * Whether users of this authentication method can change their password.
     *
     * @return bool
     */
    public function can_change_password() {
        return false;
    }

    /**
     * URL for changing the user's password, if any.
     *
     * @return void
     */
    public function change_password_url() {
        return;
    }

    /**
     * Whether this is an internal authentication method.
     *
     * @return bool
     */
    public function is_internal() {
        return false;
    }

    /**
     * Whether Moodle should prevent local password changes for this method.
     *
     * @return bool
     */
    public function prevent_local_passwords() {
        return false;
    }
}
