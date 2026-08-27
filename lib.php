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
 * Authentication plugin library functions.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Right after a fresh install, send the next admin who loads a page straight to the
 * setup wizard. This runs on every single request (it's a core bootstrap callback),
 * so it must return immediately once the one-time flag set by db/install.php is gone.
 */
function auth_moowoodle_after_config(): void {
    global $CFG, $USER;

    if (empty(get_config('auth_moowoodle', 'promptsetupwizard'))) {
        return;
    }

    if (CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER || during_initial_install()
            || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)
            || (defined('NO_MOODLE_COOKIES') && NO_MOODLE_COOKIES)) {
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
    redirect(new moodle_url('/auth/moowoodle/setup_wizard.php'));
}

/**
 * Reminder banner shown to admins on Site administration pages until the MooWoodle
 * setup wizard has been completed, so a fresh install doesn't go unnoticed.
 *
 * @return string
 */
function auth_moowoodle_before_standard_top_of_body_html(): string {
    global $PAGE, $OUTPUT;

    if ($PAGE->pagelayout !== 'admin') {
        return '';
    }

    if (!has_capability('moodle/site:config', \core\context\system::instance())) {
        return '';
    }

    if ((string) get_config('auth_moowoodle', 'setup_progress') === 'summary') {
        return '';
    }

    $wizardurl = new moodle_url('/auth/moowoodle/setup_wizard.php');

    if ($PAGE->url->compare($wizardurl, URL_MATCH_BASE)) {
        return '';
    }

    $link = html_writer::link($wizardurl, get_string('runsetupwizard', 'auth_moowoodle'), ['class' => 'alert-link']);

    return $OUTPUT->notification(get_string('setupwizard_reminder', 'auth_moowoodle', $link), 'info');
}

/**
 * Encrypt data using AES-256-GCM.
 *
 * @param array  $data Data to encrypt.
 * @param string $key Encryption key.
 * @return string|false Encrypted URL-safe data.
 */
function moowoodle_encrypt_data($data, $key) {
    $key = hash('sha256', $key, true);
    $iv = random_bytes(12);
    $tag = '';

    $encrypted = openssl_encrypt(
        json_encode($data),
        'AES-256-GCM',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if (false === $encrypted) {
        return false;
    }

    return rtrim(
        strtr(base64_encode($iv . $tag . $encrypted), '+/', '-_'),
        '='
    );
}

/**
 * Decrypt data using AES-256-GCM.
 *
 * @param string $encrypted Encrypted URL-safe data.
 * @param string $key Encryption key.
 * @return array|false Decrypted data.
 */
function moowoodle_decrypt_data($encrypted, $key) {
    $encrypted = strtr($encrypted, '-_', '+/');
    $encrypted .= str_repeat(
        '=',
        (4 - strlen($encrypted) % 4) % 4
    );

    $encrypted = base64_decode($encrypted, true);

    if (false === $encrypted || strlen($encrypted) < 28) {
        return false;
    }

    $iv = substr($encrypted, 0, 12);
    $tag = substr($encrypted, 12, 16);
    $ciphertext = substr($encrypted, 28);
    $key = hash('sha256', $key, true);

    $decrypted = openssl_decrypt(
        $ciphertext,
        'AES-256-GCM',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if (false === $decrypted) {
        return false;
    }

    $data = json_decode($decrypted, true);

    return is_array($data) ? $data : false;
}