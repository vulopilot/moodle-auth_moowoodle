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
 * WordPress connection settings form, used by the setup wizard.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_moowoodle\settings;

use moodleform;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * WordPress connection settings form, used by the setup wizard.
 */
class connection_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'wpsiteurl', get_string('wpsiteurl', 'auth_moowoodle'), ['size' => 50]);
        $mform->setType('wpsiteurl', PARAM_URL);
        $mform->addRule('wpsiteurl', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('wpsiteurl', 'wpsiteurl', 'auth_moowoodle');

        $mform->addElement('text', 'encryptkey', get_string('key', 'auth_moowoodle'), ['size' => 50]);
        $mform->setType('encryptkey', PARAM_RAW_TRIMMED);
        $mform->addRule('encryptkey', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('encryptkey', 'key', 'auth_moowoodle');

        $mform->addElement('text', 'timelimit', get_string('timelimit', 'auth_moowoodle'), ['size' => 10]);
        $mform->setType('timelimit', PARAM_INT);
        $mform->addRule('timelimit', get_string('required'), 'required', null, 'client');
        $mform->setDefault('timelimit', 60);
        $mform->addHelpButton('timelimit', 'timelimit', 'auth_moowoodle');

        $buttonarray = [
            $mform->createElement('submit', 'testconnection', get_string('testconnection', 'auth_moowoodle')),
            $mform->createElement('submit', 'saveandcontinue', get_string('saveandcontinue', 'auth_moowoodle')),
        ];
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }

    /**
     * Server side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['timelimit']) && (int) $data['timelimit'] <= 0) {
            $errors['timelimit'] = get_string('required');
        }

        return $errors;
    }
}
