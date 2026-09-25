<?php
/**
 * Plugin Name: Travel Gemz Hardening
 * Description: Baseline security hardening -- response headers, XML-RPC pingback lockdown, version fingerprint removal, and closing the public user/author info leak on this single-author site.
 * Version: 1.0.0
 * Author: Travel Gemz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TravelGemz_Hardening {

	public function __construct() {
		add_action( 'send_headers', array( $this, 'security_headers' ) );
		add_filter( 'xmlrpc_methods', array( $this, 'disable_pingback' ) );
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'rest_endpoints', array( $this, 'restrict_users_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'block_author_archive' ) );
	}

	/**
	 * Baseline response headers. Conservative choices only -- no CSP here,
	 * since a hand-authored CSP on a theme we don't fully control risks
	 * silently breaking inline styles/scripts the theme or Hostinger's
	 * own plugins rely on. Framing/MIME/referrer protections are safe
	 * regardless of what the page contains.
	 */
	public function security_headers() {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
	}

	/**
	 * Pingback abuse (XML-RPC multicall reflection/amplification) is the
	 * common real-world attack here. Leaving the rest of XML-RPC alone
	 * since disabling it outright can break legitimate integrations
	 * (e.g. Jetpack) some sites rely on -- that's a call for a human,
	 * not something to silently switch off site-wide.
	 */
	public function disable_pingback( $methods ) {
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * This site has one author and no author-archive feature in actual
	 * use, so the public /wp/v2/users listing (which leaks the account's
	 * email-derived slug even after the display name is fixed) serves no
	 * purpose. Restrict it to authenticated requests only; logged-in
	 * admin use (e.g. the block editor's author picker) is unaffected.
	 */
	public function restrict_users_endpoint( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			if ( isset( $endpoints['/wp/v2/users'] ) ) {
				unset( $endpoints['/wp/v2/users'] );
			}
			if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
				unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
			}
		}
		return $endpoints;
	}

	/** Same reasoning as above, for the public-facing author archive page. */
	public function block_author_archive() {
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
}

new TravelGemz_Hardening();
