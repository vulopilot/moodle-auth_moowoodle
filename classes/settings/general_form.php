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
 * General server requirements form, used by the setup wizard.
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
 * General server requirements form, used by the setup wizard.
 */
class general_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'advcheckbox',
            'enablewebservices',
            get_string('req_webservices', 'auth_moowoodle'),
            get_string('recommendedyes', 'auth_moowoodle')
        );
        $mform->addElement('static', 'enablewebservices_desc', '', get_string('req_webservices_desc', 'auth_moowoodle'));

        $mform->addElement(
            'advcheckbox',
            'restprotocol',
            get_string('req_restprotocol', 'auth_moowoodle'),
            get_string('recommendedyes', 'auth_moowoodle')
        );
        $mform->addElement('static', 'restprotocol_desc', '', get_string('req_restprotocol_desc', 'auth_moowoodle'));

        $mform->addElement(
            'advcheckbox',
            'passwordpolicy',
            get_string('req_passwordpolicy', 'auth_moowoodle'),
            get_string('recommendedno', 'auth_moowoodle')
        );
        $mform->addElement('static', 'passwordpolicy_desc', '', get_string('req_passwordpolicy_desc', 'auth_moowoodle'));

        $mform->addElement(
            'advcheckbox',
            'extendedusernamechars',
            get_string('req_extendedchars', 'auth_moowoodle'),
            get_string('recommendedyes', 'auth_moowoodle')
        );
        $mform->addElement('static', 'extendedusernamechars_desc', '', get_string('req_extendedchars_desc', 'auth_moowoodle'));

        $buttonarray = [
            $mform->createElement('submit', 'savesettings', get_string('save', 'auth_moowoodle')),
            $mform->createElement('submit', 'saveandcontinue', get_string('saveandcontinue', 'auth_moowoodle')),
        ];
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }
}
