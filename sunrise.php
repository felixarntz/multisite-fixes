<?php
/*
Dropin Name: sunrise.php
Dropin URI:  https://github.com/felixarntz/multisite-fixes/
Version:     1.0.0
Author:      Felix Arntz
Author URI:  http://leaves-and-love.net
License:     GNU General Public License v2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class to bootstrap multisite. Does not work in a subdirectory environment.
 *
 * @since 1.0.0
 */
class WPMS_Sunrise {

	/**
	 * Bootstraps multisite by setting up the current site and network.
	 *
	 * If either of the two cannot be detected, this method will terminate the current PHP process.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 */
	public static function bootstrap() {
		if ( ! is_multisite() ) {
			// Skip if not a multisite.
			return;
		}

		if ( ! is_subdomain_install() ) {
			// Fail if a subdirectory install.
			wp_die( 'This multisite does not support a subdirectory installation.', 'Multisite Error', array( 'response' => 500 ) );
			exit;
		}

		// If we're installing, setup dummy objects and bail early.
		if ( wp_installing() ) {
			$site = new stdClass();
			$site->id = 1;
			$site->network_id = 1;
			$site->domain = '';
			$site->path = '/';
			$site->public = 1;
			// Back-compat
			$site->blog_id = 1;
			$site->site_id = 1;

			$network = new stdClass();
			$network->id = 1;
			$network->site_id = 1;
			$network->domain = '';
			$network->path = '/';
			// Back-compat
			$network->blog_id = 1;

			self::expose_globals( $site, $network );
			return;
		}

		$domain = self::get_current_domain();

		$domains = array( $domain );
		if ( 0 === strpos( $domain, 'www.' ) ) {
			$domains[] = substr( $domain, 4 );
		} elseif ( 1 === substr_count( $domain, '.' ) ) {
			$domains[] = 'www.' . $domain;
		}

		$site = self::detect_site( $domains );
		if ( $site ) {
			if ( $domain !== $site->domain ) {
				self::redirect( $site->domain );
				exit;
			}

			if ( empty( $site->network_id ) ) {
				$site->network_id = 1;
			}

			$network = self::detect_network( $site );
		} else {
			// Try to detect network another way if no site is found.
			$network = self::detect_network( $domains );
			if ( $network && $domain !== $network->domain ) {
				self::redirect( $network->domain );
				exit;
			}
		}

		if ( ! $network ) {
			self::fail_gracefully( $domain, 'network' );
			exit;
		}

		if ( ! $site ) {
			self::fail_gracefully( $domain, 'site' );
			exit;
		}

		// Detect the network's main site ID if not set yet.
		if ( empty( $network->site_id ) ) {
			if ( $site->domain === $network->domain && $site->path === $network->path ) {
				$network->site_id = $site->id;
			} else {
				$network->site_id = self::detect_network_main_site_id( $network );
			}
		}

		// If we reach this point, everything has been detected successfully.
		self::expose_globals( $site, $network );
	}

	/**
	 * Detects the current site from given domains.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @param array $domains Array of domains for the current site.
	 * @return WP_Site|false The current site, or false on failure.
	 */
	private static function detect_site( $domains = array() ) {
		$sites = get_sites( array(
			'number'     => 1,
			'domain__in' => $domains,
			'path'       => '/',
			'orderby'    => array( 'domain_length' => 'DESC' ),
		) );

		if ( empty( $sites ) ) {
			return false;
		}

		return array_shift( $sites );
	}

	/**
	 * Detects the current network from given domains or the current site object.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @param array|WP_Site $domains_or_site Either an array of domains for the current network, or the current
	 *                                       site object.
	 * @return WP_Network|false The current network, or false on failure.
	 */
	private static function detect_network( $domains_or_site = array() ) {
		if ( is_a( $domains_or_site, 'WP_Site' ) ) {
			return WP_Network::get_instance( $domains_or_site->network_id );
		}

		$networks = get_networks( array(
			'number'     => 1,
			'domain__in' => $domains,
			'path'       => '/',
			'orderby'    => array( 'domain_length' => 'DESC' ),
		) );

		if ( empty( $networks ) ) {
			return false;
		}

		return array_shift( $networks );
	}

	/**
	 * Detects the main site ID for a given network.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @param WP_Network A network object.
	 * @return int Main site ID for the network, or 0 if none found.
	 */
	private static function detect_network_main_site_id( $network ) {
		$main_site_id = wp_cache_get( 'network:' . $network->id . ':main_site', 'site-options' );

		if ( false === $main_site_id ) {
			$main_site_ids = get_sites( array(
				'number'     => 1,
				'fields'     => 'ids',
				'domain'     => $network->domain,
				'path'       => $network->path,
				'network_id' => $network->id,
			) );

			if ( ! empty( $main_site_ids ) ) {
				$main_site_id = array_shift( $main_site_ids );
			} else {
				$main_site_id = 0;
			}

			wp_cache_add( 'network:' . $network->id . ':main_site', $main_site_id, 'site-options' );
		}

		return $main_site_id;
	}

	/**
	 * Terminates the PHP process and triggers proper action hooks or redirects depending on the error.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @param string $domain Domain for the current request.
	 * @param string $mode   Optional. Which part of the lookup failed. Either 'site' or 'network'.
	 *                       Default 'site'.
	 */
	private static function fail_gracefully( $domain, $mode = 'site' ) {
		if ( 'network' === $mode ) {
			do_action( 'ms_network_not_found', $domain, '/' );
		} elseif ( defined( 'NOBLOGREDIRECT' ) && '%siteurl%' !== NOBLOGREDIRECT ) {
			header( 'Location: ' . NOBLOGREDIRECT );
			exit;
		}

		ms_not_installed( $domain, '/' );
	}

	/**
	 * Exposes the necessary WordPress globals for the detected current site and network.
	 *
	 * It furthermore loads the network options.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @param WP_Site    $site    Current site object.
	 * @param WP_Network $network Current network object.
	 */
	private static function expose_globals( $site, $network ) {
		global $current_blog, $current_site, $blog_id, $site_id, $public;

		$current_blog = $site;
		$current_site = $network;

		$blog_id = $site->id;
		$site_id = $network->id;

		$public = $site->public;

		wp_load_core_site_options( $network->id );
	}

	/**
	 * Redirects the current request to another domain.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @param string $domain Domain to redirect to.
	 */
	private static function redirect( $domain ) {
		$protocol = self::get_current_protocol();
		$path = self::get_current_path();

		header( 'Location: ' . $protocol . '://' . $domain . $path, true, 301 );
		exit;
	}

	/**
	 * Gets the domain for the current request.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @return string The current domain.
	 */
	private static function get_current_domain() {
		$domain = strtolower( stripslashes( $_SERVER['HTTP_HOST'] ) );
		if ( ':80' === substr( $domain, -3 ) ) {
			$domain = substr( $domain, 0, -3 );
			$_SERVER['HTTP_HOST'] = substr( $_SERVER['HTTP_HOST'], 0, -3 );
		} elseif ( ':443' === substr( $domain, -4 ) ) {
			$domain = substr( $domain, 0, -4 );
			$_SERVER['HTTP_HOST'] = substr( $_SERVER['HTTP_HOST'], 0, -4 );
		}

		return $domain;
	}

	/**
	 * Gets the path for the current request.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @return string The current path.
	 */
	private static function get_current_path() {
		return stripslashes( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Gets the protocol for the current request.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @return string The current protocol.
	 */
	private static function get_current_protocol() {
		if ( function_exists( 'is_ssl' ) ) {
			if ( is_ssl() ) {
				return 'https';
			}
			return 'http';
		}

		if ( ( isset( $_SERVER['https'] ) && ! empty( $_SERVER['https'] ) && $_SERVER['https'] !== 'off' ) || $_SERVER['SERVER_PORT'] == '443' ) {
			return 'https';
		}
		return 'http';
	}
}

WPMS_Sunrise::bootstrap();
