=== Add From Server ===
Contributors: dd32
Tags: 2.5, admin, media, uploads, post
Requires at least: 2.5
Tested up to: 2.5
Stable tag: 1.2

"Add From Server" is a quick plugin which allows you to import media & files into the WordPress uploads manager from the Webservers filesystem

== Description ==

WordPress 2.5 includes a new Media manager, However, It only knows about files which have been uploaded via the WordPress interface, Not files which have been uploaded via other means(eg, FTP).

So i present, "Add From Server" a WordPress plugin which allows you to browse the filesystem on the webserver and copy any files into the WordPress uploads system, Once "imported" it'll be treated as any other uploaded file, and you can access it via the Media Li

== Changelog ==

= 1.0 =
 * Initial Release
= 1.1 =
 * Fixed a bug which causes the original import file to be deleted upon removing from the media library, The file in /uploads/2008/03/ remains however. Will now delete the file in the uploads folder instead of the original imported file, However, Be warned, files previously imported WILL remain as they are, and the original import file will be deleted(if you delete from the media library)
= 1.2 =
 * Fixed filename oddness including old directory names
 * Added a check to see if the file exists in the Media library allready
 * Added a check to see if the file is allready in the uploads folder before importing, and if so, simply add it to the database, do not mash the filesystem
= 1.3 =
 * Internationalisation; Partial French translation
 * Internationalisation; Partial Spanish translation
 * Checkbox select all
 * Import into non-post attachment

== Future Features ==
Please note that these are simply features i'd like to do, There is no timeframe, or guarantee that it will be in the next version.
1. Watch folder, New files detected in the watch manager automatically get imported
1. The ability to select a file and switch directly to adding it to the post

== Screenshots ==

1. The import manager