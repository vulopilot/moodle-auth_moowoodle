SSO for MooWoodle Pro
User Sync for MooWoodle Pro

## Setup

Site administration > Plugins > Authentication > MooWoodle Connect > Setup wizard walks through:

1. Checking that web services, the REST protocol, and (recommended) extended username characters are enabled.
2. Creating (or reusing) the Moodle external service and access token the MooWoodle WordPress plugin uses to call `auth_moowoodle_get_users` and `auth_moowoodle_user_sync`, and choosing which additional web service functions to grant it.
3. Entering the WordPress site URL and the shared SSO secret key.
4. A summary screen with the Moodle site URL and access token to paste into the MooWoodle WordPress plugin settings.

The same fields can also be edited directly from the plugin's settings page.

Until the wizard is completed, a reminder linking to it appears on Site administration pages for anyone with the `moodle/site:config` capability.
