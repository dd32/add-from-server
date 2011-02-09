=== Add From Server ===
Contributors: dd32
Tags: 2.5, admin, media, uploads, post
Requires at least: 3.0
Stable tag: 2.3

"Add From Server" is a quick plugin which allows you to import media & files into the WordPress uploads manager from the Webservers filesystem

== Description ==

== Changelog ==

= 3.0 =
 * 

= 2.3 =
 * Quick 3.0 compatibility release
 * Removed Deprecated notices, Fixed a few other warnings occasionally
 * GUID now a valid url when % is included in the filename
 * Requires WordPress 3.0 now.

= 2.x =
 * French update from Denis Rebaud

= 2.2.1 =
 * Remove svn:externals, The WordPress .zip packager does NOT like making peoples life easier when you've got multiple plugins.

= 2.2 =
 * Slight error warning changes
 * WARNING: 2.8.5/2.9 compatibility: ALL users who can upload files will now have access to the Add From Server functionality, This is due to security changes in wordpress removing the unfiltered uploads functionality. This has the side effect that you cannot upload ALL types of files too, See the FAQ for some more info.
 * Re-ordered changelog for 2.8 changelog compatibility.

= 2.1 =
 * Introduce QuickJump
 * Fix bugs related to the Admin navigation disapearing
 * Fix bugs related to hints showing up linking to the wrong page
 * Do not show the Inline uploaders tabs in the normal uploader :)
 * Fix 2.8.1's plugin security mashes..

= 2.0.1 =
 * Russian Translation from Lecactus

= 2.0 =
 * Requires WordPress 2.7+ (From now on, My Plugins will only be supported for the current stable branch)
 * WP2.7 SSL Support
 * WP2.7 checkbox support
 * WP2.7 upload modifications
 * WP2.7 Styling
 * Files/folders are sorted by name
 * Update Notification changelogs (On the plugins page)
 * Completely rewritten, Hopefully this'll fix some long-time bugs which have affected some.
 * Persion translation from sourena
 * Italian translation from Stafano

= 1.4 =
 * German Translation
 * More stuffing around with the checkbox that doesnt work for anyone, yet works on every test system i've tried
 * Set the date on imported files to that of their uploads folder

= 1.3.2 =
 * French translation changes from Ozh & Olivier
 * Fixed the checkbox list for certain unknown browsers.

= 1.3 =
 * Internationalisation; French translation
 * Internationalisation; Spanish translation
 * Checkbox select all
 * Import into non-post attachment

= 1.2 =
 * Fixed filename oddness including old directory names
 * Added a check to see if the file exists in the Media library allready
 * Added a check to see if the file is allready in the uploads folder before importing, and if so, simply add it to the database, do not mash the filesystem

= 1.1 =
 * Fixed a bug which causes the original import file to be deleted upon removing from the media library, The file in /uploads/2008/03/ remains however. Will now delete the file in the uploads folder instead of the original imported file, However, Be warned, files previously imported WILL remain as they are, and the original import file will be deleted(if you delete from the media library)

= 1.0 =
 * Initial Release


== Future Features ==
Please note that these are simply features i'd like to do, There is no timeframe, or guarantee that it will be in the next version.
1. Watch folder, New files detected in the watch manager automatically get imported
1. The ability to select a file and switch directly to adding it to the post

== Screenshots ==

1. The import manager