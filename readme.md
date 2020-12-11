Add From Server
===============
* Contributors: dd32
* Tags: admin, media, uploads, post, import, files
* Requires at least: 5.4
* Tested up to: 5.5
* Requires PHP: 7.0
* Stable tag: 3.4.5

Add From Server is designed to help ease the pain of bad web hosts, allowing you to upload files via FTP or SSH and later import them into WordPress.

## Description

This plugin offers limited support. Please do not expect new features or too many bugfixes. Features may be removed at any time.

Add From Server is designed to help ease the pain of bad web hosts, allowing you to upload files via FTP or SSH and later import them into WordPress.

This plugin is NOT designed to..
 * Be used as a replacement for the file uploader
 * Be used for migration of websites
 * Re-import your files after moving webhosting
 * Batch import media

This plugins IS designed to..
 * Import files which are larger than your hosting allows to be uploaded.
 * Import files which are too large for your internet connections upload speed.

WordPress does a better job of file uploads than this plugin, so please consider your needs before you use it.

You may also want to look at using WP-CLI for media import purposes:
https://developer.wordpress.org/cli/commands/media/import/

## Changelog

### 3.4.5
 * Fix a fatal error when WordPress or PHP requirements are not met.

### 3.4.4
 * Simplify the date handling

### 3.4.3
 * Better handling for `/` as the root path
 * Better compatibility with certain WordPress docker images
 * Better handling for some empty folders

### 3.4.2
 * Restore case insensitive alphabetical sorting

### 3.4.1
 * Plugin now requires WordPress 5.4+

### 3.4
 * The plugin now requires WordPress 5.1+ and PHP 7.0+. No reason other than why not.
 * Bumps the version to stop the invalid vulnerability warnings.
 * Cleans up code.
 * Removes the User Access Control. Any user with File Upload ability can now use the plugin.
 * Removes the Root Directory Control. The root directory is now assumed. You can use the ADD_FROM_SERVER constant to change it.
 * Removes the Quick Jump functionality.
 * Removes the ability to be able to select the date for imported media. It's always today. Or, the 1st of the month if it's stored in a dated folder.
 * Removed Media Manager integration, as it's no longer shown with the WordPress Block Editor. Classic Editor is not supported by this plugin.

## Frequently Asked Questions

### How can I import files from other folders?
In 3.4, the plugin changed to limit the directories you can import files from.
If you wish to import files from other folders, you need to add the ADD_FROM_SERVER constant to your wp-config.php file.
For example:
`define( 'ADD_FROM_SERVER', '/www/' );`

### Why does the file I want to import have a red background?
WordPress only allows the importing/uploading of certain file types to improve your security.
If you wish to add extra file types, you can use a plugin such as: http://wordpress.org/extend/plugins/pjw-mime-config/ You can also enable "Unfiltered uploads" globally for WordPress if you'd like to override this security function. Please see the WordPress support forum for details.

### Where are the files saved?
If you import a file which is outside your standard upload directory (usually wp-content/uploads/) then it will be copied to your current upload directory setting as normal.
If you however import a file which **is already within the uploads directory** (for example, wp-content/uploads/2011/02/superplugin.zip) then the file will not be copied, and will be used as-is.

### I have a a bug report
You can report bugs on <a href="https://github.com/dd32/add-from-server">GitHub</a> and get support in the <a href="https://wordpress.org/support/plugin/add-from-server/">WordPress.org Support Forums</a>.
