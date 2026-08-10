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
     * check if there is the record finding by ysername and mnet_localhost_id
     *
     * @param string $username
     * @param null $password
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
     * if the user can reset password
     */
    public function can_reset_password() {
        return false;
    }

    /**
     * return if the user can change password
     */
    public function can_change_password() {
        return false;
    }

    /**
     * return if user can change password url
     */
    public function change_password_url() {
        return;
    }

    /**
     * return if its internal
     */
    public function is_internal() {
        return false;
    }

    /**
     * function to prevent local password
     */
    public function prevent_local_passwords() {
        return false;
    }
}
