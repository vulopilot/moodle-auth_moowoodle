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
 * Helper functions used by the settings page and the setup wizard.
 *
 * @package    auth_moowoodle
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2023 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_moowoodle\local;

use core\context\system as context_system;
use Exception;

/**
 * Helper functions used by the settings page and the setup wizard.
 */
class settings_handler {

    /** @var string Name given to the web service created for the WordPress connection. */
    const SERVICE_NAME = 'MooWoodle Connect';

    /** @var string[] External functions the WordPress plugin needs to be able to call. */
    const SERVICE_FUNCTIONS = [
        'auth_moowoodle_get_users',
        'auth_moowoodle_user_sync',
    ];

    /**
     * Check the server prerequisites the WordPress connection relies on.
     *
     * @return array List of checks, each with name, met (bool), settingsurl and description.
     */
    public static function get_requirement_checks(): array {
        global $CFG;

        $protocols = !empty($CFG->webserviceprotocols) ? explode(',', $CFG->webserviceprotocols) : [];

        return [
            [
                'name' => get_string('req_webservices', 'auth_moowoodle'),
                'met' => (bool) $CFG->enablewebservices,
                'settingsurl' => (new \moodle_url('/admin/search.php', ['query' => 'enablewebservices']))->out(),
            ],
            [
                'name' => get_string('req_restprotocol', 'auth_moowoodle'),
                'met' => in_array('rest', $protocols, true),
                'settingsurl' => (new \moodle_url('/admin/search.php', ['query' => 'webserviceprotocols']))->out(),
            ],
            [
                'name' => get_string('req_extendedchars', 'auth_moowoodle'),
                'met' => (bool) $CFG->extendedusernamechars,
                'settingsurl' => (new \moodle_url('/admin/search.php', ['query' => 'extendedusernamechars']))->out(),
            ],
        ];
    }

    /**
     * Generate a random secret key to share between Moodle and WordPress.
     *
     * @return string
     */
    public static function generate_secret_key(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * Find the web service previously created by the wizard, if any.
     *
     * @return \stdClass|false
     */
    public static function get_existing_service() {
        global $DB;

        $serviceid = get_config('auth_moowoodle', 'webservice_id');

        if (empty($serviceid)) {
            return false;
        }

        return $DB->get_record('external_services', ['id' => $serviceid], '*', IGNORE_MISSING);
    }

    /**
     * Find an active token issued for the given service and user.
     *
     * @param int $serviceid
     * @param int $userid
     * @return \stdClass|false
     */
    public static function get_existing_token(int $serviceid, int $userid) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/externallib.php');

        return $DB->get_record(
            'external_tokens',
            ['externalserviceid' => $serviceid, 'userid' => $userid, 'tokentype' => EXTERNAL_TOKEN_PERMANENT],
            '*',
            IGNORE_MULTIPLE
        );
    }

    /**
     * Create (or reuse) the external service and a token for the given user.
     *
     * @param int $userid User the token will be issued for. Needs the capabilities
     *                     required by auth_moowoodle_get_users / auth_moowoodle_user_sync.
     * @return array [success => bool, message => string, token => string|null]
     */
    public static function create_external_service(int $userid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/webservice/lib.php');
        require_once($CFG->libdir . '/externallib.php');

        $webservicemanager = new \webservice();

        try {
            $service = self::get_existing_service();

            if (!$service) {
                $shortname = self::generate_service_shortname();

                $servicedata = (object) [
                    'name' => self::SERVICE_NAME,
                    'shortname' => $shortname,
                    'enabled' => 1,
                    'restrictedusers' => 1,
                    'downloadfiles' => 0,
                    'uploadfiles' => 0,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];

                $serviceid = $webservicemanager->add_external_service($servicedata);
                set_config('webservice_id', $serviceid, 'auth_moowoodle');
            } else {
                $serviceid = $service->id;

                if (empty($service->enabled)) {
                    $service->enabled = 1;
                    $webservicemanager->update_external_service($service);
                }
            }

            foreach (self::SERVICE_FUNCTIONS as $functionname) {
                if (!$webservicemanager->service_function_exists($functionname, $serviceid)) {
                    $webservicemanager->add_external_function_to_service($functionname, $serviceid);
                }
            }

            $authorised = $DB->record_exists(
                'external_services_users',
                ['externalserviceid' => $serviceid, 'userid' => $userid]
            );

            if (!$authorised) {
                $webservicemanager->add_ws_authorised_user((object) [
                    'externalserviceid' => $serviceid,
                    'userid' => $userid,
                    'iprestriction' => null,
                    'validuntil' => null,
                ]);
            }

            $existingtoken = self::get_existing_token($serviceid, $userid);

            if ($existingtoken) {
                $token = $existingtoken->token;
            } else {
                $token = \external_generate_token(EXTERNAL_TOKEN_PERMANENT, $serviceid, $userid, context_system::instance());
            }

            return [
                'success' => true,
                'message' => get_string('webservice_created_success', 'auth_moowoodle'),
                'token' => $token,
                'serviceid' => $serviceid,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => get_string('webservice_created_fail', 'auth_moowoodle', $e->getMessage()),
                'token' => null,
                'serviceid' => null,
            ];
        }
    }

    /**
     * Generate a unique shortname for the external service.
     *
     * @return string
     */
    protected static function generate_service_shortname(): string {
        global $CFG;
        require_once($CFG->dirroot . '/webservice/lib.php');

        $webservicemanager = new \webservice();
        $base = 'moowoodleconnect';
        $suffix = 0;

        do {
            $suffix++;
            $candidate = $base . ($suffix > 1 ? $suffix : '');
        } while ($webservicemanager->get_external_service_by_shortname($candidate) && $suffix < 100);

        return $candidate;
    }

    /**
     * Best-effort check that the configured WordPress site is reachable.
     *
     * This only verifies the URL responds; it cannot confirm the MooWoodle
     * WordPress plugin is installed, since that requires an endpoint on the
     * WordPress side that this plugin does not control.
     *
     * @param string $wpsiteurl
     * @return array [success => bool, message => string]
     */
    public static function test_connection(string $wpsiteurl): array {
        global $CFG;

        $wpsiteurl = rtrim(trim($wpsiteurl), '/');

        if ($wpsiteurl === '' || !filter_var($wpsiteurl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => get_string('testconnection_invalidurl', 'auth_moowoodle'),
            ];
        }

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_NOBODY' => 1,
        ]);
        $curl->head($wpsiteurl);
        $info = $curl->get_info();
        $errno = $curl->get_errno();

        if ($errno || empty($info['http_code']) || $info['http_code'] >= 500) {
            return [
                'success' => false,
                'message' => get_string('testconnection_unreachable', 'auth_moowoodle', $wpsiteurl),
            ];
        }

        return [
            'success' => true,
            'message' => get_string('testconnection_reachable', 'auth_moowoodle', $wpsiteurl),
        ];
    }
}
