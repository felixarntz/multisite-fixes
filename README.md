# Multisite Fixes

**DISCLAIMER: This project is no longer maintained.**

This repository contains a `sunrise.php` and some must-use plugins to address common issues when working with Multisite and Multinetwork.

## Bundled functionalities

All parts of this repository can be used individually as needed. You don't have to use all of them.

* **`sunrise.php`**: Detects www vs non-www and redirects if necessary
* **WPMS SSL Redirects** Plugin: Allows to define which sites are HTTPS (through `wp-config.php`) and redirects if necessary
* **WPMN Global Admins** Plugin: Uses constants in `wp-config.php` to specify users who are global admins (across all networks) vs regular network admins
* **WPMS Site URL Fixer** Plugin: Fixes URLs in WordPress setups where WordPress Core is located in a subdirectory

## Setup

1. Copy all must-use plugin files you would like to use into your `wp-content/mu-plugins` directory.
2. If you wanna use `sunrise.php`, copy it directly into your `wp-content` directory and then add `define( 'SUNRISE', 'on' );` to your `wp-config.php` file.

### Individual Plugins

* For **WPMS SSL Redirects**, define a constant called `SSL_SITES` in your `wp-config.php` and fill it with a comma-separated list of domains that should use HTTPS; you may also add a constant `SSL_NETWORKS` in a similar manner to make entire networks use HTTPS or `SSL_GLOBAL` with boolean true to have all sites in all networks use HTTPS.
* For **WPMN Global Admins**, define a constant called `WP_GLOBAL_ADMINS` in your `wp-config.php` and fill it with a comma-separated list of usernames that should be global administrators; you should then add further constants named like `WPMN_NETWORK_{{NETWORK_ID}}_ADMINS` in a similar manner to define the network admins for each network.
* For **WPMS Site URL Fixer**, define a constant called `WP_CORE_DIRECTORY` an simply add the subdirectory name where WordPress Core resides (no trailing slashes!).

## Be aware

Everything here should only be used for subdomain (or domain) setups. Do not use with subdirectory setups!

The code in this repository requires at least WordPress 4.6.
