<?php
/*
Plugin Name: WPMS SSL Redirects
Plugin URI:  https://github.com/felixarntz/multisite-fixes/
Description: Allows to define which sites are HTTPS (through `wp-config.php`) and redirects if necessary.
Version:     1.0.0
Author:      Felix Arntz
Author URI:  http://leaves-and-love.net
License:     GNU General Public License v2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WPMS_SSL_Redirects {
	public static function maybe_make_url_https( $content ) {
		if ( self::is_ssl_site() ) {
			$site = get_site();

			$content = str_replace( 'http://' . $site->domain, 'https://' . $site->domain, $content );
		}

		return $content;
	}

	public static function maybe_redirect_https() {
		if ( self::is_ssl_site() && ! is_ssl() ) {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
			$path = $_SERVER['REQUEST_URI'];

			wp_redirect( 'https://' . $host . $path, 301 );
			exit;
		}
	}

	public static function maybe_force_ssl_admin() {
		if ( self::is_ssl_site() ) {
			if ( ! defined( 'FORCE_SSL_ADMIN' ) ) {
				define( 'FORCE_SSL_ADMIN', true );
			}
			if ( ! defined( 'FORCE_SSL_LOGIN' ) ) {
				define( 'FORCE_SSL_LOGIN', true );
			}
		}
	}

	public static function is_ssl_site( $site_id = null ) {
		if ( defined( 'SSL_GLOBAL' ) && SSL_GLOBAL ) {
			return true;
		}

		$site = get_site( $site_id );
		$network = get_network( $site->network_id );

		$ssl_networks = self::get_ssl_networks();
		if ( in_array( (int) $network->id, $ssl_networks, true ) || in_array( $network->domain, $ssl_networks, true ) ) {
			return true;
		}

		$ssl_sites = self::get_ssl_sites();
		if ( in_array( (int) $site->id, $ssl_sites, true ) || in_array( $site->domain, $ssl_sites, true ) ) {
			return true;
		}

		return false;
	}

	public static function get_ssl_networks() {
		if ( ! defined( 'SSL_NETWORKS' ) ) {
			return array();
		}

		$network_ids = explode( ',', SSL_NETWORKS );
		foreach ( $network_ids as $index => $network_id ) {
			$network_id = trim( $network_id );

			if ( is_numeric( $network_id ) ) {
				$network_id = (int) $network_id;
			}

			$network_ids[ $index ] = $network_id;
		}

		return $network_ids;
	}

	public static function get_ssl_sites() {
		if ( ! defined( 'SSL_SITES' ) ) {
			return array();
		}

		return array_map( 'trim', explode( ',', SSL_SITES ) );

		$site_ids = explode( ',', SSL_SITES );
		foreach ( $site_ids as $index => $site_id ) {
			$site_id = trim( $site_id );

			if ( is_numeric( $site_id ) ) {
				$site_id = (int) $site_id;
			}

			$site_ids[ $index ] = $site_id;
		}

		return $site_ids;
	}
}

if ( is_multisite() && ( defined( 'SSL_GLOBAL' ) || defined( 'SSL_NETWORKS' ) || defined( 'SSL_SITES' ) ) ) {
	add_action( 'muplugins_loaded', array( 'WPMS_SSL_Redirects', 'maybe_force_ssl_admin' ), 1 );
	add_action( 'plugins_loaded', array( 'WPMS_SSL_Redirects', 'maybe_redirect_https' ), 1 );
	add_filter( 'option_home', array( 'WPMS_SSL_Redirects', 'maybe_make_url_https' ) );
	add_filter( 'option_siteurl', array( 'WPMS_SSL_Redirects', 'maybe_make_url_https' ) );
	add_filter( 'the_content', array( 'WPMS_SSL_Redirects', 'maybe_make_url_https' ) );
}
