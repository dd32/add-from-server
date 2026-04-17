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
