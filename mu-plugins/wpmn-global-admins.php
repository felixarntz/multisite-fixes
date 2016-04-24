<?php
/*
Plugin Name: WPMN Global Admins
Plugin URI:  https://github.com/felixarntz/multisite-fixes/
Description: Uses constants in `wp-config.php` to specify users who are global admins (across all networks) vs regular network admins.
Version:     1.0.0
Author:      Felix Arntz
Author URI:  http://leaves-and-love.net
License:     GNU General Public License v2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class WPMN_Global_Admins {
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		switch ( $cap ) {
			case 'manage_cache': // for WP Spider Cache plugin
			case 'manage_networks':
			case 'create_networks':
			case 'delete_networks':
			case 'delete_network':
			case 'manage_global_users':
				if ( ! self::is_global_admin( $user_id ) ) {
					$caps[] = 'do_not_allow';
				}
				break;
			case 'edit_user':
				if ( ! self::is_global_admin( $user_id ) && isset( $args[0] ) && self::is_global_admin( $args[0] ) ) {
					$caps[] = 'do_not_allow';
				}
				break;
		}

		return $caps;
	}

	public static function user_has_networks( $networks, $user_id ) {
		global $wpdb;

		$all_networks = $wpdb->get_col( "SELECT id FROM {$wpdb->site}" );
		if ( self::is_global_admin( $user_id ) ) {
			$user_networks = $all_networks;
		} else {
			$user_networks = array();
			foreach ( $all_networks as $network_id ) {
				if ( self::is_network_admin( $user_id, $network_id ) ) {
					$user_networks[] = (int) $network_id;
				}
			}
		}

		if ( empty( $user_networks ) ) {
			$user_networks = false;
		}

		return $user_networks;
	}

	public static function pre_user_query( &$user_query ) {
		global $wpdb;

		if ( current_user_can( 'manage_global_users' ) ) {
			return;
		}

		if ( 0 < absint( $user_query->query_vars['blog_id'] ) ) {
			return;
		}

		$sites = wp_get_sites();

		$site_queries = array();
		foreach ( $sites as $site ) {
			$site_queries[] = array(
				'key'		=> $wpdb->get_blog_prefix( $site['blog_id'] ) . 'capabilities',
				'compare'	=> 'EXISTS',
			);
		}

		$site_queries['relation'] = 'OR';

		if ( empty( $user_query->meta_query->queries ) ) {
			$user_query->meta_query->queries = $site_queries;
		} else {
			$user_query->meta_query->queries = array(
				'relation' => 'AND',
				array( $user_query->meta_query->queries, $site_queries ),
			);
		}
	}

	public static function set_super_admins() {
		global $super_admins;

		$super_admins = array_unique( array_merge( self::get_global_admins(), self::get_network_admins() ) );
	}

	public static function is_global_admin( $user_id ) {
		$global_admins = self::get_global_admins();
		if ( empty( $global_admins ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		return $user && $user->exists() && in_array( $user->user_login, $global_admins, true );
	}

	public static function is_network_admin( $user_id, $network_id = null ) {
		$network_admins = self::get_network_admins( $network_id );
		if ( empty( $network_admins ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		return $user && $user->exists() && in_array( $user->user_login, $network_admins, true );
	}

	public static function get_global_admins() {
		if ( ! defined( 'WP_GLOBAL_ADMINS' ) ) {
			return array();
		}

		return array_map( 'trim', explode( ',', WP_GLOBAL_ADMINS ) );
	}

	public static function get_network_admins( $network_id = null ) {
		if ( ! $network_id ) {
			$network_id = get_current_site()->id;
		}

		if ( ! defined( 'WP_NETWORK_' . $network_id . '_ADMINS' ) ) {
			return array();
		}

		return array_map( 'trim', explode( ',', constant( 'WP_NETWORK_' . $network_id . '_ADMINS' ) ) );
	}
}

if ( is_multisite() && ( defined( 'WP_GLOBAL_ADMINS' ) || defined( 'WP_NETWORK_1_ADMINS' ) ) ) {
	WPMN_Global_Admins::set_super_admins();
	add_filter( 'map_meta_cap', array( 'WPMN_Global_Admins', 'map_meta_cap' ), 10, 4 );
	add_filter( 'networks_user_is_network_admin', array( 'WPMN_Global_Admins', 'user_has_networks' ), 10, 2 );
	add_action( 'pre_user_query', array( 'WPMN_Global_Admins', 'pre_user_query' ), 10, 1 );
}
