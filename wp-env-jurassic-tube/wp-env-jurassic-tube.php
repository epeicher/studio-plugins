<?php
/**
 * Local dev only: let a wp-env site answer on its Jurassic Tube host.
 *
 * wp-env defines WP_HOME / WP_SITEURL as http://localhost:<port>. A request
 * arriving through the tunnel (https://<name>.jurassic.tube) therefore gets
 * a canonical redirect that keeps the tunnel host but adds the local port
 * (https://<name>.jurassic.tube:8889/), which is unreachable.
 *
 * For requests on a *.jurassic.tube host this rewrites the home and site
 * URLs onto the request's own origin. Requests on localhost and wp-cli runs
 * return early and see the stock install.
 *
 * Mounted at wp-content/mu-plugins/ through a mapping in .wp-env.override.json;
 * see README.md.
 */

$jt_host = strtolower( $_SERVER['HTTP_HOST'] ?? '' );

if ( ! preg_match( '/^[a-z0-9-]+\.jurassic\.tube$/', $jt_host ) ) {
	unset( $jt_host );
	return;
}

// wp-config.php already turns X-Forwarded-Proto into $_SERVER['HTTPS'].
$jt_origin = ( is_ssl() ? 'https' : 'http' ) . '://' . $jt_host;

$jt_rewrite = static function ( $url ) use ( $jt_origin ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return $url;
	}
	return preg_replace( '#^https?://localhost(:\d+)?(?=/|$)#i', $jt_origin, $url );
};

// Priority 20: after core's _config_wp_home / _config_wp_siteurl apply the constants.
add_filter( 'option_home', $jt_rewrite, 20 );
add_filter( 'option_siteurl', $jt_rewrite, 20 );

// WP_CONTENT_URL / WP_PLUGIN_URL are baked from the localhost site URL before
// mu-plugins load, so URLs built from those constants need rewriting too.
foreach ( array( 'plugins_url', 'content_url', 'script_loader_src', 'style_loader_src' ) as $jt_hook ) {
	add_filter( $jt_hook, $jt_rewrite, 20 );
}

add_filter(
	'upload_dir',
	static function ( $dirs ) use ( $jt_rewrite ) {
		if ( is_array( $dirs ) ) {
			foreach ( array( 'url', 'baseurl' ) as $key ) {
				if ( isset( $dirs[ $key ] ) ) {
					$dirs[ $key ] = $jt_rewrite( $dirs[ $key ] );
				}
			}
		}
		return $dirs;
	},
	20
);

// Absolute localhost links saved inside post content.
add_filter(
	'the_content',
	static function ( $content ) use ( $jt_origin ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}
		return preg_replace( '#https?://localhost:\d+(?=/)#i', $jt_origin, $content );
	},
	5
);

unset( $jt_host, $jt_origin, $jt_rewrite, $jt_hook );
