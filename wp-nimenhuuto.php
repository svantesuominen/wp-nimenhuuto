<?php
/**
 * Plugin Name: Nimenhuuto Next Session
 * Description: Display the next upcoming session from Nimenhuuto accounts via Gutenberg block or shortcode.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_NIMENHUUTO_VERSION', '1.0.0' );
define( 'WP_NIMENHUUTO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_NIMENHUUTO_URL', plugin_dir_url( __FILE__ ) );

require_once WP_NIMENHUUTO_DIR . 'includes/class-nimenhuuto-ical-parser.php';
require_once WP_NIMENHUUTO_DIR . 'includes/class-nimenhuuto-fetcher.php';
require_once WP_NIMENHUUTO_DIR . 'includes/class-nimenhuuto-settings.php';

// ---------------------------------------------------------------------------
// Block registration
// ---------------------------------------------------------------------------

add_action( 'init', function () {
	register_block_type( 'wp-nimenhuuto/next-session', [
		'render_callback' => 'wp_nimenhuuto_render_block',
		'attributes'      => [
			'accountId' => [
				'type'    => 'string',
				'default' => '',
			],
		],
	] );
} );

add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script(
		'wp-nimenhuuto-editor',
		WP_NIMENHUUTO_URL . 'assets/js/block-editor.js',
		[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ],
		WP_NIMENHUUTO_VERSION,
		true
	);

	wp_localize_script( 'wp-nimenhuuto-editor', 'wpNimenhuutoData', [
		'accounts' => array_values( get_option( 'wp_nimenhuuto_accounts', [] ) ),
	] );
} );

// ---------------------------------------------------------------------------
// Block render callback
// ---------------------------------------------------------------------------

function wp_nimenhuuto_render_block( array $attributes ): string {
	return wp_nimenhuuto_get_output( $attributes['accountId'] ?? '' );
}

// ---------------------------------------------------------------------------
// Shortcode [nimenhuuto_next_session account="id"]
// ---------------------------------------------------------------------------

add_shortcode( 'nimenhuuto_next_session', function ( $atts ): string {
	$atts = shortcode_atts( [ 'account' => '' ], $atts, 'nimenhuuto_next_session' );
	return wp_nimenhuuto_get_output( $atts['account'] );
} );

// ---------------------------------------------------------------------------
// Core output logic
// ---------------------------------------------------------------------------

function wp_nimenhuuto_get_output( string $account_id ): string {
	$accounts = get_option( 'wp_nimenhuuto_accounts', [] );

	if ( empty( $accounts ) ) {
		return '';
	}

	if ( ! empty( $account_id ) ) {
		$accounts = array_filter( $accounts, fn( $a ) => $a['id'] === $account_id );
	}

	$fetcher = new Nimenhuuto_Fetcher();
	$output  = '';

	foreach ( $accounts as $account ) {
		$event = $fetcher->get_next_event( $account );
		if ( $event ) {
			$output .= wp_nimenhuuto_format_event( $account, $event );
		}
	}

	return $output;
}

function wp_nimenhuuto_format_event( array $account, array $event ): string {
	$start = $event['start'] ?? null;
	$end   = $event['end'] ?? null;

	if ( ! $start instanceof DateTimeInterface ) {
		return '';
	}

	$day_names = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
	$day_name  = $day_names[ (int) $start->format( 'w' ) ];
	$date      = $day_name . ' ' . $start->format( 'j.n.' );
	$time      = $start->format( 'H:i' );

	if ( $end instanceof DateTimeInterface ) {
		$time .= ' – ' . $end->format( 'H:i' );
	}

	$location = ! empty( $event['location'] ) ? ' ' . esc_html( $event['location'] ) : '';

	$text = sprintf(
		'The next %s session is %s %s at %s%s.',
		esc_html( $account['label'] ),
		esc_html( $event['title'] ),
		esc_html( $date ),
		esc_html( $time ),
		$location
	);

	return '<p class="wp-nimenhuuto-next-session">' . $text . '</p>' . "\n";
}

// ---------------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	( new Nimenhuuto_Settings() )->add_menu();
} );
