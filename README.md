MooWoodle Connect for Moodle
==============================================

# Table of Contents

- [Description](#description)
- [Features](#features)
- [Plugin Version](#plugin-version)
- [Required version of Moodle](#required-version-of-moodle)
- [Free Software](#free-software)
- [Support](#support)
- [Installation](#installation)
- [Uninstallation](#uninstallation)
- [Files Information](#files-information)
- [History](#history)
- [Author](#author)
- [Provided by](#provided-by)

# Description

MooWoodle Connect is a Moodle authentication plugin that provides Single Sign-On and user
synchronization between a Moodle site and the MooWoodle WordPress plugin.

It lets a WordPress site running MooWoodle:

* Log a user into Moodle via a signed, time-limited SSO link, without a separate Moodle login.
* Pull batches of Moodle user data (`auth_moowoodle_get_users`) and push user create/update
  requests into Moodle (`auth_moowoodle_user_sync`) over Moodle's web services API.

Please note: the MooWoodle plugin is mandatory on the WordPress side for the integration to
work, and must be installed and configured there separately.

[(Back to top)](#table-of-contents)

# Features

* Single sign-on from WordPress into Moodle using a shared secret key and a short-lived,
  signed login link.
* Two-way user synchronization: WordPress can create or update Moodle users (username,
  email, name, and — when supplied — password), matched by email.
* A batched user-export endpoint, restricted to a chosen set of roles, for WordPress to pull
  existing Moodle users.
* A guided setup wizard that checks prerequisites, creates the Moodle web service and access
  token, and walks through connecting to the WordPress site.
* Fine-grained control over which additional web service functions (beyond the two this
  plugin requires) are granted to the WordPress integration user.
* A reminder banner on Site administration pages, shown to anyone with the
  `moodle/site:config` capability, until the setup wizard has been completed.

[(Back to top)](#table-of-contents)

# Plugin Version

v1.1.0 (Build: 2026083102) - Latest

[(Back to top)](#table-of-contents)

# Required version of Moodle

This plugin requires Moodle 5.0 and above, and is tested and supported through the Moodle 5.3
series (including the 5.3dev branch), per the `$plugin->requires` and `$plugin->supported`
declarations in `version.php`.

[(Back to top)](#table-of-contents)

# Free Software

MooWoodle Connect is free software under the terms of the GNU General Public License v3, or
(at your option) any later version — see the license header in any plugin file, or
http://www.gnu.org/copyleft/gpl.html.

If you are unsure about anything, the FAQ at http://www.gnu.org/licenses/gpl-faq.html is a
good place to look.

[(Back to top)](#table-of-contents)

# Support

For issues or questions about this plugin, please use the GitHub issue tracker at
https://github.com/vulopilot/moodle-auth_moowoodle.

[(Back to top)](#table-of-contents)

# Installation

= Minimum Requirements =
* Moodle 5.0 or higher (see [Required version of Moodle](#required-version-of-moodle))
* The MooWoodle plugin installed and active on the WordPress site you want to connect to

= Moodle Plugin Automatic Installation =
* Go to Site administration > Plugins > Install plugins.
* Upload the plugin zip file.
* Click "Install plugin from the ZIP file".

= Moodle Plugin Manual Installation =
* Unzip the plugin and upload the resulting folder as `auth/moowoodle` in your Moodle
  installation, using the FTP application of your choice.
* Visit Site administration > Notifications to complete the install.

= Moodle Configuration =
Right after installation finishes, the next page an admin loads redirects straight into the
setup wizard at Site administration > Plugins > Authentication > MooWoodle Connect > Setup
wizard. Until the wizard is completed, a reminder also appears on Site administration pages
for anyone with the `moodle/site:config` capability.

The wizard walks through:

1. **General** — checking that web services, the REST protocol, and (recommended) extended
   username characters are enabled.
2. **Web Service** — creating (or reusing) the Moodle external service and access token that
   the MooWoodle WordPress plugin authenticates with, and choosing which additional web
   service functions to grant it.
3. **WordPress Site** — entering the WordPress site URL and the shared SSO secret key.
4. **Summary** — the Moodle site URL and access token to paste into the MooWoodle WordPress
   plugin settings.

The same fields can also be edited directly from the plugin's settings page afterwards.

[(Back to top)](#table-of-contents)

# Uninstallation

Go to Site administration > Plugins > Plugin overview, find MooWoodle Connect under
Authentication plugins, and click Uninstall. Then remove the `auth/moowoodle` folder from
your Moodle installation.

[(Back to top)](#table-of-contents)

# Files Information

Languages
---------
`lang/en` contains the language strings for this plugin.

Classes
-------
`classes/local` contains the setup wizard and settings-handling logic; `classes/settings`
contains the Moodle form definitions used by the wizard and settings page; `classes/event`
contains the observer for real-time user sync.

[(Back to top)](#table-of-contents)

# History

See the commit history at https://github.com/dualcube/moodle-auth_moowoodle/commits.

[(Back to top)](#table-of-contents)

# Author

DualCube

[(Back to top)](#table-of-contents)

# Provided by

DualCube (https://dualcube.com)

[(Back to top)](#table-of-contents)
