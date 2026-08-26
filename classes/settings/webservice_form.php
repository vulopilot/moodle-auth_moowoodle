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
 * Web service / token creation form, used by the setup wizard and settings page.
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
 * Web service / token creation form, used by the setup wizard and settings page.
 */
class webservice_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $existing = !empty($this->_customdata['existingservice']);

        $label = $existing
            ? get_string('webservice_recreate', 'auth_moowoodle')
            : get_string('webservice_create', 'auth_moowoodle');

        $mform->addElement('submit', 'createservice', $label);
    }
}
