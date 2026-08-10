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