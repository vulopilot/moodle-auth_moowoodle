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
 * Synchronization (web service function) settings form, used by the setup wizard.
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
 * Synchronization (web service function) settings form, used by the setup wizard.
 */
class synchronization_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $functions = $this->_customdata['functions'] ?? [];
        $mandatory = $this->_customdata['mandatory'] ?? [];

        foreach ($functions as $function) {
            $fieldname = 'func_' . $function;

            $mform->addElement('advcheckbox', $fieldname, $function, '');

            if (in_array($function, $mandatory, true)) {
                $mform->setDefault($fieldname, 1);
                $mform->freeze($fieldname);
            }
        }

        $buttonarray = [
            $mform->createElement('submit', 'savesettings', get_string('save', 'auth_moowoodle')),
            $mform->createElement('submit', 'saveandcontinue', get_string('saveandcontinue', 'auth_moowoodle')),
        ];
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }
}
