Add From Server
===============
* Contributors: dd32
* Tags: admin, media, uploads, post, import, files, block-editor
* Requires at least: 6.9
* Tested up to: 7.0
* Requires PHP: 7.4
* Stable tag: 4.0.0

Add From Server is designed to help ease the pain of bad web hosts, allowing you to upload files via FTP or SSH and later import them into WordPress.

## Description

Add From Server is designed to import files which are larger than your hosting allows you to upload, or too large for your internet connection's upload speed. It is **not** a replacement for the file uploader, a migration tool, or a batch media importer.

Version 4.0 is a full modernization:

* A REST API (`/wp-json/add-from-server/v1`) with strict capability checks.
* A modern React admin page built on `@wordpress/components`.
* Block editor integration via a sidebar plugin — browse and import files without leaving the post editor.
* A hardened Filesystem helper that confines all browsing and imports to a configurable root directory and rejects path traversal, symlink escapes and paths that canonically fall outside the root.
* The legacy `frmsvr_root` option, language nag screen and other accumulated cruft have been removed. The root directory is now configured exclusively via the `ADD_FROM_SERVER` constant or the `add_from_server_root` filter.
* PSR-4 autoloading, strict types, PHPUnit unit tests and a local environment powered by `@wordpress/env`.

## Installation

1. Install the plugin into `wp-content/plugins/add-from-server/`.
2. Activate via the Plugins screen.
3. Visit **Media → Add From Server**, or open the Add From Server panel in the block editor sidebar.

## Configuration

By default, Add From Server is confined to the parent of your `wp-content` directory. To point it somewhere else, define the constant in `wp-config.php`:

```php
define( 'ADD_FROM_SERVER', '/var/www/media/' );
```

Or use the filter (useful if you want to compute the path dynamically):

```php
add_filter( 'add_from_server_root', function ( $root ) {
    return '/var/www/media/';
} );
```

## Local development

This plugin ships with a full local dev environment powered by [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/):

```bash
composer install
npm install
npm run start     # boot WP at http://localhost:8888
npm run test:php  # run the full PHPUnit suite (unit + integration)
```

The unit suite has no external dependencies:

```bash
./vendor/bin/phpunit --testsuite=unit
```

## Frequently Asked Questions

### How can I import files from other folders?
Define the `ADD_FROM_SERVER` constant in `wp-config.php`, or use the `add_from_server_root` filter.

### Why does a file I want to import have a red background / appear disabled?
WordPress only allows the importing/uploading of certain file types for security. You can use a plugin such as [pjw-mime-config](https://wordpress.org/plugins/pjw-mime-config/) to add additional file types.

### Where are the files saved?
If you import a file which is outside your uploads directory (usually `wp-content/uploads/`) it will be copied into your current uploads directory. If you import a file that is already **inside** the uploads directory, it will be attached in-place without being copied.

### Where do I report bugs?
Report bugs on [GitHub](https://github.com/dd32/add-from-server) and get support in the [WordPress.org support forums](https://wordpress.org/support/plugin/add-from-server/).

## Changelog

See [changelog.txt](./changelog.txt) for a complete history.
