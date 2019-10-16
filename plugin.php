<?php

/**
 * Plugin Name: SHC Wordpress Beta/RC Tester
 * Description: Limit core updates to next Beta/RC release if currently running a Beta/RC release
 * Version: 0.1.0
 * Author: Paul V. Biron/Sparrow Hawk Computing
 * Author URI: https://sparrowhawkcomputing.com
 * Plugin URI: https://github.com/pbiron/shc-wordpress-beta-tester
 * GitHub Plugin URI: https://github.com/pbiron/shc-wordpress-beta-tester
 * Network: true
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace SHC\WordPress_Beta_Tester;

defined( 'ABSPATH' ) || die;

global $wp_version;
if ( ! preg_match( '@' . Plugin::$beta_rc_version_regex . '@', $wp_version ) ) {
	// site is not running a "named" beta/RC release.
	// nothing to do, so bail (essentially, we'll be a no-op plugin in this case).
	return;
}

/**
 * Class to modify the response to the Core Update API.
 *
 * Ideally, these mods would be part of the Core Update API, but since that is not
 * open-sourced, it likely would be hard (i.e., take a long time) for whoever controls
 * that API to incorportate these mods.
 *
 * Since this class will not be instantiated if the current site is not
 * running a "named" beta/RC release, we don't need to check that in any
 * of the methods here.
 *
 * My immediate use-case for this functionality is as follows:
 *
 * 1. once I install beta1 on a site, I want to continue to test *that* version
 *    and not worry about things working differently tomorrow after additional commits
 *    have been made to trunk.  For instance, if I find something that doesn't work
 *    correctly in beta1 but don't have time to immediately investigate exactly why,
 *    I don't want the site to get updated to the next nightly...as that might change
 *    the behavior that I identified.
 *
 * However, it might be useful to use this prototype to investigate how those API mods
 * would work.  For that purpose, the logic of when to test for the next beta/RC package would
 * have to change a little.  For instance, suppose the site is running 5.4-alpha-12345,
 * then the "next" package would be 5.4-beta1.  I don't think that change would be
 * too hard.  Also, see the "@todo" in update_to_beta_or_rc_releases().
 *
 * @since 0.1.0
 */
class Plugin {
	/**
	 * Regular expression for extracting the components of `$wp_version` string.
	 *
	 * The subpatterns are as follows:
	 *
	 * 1. The first is the WP version number (e.g., 5.2.3).
	 * 2. The second is the minor version number (e.g., .3 in the above example).
	 *    This subpattern is optional because WP uses versions numbers like
	 *    5.3 instead of 5.3.0.
	 * 3. The third is whether the release is a beta or RC.
	 * 4. The forth is the number of the beta or RC release (e.g., 1st beta, 2nd RC, etc).
	 *
	 * If a `$wp_version` string does not match this regex, then the site is
	 * not running a beta/RC release.
	 *
	 * We store this regex as a static property because we use it in 2 separate places
	 * and doing so ensures that the regex is the same in both places.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	static $beta_rc_version_regex = '^(\d+\.\d+(\.\d+)?)-(beta|RC)(\d+)$';

	/**
	 * Used to store the URL(s) for the next beta/RC download packages.
	 *
	 * @since 0.1.0
	 *
	 * @var array
	 */
	protected $next_package_urls = array();

	/**
	 * Constructor.
	 *
	 * Adds the {@link https://developer.wordpress.org/reference/hooks/http_response/ http_response}
	 * hook callback.
	 *
	 * Also generates the URLs for the next beta/RC download packages.  We do that here so that
	 * we don't have to do it inside the `http_response` callback, which would slow down handling
	 * of Core Update API requests.
	 *
	 * @since 0.1.0
	 */
	function __construct() {
		global $wp_version;

		add_filter( 'http_response', array( $this, 'update_to_beta_or_rc_releases' ), 10, 3 );

		$beta_rc_download_url_pattern = 'https://wordpress.org/wordpress-%s-%s%s.zip';

		// extract the parts of the beta/RC version the site is running.
		$matches = array();
		preg_match( '@' . self::$beta_rc_version_regex . '@', $wp_version, $matches );

		// see the DocBlock of self::$beta_rc_version_regex for what those parts are.
		$version    = $matches[1];
		$beta_or_rc = $matches[3];
		$next       = intval( $matches[4] ) + 1;

		// construct the URLs for the next beta/RC release.
		if ( 'beta' === $beta_or_rc ) {
			// when running a beta, we check for both the next beta and the first RC.
			// check the RC1 package first.
			$this->next_package_urls[ "{$version}-RC1" ]         = sprintf( $beta_rc_download_url_pattern, $version, 'RC', 1 );
			$this->next_package_urls[ "{$version}-beta{$next}" ] = sprintf( $beta_rc_download_url_pattern, $version, 'beta', $next );
		}
		else {
			// when running an RC, we just check for the next RC.
			$this->next_package_urls[ "{$version}-RC{$next}" ]   = sprintf( $beta_rc_download_url_pattern, $version, 'RC', $next );
		}

		return;
	}

	/**
	 * Modify the repsonse from WP Core Update API requests to only show the
	 * next Beta/RC (and the latest stable release) package.
	 *
	 * @since 0.1.0
	 *
	 * @param array $response HTTP response.
	 * @param array $parsed_args HTTP request arguments.
	 * @param string $url The request URL.
	 * @return array
	 *
	 * @filter http_response
	 */
	function update_to_beta_or_rc_releases( $response, $parsed_args, $url ) {
		if ( is_wp_error( $response ) ||
				! preg_match( '@^https?://api.wordpress.org/core/version-check/@', $url ) ||
				200 !== wp_remote_retrieve_response_code( $response ) ) {
			// not a successful core update API request.
			// nothing to do, so bail.
			return $response;
		}

		// get the response body as an array.
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// loop through the next beta/RC download URLs and see if a package exists
		// at any of them.
		$found = false;
		foreach ( $this->next_package_urls as $version => $next_package_url ) {
			if ( ! $this->next_package_exists( $next_package_url ) ) {
				continue;
			}

			// the next beta/RC package was found.
			// Modify the development and autoupdate offers to use the URL
			// of that next beta/RC release.
			// @todo are there any cases where we'd need to modify other offers?
			//       I don't know the core update API well enough to know.
			foreach ( $body['offers'] as &$offer ) {
				switch ( $offer['response'] ) {
					case 'development':
					case 'autoupdate':
						$offer['download'] = $next_package_url;
						$offer['current'] = $offer['version'] = $version;

						foreach ( $offer['packages'] as $package => &$package_url ) {
							$package_url = 'full' === $package ? $next_package_url : false;
						}

						break;
				}
			}

			$found = true;

			break;
		}

		if ( ! $found ) {
			// the next beta/RC release package was not found.
			// remove the development and autoupdate offers.
			$body['offers'] = array_diff_key(
				$body['offers'],
				wp_list_filter( $body['offers'], array( 'response' => 'development' ) ),
				wp_list_filter( $body['offers'], array( 'response' => 'autoupdate' ) )
			);
		}

		// re-json encode the body.
		$response['body'] = json_encode( $body );

		return $response;
	}

	/**
	 * Check whether the next beta/RC package exists.
	 *
	 * Note: having this check enscapsulated in a method is for 2 reasons:
	 *
	 * 1. to avoid weird variable name for the `$respsonse` to this question,
	 *    since the update_to_beta_or_rc_releases() is passed the a variable named
	 *    `$response`.
	 * 2. The `wp_remote_head()` calls we do will *often* result in 404s (and
	 *    that is perfectly OK).  However, if the
	 *    {@link https://wordpress.org/plugins/query-monitor/ Query Minitor} plugin
	 *    is active, it will report those 404s are errors (by turning it's Admin Bar node
	 *    bright red) and that could be very disconcerting to even the type of user
	 *    who is likely the want the functionality of this plugin.  I have opened an
	 *    {@link https://github.com/johnbillion/query-monitor/issues/474 issue} to
	 *    add a new hook that would allow us to tell QM to ignore those 404s.  Having
	 *    this check in its own method will make it easier to add that hook when/if
	 *    it is supported by QM.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url URL of a beta/RC release package.
	 * @return bool
	 */
	protected function next_package_exists( $url ) {
		$response = wp_remote_head( $url );

		return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
	}
}

// instantiate ourself.
new Plugin();
