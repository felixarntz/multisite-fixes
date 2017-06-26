<?php
/*
Plugin Name: WPMS Site URL Fixer
Plugin URI:  https://github.com/felixarntz/multisite-fixes/
Description: Fixes URLs in WordPress setups where WordPress Core is located in a subdirectory.
Version:     1.0.0
Author:      Felix Arntz
Author URI:  http://leaves-and-love.net
License:     GNU General Public License v2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WPMS_Site_URL_Fixer {
	public static function fix_home_url( $value ) {
		$core_directory = self::get_directory();

		if ( '/' . $core_directory === substr( $value, - strlen( $core_directory ) - 1 ) ) {
			$value = substr( $value, 0, - strlen( $core_directory ) - 1 );
		}
		return $value;
	}

	public static function fix_site_url( $value ) {
		$core_directory = self::get_directory();

		if ( '/' . $core_directory !== substr( $value, - strlen( $core_directory ) - 1 ) ) {
			$value .= '/' . $core_directory;
		}
		return $value;
	}

	public static function fix_network_site_url( $url, $path, $scheme ) {
		$core_directory = self::get_directory();

		$path = ltrim( $path, '/' );
		$url = substr( $url, 0, strlen( $url ) - strlen( $path ) );

		if ( $core_directory . '/' !== substr( $url, - strlen( $core_directory ) - 1 ) ) {
			$url .= $core_directory . '/';
		}

		return $url . $path;
	}

	public static function get_directory() {
		return trim( WP_CORE_DIRECTORY, '/' );
	}
}

if ( is_multisite() && defined( 'WP_CORE_DIRECTORY' ) && WP_CORE_DIRECTORY ) {
	add_filter( 'option_home', array( 'WPMS_Site_URL_Fixer', 'fix_home_url' ) );
	add_filter( 'option_siteurl', array( 'WPMS_Site_URL_Fixer', 'fix_site_url' ) );
	add_filter( 'network_site_url', array( 'WPMS_Site_URL_Fixer', 'fix_network_site_url' ), 10, 3 );
}
