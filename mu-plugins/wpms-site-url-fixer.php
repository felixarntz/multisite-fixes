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
		if ( '/' . self::get_directory() === substr( $value, -5 ) ) {
			$value = substr( $value, 0, -5 );
		}
		return $value;
	}

	public static function fix_site_url( $value ) {
		if ( '/' . self::get_directory() !== substr( $value, -5 ) ) {
			$value .= '/' . self::get_directory();
		}
		return $value;
	}

	public static function fix_network_site_url( $url, $path, $scheme ) {
		$path = ltrim( $path, '/' );
		$url = substr( $url, 0, strlen( $url ) - strlen( $path ) );

		if ( self::get_directory() . '/' !== substr( $url, -5 ) ) {
			$url .= self::get_directory() . '/';
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
