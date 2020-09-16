=== Add From Server ===
Contributors: dd32
Tags: admin, media, uploads, post, import, files
Requires at least: 5.0
Tested up to: 5.2
Stable tag: 3.4

"Add From Server" is a quick plugin which allows you to import media & files into the WordPress uploads manager from the Webservers filesystem

== Description ==

** Support for this plugin is NOT offered, This plugin still however works. Please don't expect	support	requests to be answered, or "This doesn't work"	reviews	to be responded	to. **

Please Note: This plugin is not designed to replace the media uploader. This plugin is not designed to be used for migration of sites. This plugin is not designed to re-import your media upload history. This plugin is not designed to Batch import your media. Etc. This plugin is 8 years old and designed for importing singular large files which could not be uploaded through the administration interface.

The heart of a CMS is the ability to upload and insert content, WordPress does a fantastic job at this, unfortunately, some web hosts have limited servers, or users simply do not have the ability to upload large files through their web browser.
Add From Server is designed to help ease this pain, You can upload a bunch of files via FTP (Or your favourite transmission method) and simply import those files from the webserver directly into WordPress.

== Changelog ==

= 3.4 =
 * The plugin now requires WordPress 5.1+ and PHP 7.0+. No reason other than why not.
 * Bumps the version to stop the invalid vulnerability warnings.
 * Cleans up code.
 * Removes the User Access Control.
 * Removes the Root Directory Control.
 * Removes the Quick Jump functionality.
 * Removes the ability to be able to select the date for imported media. It's always today. Or, the 1st of the month if it's stored in a dated folder.

== FAQ ==

= Why does the file I want to import have a red background? =
WordPress only allows the importing/uploading of certain file types to improve your security.
If you wish to add extra file types, you can use a plugin such as: http://wordpress.org/extend/plugins/pjw-mime-config/ You can also enable "Unfiltered uploads" globally for WordPress if you'd like to override this security function. Please see the WordPress support forum for details.

= Where are the files saved? =
If you import a file which is outside your standard upload directory (usually wp-content/uploads/) then it will be copied to your current upload directory setting as normal.
If you however import a file which **is already within the uploads directory** (for example, wp-content/uploads/2011/02/superplugin.zip) then the file will not be copied, and will be used as-is.

= I have a a bug report =
You can report bugs on <a href="https://github.com/dd32/add-from-server">GitHub</a> and get support in the <a href="https://wordpress.org/support/plugin/add-from-server/">WordPress.org Support Forums</a>.
