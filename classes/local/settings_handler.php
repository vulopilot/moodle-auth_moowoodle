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

    /** @var string[] External functions the WordPress plugin needs to be able to call. Always granted. */
    const SERVICE_FUNCTIONS = [
        'auth_moowoodle_get_users',
        'auth_moowoodle_user_sync',
    ];

    /** @var string[] Additional functions the admin can optionally grant to the web service. */
    const SYNC_FUNCTIONS = [
        'core_webservice_get_site_info',
        'core_course_get_categories',
        'core_course_get_courses',
        'core_course_get_courses_by_field',
        'core_user_get_users',
        'core_user_create_users',
        'core_user_update_users',
        'core_user_delete_users',
        'enrol_manual_enrol_users',
        'enrol_manual_unenrol_users',
        'auth_moowoodle_get_users',
        'auth_moowoodle_user_sync',
        'core_cohort_get_cohorts',
        'core_cohort_add_cohort_members',
        'core_cohort_delete_cohort_members',
        'core_group_get_course_groups',
        'core_group_create_groups',
        'core_group_add_group_members',
        'core_group_delete_group_members',
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
     * Find any active token issued for the given service, regardless of user.
     *
     * @param int $serviceid
     * @return \stdClass|false
     */
    public static function get_any_token_for_service(int $serviceid) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/externallib.php');

        return $DB->get_record(
            'external_tokens',
            ['externalserviceid' => $serviceid, 'tokentype' => EXTERNAL_TOKEN_PERMANENT],
            '*',
            IGNORE_MULTIPLE
        );
    }

    /**
     * A snapshot of everything the Summary step needs to display.
     *
     * @return array
     */
    public static function get_summary(): array {
        global $CFG, $DB;

        $protocols = !empty($CFG->webserviceprotocols) ? explode(',', $CFG->webserviceprotocols) : [];

        $service = self::get_existing_service();
        $token = $service ? self::get_any_token_for_service((int) $service->id) : false;

        $functionsgranted = false;

        if ($service) {
            $granted = $DB->get_records_menu(
                'external_services_functions',
                ['externalserviceid' => $service->id],
                '',
                'id,functionname'
            );
            $functionsgranted = empty(array_diff(self::SERVICE_FUNCTIONS, $granted));
        }

        $capabilityok = false;

        if ($token) {
            $capabilityok = has_capability(
                'auth/moowoodle:syncusers',
                context_system::instance(),
                (int) $token->userid
            );
        }

        return [
            'restprotocol' => in_array('rest', $protocols, true),
            'webservices' => (bool) $CFG->enablewebservices,
            'passwordpolicy' => (bool) $CFG->passwordpolicy,
            'extendedusernamechars' => (bool) $CFG->extendedusernamechars,
            'webservicefunctions' => $functionsgranted,
            'capability' => $capabilityok,
            'moodleurl' => $CFG->wwwroot,
            'webservicename' => $service->name ?? '',
            'token' => $token->token ?? '',
            'wordpressurl' => (string) get_config('auth_moowoodle', 'wpsiteurl'),
            'langcode' => $CFG->lang,
        ];
    }

    /**
     * Tokens issued for the given service, keyed by the token string itself.
     *
     * @param int $serviceid
     * @return string[]
     */
    public static function get_tokens_for_service(int $serviceid): array {
        global $CFG, $DB;

        if ($serviceid <= 0) {
            return [];
        }

        require_once($CFG->libdir . '/externallib.php');

        $tokens = $DB->get_records(
            'external_tokens',
            ['externalserviceid' => $serviceid, 'tokentype' => EXTERNAL_TOKEN_PERMANENT],
            'id ASC'
        );

        $options = [];

        foreach ($tokens as $token) {
            $user = \core_user::get_user((int) $token->userid, '*', IGNORE_MISSING);
            $name = $user ? trim(fullname($user)) : '';

            if ($name === '') {
                $name = get_string('webservice_unknownuser', 'auth_moowoodle', $token->userid);
            }

            $options[$token->token] = $name . ' (' . substr($token->token, 0, 8) . '…)';
        }

        return $options;
    }

    /**
     * List of custom (non built-in) external services, keyed by id.
     *
     * @return string[]
     */
    public static function get_existing_services(): array {
        global $DB;

        $records = $DB->get_records_select(
            'external_services',
            'component IS NULL',
            null,
            'name ASC',
            'id,name'
        );

        $options = [];
        foreach ($records as $record) {
            $options[$record->id] = $record->name;
        }

        return $options;
    }

    /**
     * Users allowed to hold a token, keyed by id.
     *
     * @return string[]
     */
    public static function get_selectable_users(): array {
        $users = get_users_by_capability(
            context_system::instance(),
            'moodle/webservice:createtoken',
            'u.id,u.firstname,u.lastname,u.email',
            'u.lastname ASC, u.firstname ASC'
        );

        $options = [];
        foreach ($users as $user) {
            $options[$user->id] = fullname($user) . ' (' . $user->email . ')';
        }

        return $options;
    }

    /**
     * Functions the admin can optionally grant to the web service.
     *
     * @return string[]
     */
    public static function get_selectable_sync_functions(): array {
        return self::SYNC_FUNCTIONS;
    }

    /**
     * Functions currently granted to the web service (mandatory ones are always included).
     *
     * @return string[]
     */
    public static function get_enabled_sync_functions(): array {
        $stored = get_config('auth_moowoodle', 'syncfunctions');

        if ($stored === false || $stored === '') {
            return self::SERVICE_FUNCTIONS;
        }

        $selected = array_filter(explode(',', $stored));

        return array_values(array_unique(array_merge(self::SERVICE_FUNCTIONS, $selected)));
    }

    /**
     * Store which optional functions are granted, and apply the change to an existing service.
     *
     * @param string[] $functions Functions selected by the admin, from SYNC_FUNCTIONS.
     */
    public static function save_sync_functions(array $functions): void {
        $functions = array_values(array_intersect(self::SYNC_FUNCTIONS, $functions));
        $functions = array_unique(array_merge(self::SERVICE_FUNCTIONS, $functions));

        set_config('syncfunctions', implode(',', $functions), 'auth_moowoodle');

        $service = self::get_existing_service();

        if ($service) {
            self::sync_service_functions((int) $service->id, $functions);
        }
    }

    /**
     * Grant the given functions to the service.
     *
     * Only adds functions; never removes any. The selected service may be one the
     * admin picked from existing services rather than one this plugin created, so
     * unchecking a function here does not strip anything it might already be using
     * for another purpose. Functions can be removed from a service, if needed, from
     * Moodle's own External services screen.
     *
     * @param int $serviceid
     * @param string[] $functions Functions that should be granted.
     */
    protected static function sync_service_functions(int $serviceid, array $functions): void {
        global $CFG;

        require_once($CFG->dirroot . '/webservice/lib.php');
        $webservicemanager = new \webservice();

        foreach ($functions as $functionname) {
            if (!$webservicemanager->service_function_exists($functionname, $serviceid)) {
                $webservicemanager->add_external_function_to_service($functionname, $serviceid);
            }
        }
    }

    /**
     * Save the general server requirement toggles onto core config.
     *
     * @param \stdClass $data
     */
    public static function save_general_settings(\stdClass $data): void {
        global $CFG;

        set_config('enablewebservices', !empty($data->enablewebservices) ? 1 : 0);

        $protocols = !empty($CFG->webserviceprotocols) ? explode(',', $CFG->webserviceprotocols) : [];
        $protocols = array_filter($protocols, static function ($protocol) {
            return $protocol !== '' && $protocol !== 'rest';
        });

        if (!empty($data->restprotocol)) {
            $protocols[] = 'rest';
        }

        set_config('webserviceprotocols', implode(',', array_unique($protocols)));
        set_config('passwordpolicy', !empty($data->passwordpolicy) ? 1 : 0);
        set_config('extendedusernamechars', !empty($data->extendedusernamechars) ? 1 : 0);
    }

    /**
     * Create a new external service, or reuse an existing one, and issue a token for the given user.
     *
     * @param int $serviceid Existing external_services.id to reuse, or 0 to create a new service.
     * @param int $userid User the token will be issued for. Needs the capabilities
     *                     required by auth_moowoodle_get_users / auth_moowoodle_user_sync.
     * @param string $servicename Name for a newly created service. Ignored when reusing one. Falls
     *                            back to SERVICE_NAME if blank.
     * @return array [success => bool, message => string, token => string|null, serviceid => int|null]
     */
    public static function create_or_update_service(int $serviceid, int $userid, string $servicename = ''): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/webservice/lib.php');
        require_once($CFG->libdir . '/externallib.php');

        $webservicemanager = new \webservice();

        try {
            if ($serviceid > 0) {
                $service = $DB->get_record('external_services', ['id' => $serviceid], '*', MUST_EXIST);

                if (empty($service->enabled)) {
                    $service->enabled = 1;
                    $webservicemanager->update_external_service($service);
                }
            } else {
                $shortname = self::generate_service_shortname();

                $servicedata = (object) [
                    'name' => trim($servicename) !== '' ? trim($servicename) : self::SERVICE_NAME,
                    'shortname' => $shortname,
                    'enabled' => 1,
                    'restrictedusers' => 1,
                    'downloadfiles' => 0,
                    'uploadfiles' => 0,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];

                $serviceid = $webservicemanager->add_external_service($servicedata);
            }

            set_config('webservice_id', $serviceid, 'auth_moowoodle');

            foreach (self::get_enabled_sync_functions() as $functionname) {
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
