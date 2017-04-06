=== Add From Server ===
Contributors: dd32
Tags: admin, media, uploads, post, import, files
Requires at least: 4.5
Stable tag: 3.3.2

"Add From Server" is a quick plugin which allows you to import media & files into the WordPress uploads manager from the Webservers filesystem

== Description ==

The heart of a CMS is the ability to upload and insert content, WordPress does a fantastic job at this, unfortunately, some web hosts have limited servers, or users simply do not have the ability to upload large files through their web browser.
Add From Server is designed to help ease this pain, You can upload a bunch of files via FTP (Or your favourite transmission method) and simply import those files from the webserver directly into WordPress.

== Changelog ==

= 3.3.3 =
 * Fixes some scenario's where the translation warning sticks around for translated (and other english locales)
 * Fixes a PHP Warning
 * Support per-user locales
 * Bumps required version of WordPress to 4.5+

= 3.3.2 =
 * Security Fix: Fixes a CSRF vulnerability which could be used to trick a user into importing a large file to their site. Props to Edwin Molenaar (https://www.linkedin.com/in/edwinmolenaar)
 * Fix a typo that caused subsequent plugin activations to fail if the server doesn't meet the Add From Server requirements
 * Fix a path mismatch on certain windows configurations (No longer need to specify uppercase disk markers)
 * Import Audio metadata and store image/audio metadata in the same manner as core.

= 3.3.1 =
 * Fix plugin activation

= 3.3 =
 * The plugin now requires WordPress 4.0 and PHP 5.4 as a minumum requirement.
 * Updated to use WordPress.org translation system, please submit translations through https://translate.wordpress.org/projects/wp-plugins/add-from-server/stable
 * Updated to WordPress 4.3 styles

== Upgrade Notice ==

= 3.3.1 =
Warning: This plugin now requires WordPress 4.0 & PHP 5.4. Updates to support WordPress 4.3 & WordPress.org Language Pack Translations

= 3.3 =
Warning: This plugin now requires WordPress 4.0 & PHP 5.4. Updates to support WordPress 4.3 & WordPress.org Language Pack Translations

== FAQ ==

= What placeholders can I use in the Root path option? =
You can use `%role%` and `%username%` only.
In the case of `%role%`, the first role which the user has is used, this can mean that in complex installs where a user has many roles that using %role% could be unreliable.

= Why does the file I want to import have a red background? =
WordPress only allows the importing/uploading of certain file types to improve your security.
If you wish to add extra file types, you can use a plugin such as: http://wordpress.org/extend/plugins/pjw-mime-config/ You can also enable "Unfiltered uploads" globally for WordPress if you'd like to override this security function. Please see the WordPress support forum for details.

= Where are the files saved? =
If you import a file which is outside your standard upload directory (usually wp-content/uploads/) then it will be copied to your current upload directory setting as normal.
If you however import a file which **is already within the uploads directory** (for example, wp-content/uploads/2011/02/superplugin.zip) then the file will not be copied, and will be used as-is.

= I have a a bug report =
You can report bugs in the <a href="https://wordpress.org/support/plugin/add-from-server">plugins Support Forum here</a>

== Screenshots ==

1. The import manager, This allows you to select which files to import. Note that files which cannot be imported are Red.
2. The Options panel, This allows you to specify what users can access Add From Server, and which folders users can import files from.
