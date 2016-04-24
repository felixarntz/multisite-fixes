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
			$site = get_blog_details( get_current_blog_id(), false );

			$content = str_replace( 'http://' . $site->domain, 'https://' . $site->domain, $content );
		}

		return $content;
	}

	public static function maybe_redirect_https() {
		if ( is_ssl() ) {
			return;
		}

		if ( self::is_ssl_site() ) {
			if ( ! defined( 'FORCE_SSL_ADMIN' ) ) {
				define( 'FORCE_SSL_ADMIN', true );
			}
			if ( ! defined( 'FORCE_SSL_LOGIN' ) ) {
				define( 'FORCE_SSL_LOGIN', true );
			}

			if ( ! is_ssl() ) {
				$host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
				$path = $_SERVER['REQUEST_URI'];

				wp_redirect( 'https://' . $host . $path, 301 );
				exit;
			}
		}
	}

	public static function is_ssl_site( $site_id = null ) {
		if ( defined( 'SSL_GLOBAL' ) && SSL_GLOBAL ) {
			return true;
		}

		if ( ! $site_id ) {
			$site_id = get_current_blog_id();
		}

		$site = get_blog_details( $site_id, false );
		$network = WP_Network::get_instance( $site->site_id );

		$ssl_networks = self::get_ssl_networks();
		if ( in_array( $network->id, $ssl_networks ) || in_array( $network->domain, $ssl_networks ) ) {
			return true;
		}

		$ssl_sites = self::get_ssl_sites();
		if ( in_array( $site->blog_id, $ssl_sites ) || in_array( $site->domain, $ssl_sites ) ) {
			return true;
		}

		return false;
	}

	public static function get_ssl_networks() {
		if ( ! defined( 'SSL_NETWORKS' ) ) {
			return array();
		}

		return array_map( 'trim', explode( ',', SSL_NETWORKS ) );
	}

	public static function get_ssl_sites() {
		if ( ! defined( 'SSL_SITES' ) ) {
			return array();
		}

		return array_map( 'trim', explode( ',', SSL_SITES ) );
	}
}

if ( is_multisite() && ( defined( 'SSL_GLOBAL' ) || defined( 'SSL_NETWORKS' ) || defined( 'SSL_SITES' ) ) ) {
	add_action( 'muplugins_loaded', array( 'WPMS_SSL_Redirects', 'maybe_redirect_https' ), 1 );
	add_filter( 'option_home', array( 'WPMS_SSL_Redirects', 'maybe_make_url_https' ) );
	add_filter( 'option_siteurl', array( 'WPMS_SSL_Redirects', 'maybe_make_url_https' ) );
	add_filter( 'the_content', array( 'WPMS_SSL_Redirects', 'maybe_make_url_https' ) );
}
