SSO for MooWoodle Pro
User Sync for MooWoodle Pro

## Setup

Site administration > Plugins > Authentication > MooWoodle Connect > Setup wizard walks through:

1. Checking that web services, the REST protocol, and (recommended) extended username characters are enabled.
2. Entering the WordPress site URL and the shared SSO secret key.
3. Creating the Moodle external service and access token the MooWoodle WordPress plugin uses to call `auth_moowoodle_get_users` and `auth_moowoodle_user_sync`.
4. A summary screen with the Moodle site URL and access token to paste into the MooWoodle WordPress plugin settings.

The same fields can also be edited directly from the plugin's settings page.
