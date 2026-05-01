<?php

define( 'ABSPATH', __DIR__ );

$pmm_test_options    = array();
$pmm_test_current_id = 0;

function add_action() {}
function add_filter() {}
function apply_filters( $hook_name, $value ) { return $value; }
function current_user_can() { return true; }
function wp_die( $message = '' ) { throw new RuntimeException( (string) $message ); }
function check_admin_referer() {}
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value ); }
function sanitize_title( $value ) { return sanitize_key( str_replace( ' ', '-', (string) $value ) ); }
function esc_url_raw( $value ) { return trim( (string) $value ); }
function esc_url( $value ) { return trim( (string) $value ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html__( $value ) { return $value; }
function __( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function get_option( $name, $default = false ) {
	global $pmm_test_options;

	return array_key_exists( $name, $pmm_test_options ) ? $pmm_test_options[ $name ] : $default;
}
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function get_queried_object_id() {
	global $pmm_test_current_id;

	return $pmm_test_current_id;
}

require dirname( __DIR__ ) . '/primary-menu-manager.php';

function pmm_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $message . PHP_EOL );
		fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
		fwrite( STDERR, 'Actual: ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}
}

global $pmm_test_options, $pmm_test_current_id;

$pmm_test_current_id       = 42;
$pmm_test_options[ PMM_OPTION ] = array(
	array(
		'enabled'        => true,
		'title'          => 'Landing Blanca City',
		'menu_locations' => array( 'primary', 'slideout' ),
		'priority'       => 1,
		'post_ids'       => array( 42 ),
		'path_contains'  => '',
		'logo_enabled'   => true,
		'logo_url'       => '/uploads/blanca-logo.svg',
		'logo_link_url'  => '/blanca-city/',
		'items'          => array(
			array(
				'label' => 'Tổng quan',
				'url'   => '#tong-quan',
			),
		),
	),
);

$logo_config = pmm_get_current_logo_config();

pmm_assert_same( '/uploads/blanca-logo.svg', $logo_config['logo_url'], 'Matching rule should provide the custom logo URL.' );
pmm_assert_same( '/blanca-city/', $logo_config['logo_link_url'], 'Matching rule should provide the custom logo link.' );

$filtered_logo = pmm_filter_custom_logo_html(
	'<a href="/"><img src="/uploads/default-logo.png" srcset="/uploads/default-logo.png 1x" sizes="100vw" alt="Site"></a>'
);

pmm_assert_same(
	'<a href="/blanca-city/"><img src="/uploads/blanca-logo.svg" alt="Site"></a>',
	$filtered_logo,
	'Custom logo HTML should receive the rule logo URL and logo link.'
);

echo "pmm-logo-rule-test passed\n";
