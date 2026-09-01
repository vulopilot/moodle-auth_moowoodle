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
 * Listeners for core hooks, registered in db/hooks.php.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_moowoodle\local;

use core\hook\after_config;
use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Listeners for core hooks, registered in db/hooks.php.
 */
class hook_listener {
    /**
     * Right after a fresh install, send the next admin who loads a page straight to the
     * setup wizard. This runs on every single request, so it must return immediately
     * once the one-time flag set by db/install.php is gone.
     *
     * @param after_config $hook
     */
    public static function after_config(after_config $hook): void {
        global $CFG, $USER;

        // Unlike the legacy <component>_after_config() callback convention (which
        // get_plugins_with_function() itself refuses to invoke this early), a hook
        // registered in db/hooks.php has no such built-in guard - so these checks
        // must run, and must come, before anything below touches the database.
        if (
            CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER || during_initial_install()
                || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)
                || (defined('NO_MOODLE_COOKIES') && NO_MOODLE_COOKIES)
        ) {
            return;
        }

        if (empty(get_config('auth_moowoodle', 'promptsetupwizard'))) {
            return;
        }

        // Don't jump in mid-upgrade; wait until the whole site (all plugins) is up to date.
        if (!empty($CFG->upgraderunning) || moodle_needs_upgrading()) {
            return;
        }

        if (empty($USER->id) || isguestuser()) {
            return;
        }

        // Avoid redirecting away from the plugin's own pages (the wizard itself, or the
        // token-refresh AJAX endpoint it calls).
        if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/auth/moowoodle/') !== false) {
            return;
        }

        if (!has_capability('moodle/site:config', \core\context\system::instance())) {
            return;
        }

        unset_config('promptsetupwizard', 'auth_moowoodle');
        redirect(new \moodle_url('/auth/moowoodle/setup_wizard.php'));
    }

    /**
     * Reminder banner shown to admins on Site administration pages until the MooWoodle
     * setup wizard has been completed, so a fresh install doesn't go unnoticed.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $OUTPUT;

        if ($PAGE->pagelayout !== 'admin') {
            return;
        }

        if (!has_capability('moodle/site:config', \core\context\system::instance())) {
            return;
        }

        if ((string) get_config('auth_moowoodle', 'setup_progress') === 'summary') {
            return;
        }

        $wizardurl = new \moodle_url('/auth/moowoodle/setup_wizard.php');

        if ($PAGE->url->compare($wizardurl, URL_MATCH_BASE)) {
            return;
        }

        $link = \html_writer::link($wizardurl, get_string('runsetupwizard', 'auth_moowoodle'), ['class' => 'alert-link']);

        $hook->add_html($OUTPUT->notification(get_string('setupwizard_reminder', 'auth_moowoodle', $link), 'info'));
    }
}
