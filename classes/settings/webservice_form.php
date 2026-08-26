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
 * Web service / token form, used by the setup wizard.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_moowoodle\settings;

use html_writer;
use moodleform;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Web service / token form, used by the setup wizard.
 */
class webservice_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $services = $this->_customdata['services'] ?? [];
        $users = $this->_customdata['users'] ?? [];

        $serviceoptions = [0 => get_string('webservice_createnew', 'auth_moowoodle')] + $services;
        $mform->addElement('select', 'serviceid', get_string('webservice_selectservice', 'auth_moowoodle'), $serviceoptions);
        $mform->setType('serviceid', PARAM_INT);

        $mform->addElement('select', 'userid', get_string('webservice_selectuser', 'auth_moowoodle'), $users);
        $mform->setType('userid', PARAM_INT);

        $mform->addGroup(
            [
                $mform->createElement('text', 'langcode', '', ['size' => 20, 'readonly' => 'readonly', 'id' => 'auth_moowoodle_langcode']),
                $mform->createElement('html', $this->copy_button('auth_moowoodle_langcode')),
            ],
            'langcodegroup',
            get_string('webservice_langcode', 'auth_moowoodle'),
            ' ',
            false
        );
        $mform->setType('langcode', PARAM_TEXT);

        $mform->addGroup(
            [
                $mform->createElement('text', 'siteurl', '', ['size' => 40, 'readonly' => 'readonly', 'id' => 'auth_moowoodle_siteurl']),
                $mform->createElement('html', $this->copy_button('auth_moowoodle_siteurl')),
            ],
            'siteurlgroup',
            get_string('webservice_siteurl', 'auth_moowoodle'),
            ' ',
            false
        );
        $mform->setType('siteurl', PARAM_URL);

        $mform->addGroup(
            [
                $mform->createElement('text', 'token', '', ['size' => 40, 'readonly' => 'readonly', 'id' => 'auth_moowoodle_token']),
                $mform->createElement('html', $this->copy_button('auth_moowoodle_token')),
            ],
            'tokengroup',
            get_string('webservice_token_label', 'auth_moowoodle'),
            ' ',
            false
        );
        $mform->setType('token', PARAM_RAW);

        $mform->addElement('submit', 'updateservice', get_string('webservice_update', 'auth_moowoodle'));
    }

    /**
     * A small "Copy" button that copies the value of the given field to the clipboard.
     *
     * @param string $targetid
     * @return string
     */
    protected function copy_button(string $targetid): string {
        return html_writer::tag(
            'button',
            get_string('copy', 'auth_moowoodle'),
            [
                'type' => 'button',
                'class' => 'btn btn-secondary btn-sm auth-moowoodle-copy ml-2',
                'data-copy-target' => $targetid,
            ]
        );
    }
}
