<?php
/**
 * Plugin Name: Studio Jurassic Tube
 * Description: Lets a WordPress Studio site answer on its Jurassic Tube domain (or another tunnel domain) instead of redirecting to localhost:<port>.
 *
 * Studio pins WP_HOME / WP_SITEURL to http://localhost:<port> before WordPress
 * boots, so a request for <sub>.jurassic.tube is redirected to
 * <sub>.jurassic.tube:<port>. When a request arrives on a tunnel host, this
 * plugin serves the site from that host instead. Requests on localhost (or a
 * Studio .local domain) are left alone.
 *
 * Install: symlink this file into the site's wp-content/mu-plugins/ folder.
 * See README.md.
 */

// Hosts ending in one of these suffixes are treated as the site's public URL.
$studio_jt_suffixes = array( '.jurassic.tube' );

$studio_jt_host = strtolower( $_SERVER['HTTP_HOST'] ?? '' );

$studio_jt_is_tunnel = false;
if ( preg_match( '/^[a-z0-9.-]+$/', $studio_jt_host ) ) {
	foreach ( $studio_jt_suffixes as $studio_jt_suffix ) {
		if ( substr( $studio_jt_host, -strlen( $studio_jt_suffix ) ) === $studio_jt_suffix ) {
			$studio_jt_is_tunnel = true;
			break;
		}
	}
}

$studio_jt_public = $studio_jt_is_tunnel ? 'https://' . $studio_jt_host : '';

// Jetpack ignores the option_home / option_siteurl filters below: it reads the
// raw WP_HOME / WP_SITEURL constants, so it reports http://localhost:<port> to
// WordPress.com, which refuses the connection (siteurl_private_ip). Give it the
// public URL through its own filters. The last tunnel URL is remembered so
// requests that arrive on localhost (WP-Cron, WP-CLI) report the same URL and
// do not trigger Jetpack's identity crisis / safe mode.
$studio_jt_jetpack_url = static function ( $url ) use ( $studio_jt_public ) {
	$public = $studio_jt_public ? $studio_jt_public : get_option( 'studio_jt_public_url' );
	return $public ? $public : $url;
};
add_filter( 'jetpack_sync_home_url', $studio_jt_jetpack_url, 20 );
add_filter( 'jetpack_sync_site_url', $studio_jt_jetpack_url, 20 );

// Studio sets WP_ENVIRONMENT_TYPE to "local", so Jetpack treats the site as
// local and switches to Offline Mode, where it cannot connect. Once the site
// has a public tunnel URL it is no longer local. A JETPACK_DEV_DEBUG or
// WP_LOCAL_DEV constant in wp-config.php still takes precedence over this.
add_filter(
	'jetpack_is_local_site',
	static function ( $is_local ) use ( $studio_jt_public ) {
		return ( $studio_jt_public || get_option( 'studio_jt_public_url' ) ) ? false : $is_local;
	},
	20
);

if ( $studio_jt_is_tunnel ) {
	// The tunnel and the local Caddy proxy both terminate HTTPS.
	$_SERVER['HTTPS'] = 'on';

	add_action(
		'init',
		static function () use ( $studio_jt_public ) {
			if ( get_option( 'studio_jt_public_url' ) !== $studio_jt_public ) {
				update_option( 'studio_jt_public_url', $studio_jt_public, true );
			}
		}
	);

	// Priority 20 runs after core's WP_HOME / WP_SITEURL constant filters.
	$studio_jt_home = static function () use ( $studio_jt_public ) {
		return $studio_jt_public;
	};
	add_filter( 'option_home', $studio_jt_home, 20 );
	add_filter( 'option_siteurl', $studio_jt_home, 20 );

	// WP_CONTENT_URL and WP_PLUGIN_URL were already computed from the local URL
	// before mu-plugins load, so rewrite the local origin in asset URLs.
	$studio_jt_local = array();
	if ( defined( 'WP_CONTENT_URL' ) ) {
		$studio_jt_parts = parse_url( WP_CONTENT_URL );
		if ( ! empty( $studio_jt_parts['host'] ) ) {
			$studio_jt_authority = $studio_jt_parts['host'];
			if ( ! empty( $studio_jt_parts['port'] ) ) {
				$studio_jt_authority .= ':' . $studio_jt_parts['port'];
			}
			$studio_jt_local = array( 'https://' . $studio_jt_authority, 'http://' . $studio_jt_authority );
		}
	}

	if ( $studio_jt_local && ! in_array( $studio_jt_public, $studio_jt_local, true ) ) {
		$studio_jt_swap = static function ( $url ) use ( $studio_jt_local, $studio_jt_public ) {
			return is_string( $url ) ? str_replace( $studio_jt_local, $studio_jt_public, $url ) : $url;
		};

		add_filter( 'content_url', $studio_jt_swap );
		add_filter( 'plugins_url', $studio_jt_swap );
		add_filter( 'theme_root_uri', $studio_jt_swap );
		add_filter( 'script_loader_src', $studio_jt_swap );
		add_filter( 'style_loader_src', $studio_jt_swap );
		add_filter(
			'upload_dir',
			static function ( $dir ) use ( $studio_jt_swap ) {
				$dir['url']     = $studio_jt_swap( $dir['url'] );
				$dir['baseurl'] = $studio_jt_swap( $dir['baseurl'] );
				return $dir;
			}
		);
	}
}
