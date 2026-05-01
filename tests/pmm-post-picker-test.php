<?php

define( 'ABSPATH', __DIR__ );

$pmm_test_posts = array(
	2258 => array(
		'ID'          => 2258,
		'post_title'  => 'Menu Izumi',
		'post_type'   => 'page',
		'post_status' => 'publish',
	),
	3010 => array(
		'ID'          => 3010,
		'post_title'  => 'Tin mở bán',
		'post_type'   => 'post',
		'post_status' => 'draft',
	),
);

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
function get_option( $name, $default = false ) { return $default; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function get_queried_object_id() { return 0; }
function get_post( $post_id ) {
	global $pmm_test_posts;

	return isset( $pmm_test_posts[ $post_id ] ) ? (object) $pmm_test_posts[ $post_id ] : null;
}
function get_permalink( $post ) { return '/post-' . ( is_object( $post ) ? (int) $post->ID : (int) $post ) . '/'; }
function get_the_title( $post ) { return $post->post_title; }
function get_post_type_object( $post_type ) {
	$labels = array(
		'page' => 'Page',
		'post' => 'Post',
	);

	return isset( $labels[ $post_type ] ) ? (object) array( 'labels' => (object) array( 'singular_name' => $labels[ $post_type ] ) ) : null;
}
function get_post_status_object( $post_status ) {
	$labels = array(
		'publish' => 'Published',
		'draft'   => 'Draft',
	);

	return isset( $labels[ $post_status ] ) ? (object) array( 'label' => $labels[ $post_status ] ) : null;
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

$items = pmm_get_post_picker_items( array( 2258, 9999, 3010 ) );

pmm_assert_same( 2, count( $items ), 'Post picker should skip missing IDs.' );
pmm_assert_same( 2258, $items[0]['id'], 'Post picker should preserve the saved ID.' );
pmm_assert_same( 'Menu Izumi', $items[0]['title'], 'Post picker should expose the post title.' );
pmm_assert_same( '/post-2258/', $items[0]['permalink'], 'Post picker should expose the permalink for logo link defaults.' );
pmm_assert_same( 'Page #2258 - Published', $items[0]['meta'], 'Post picker should expose post type, ID, and status.' );
pmm_assert_same( 'Post #3010 - Draft', $items[1]['meta'], 'Post picker should format post metadata for other post types.' );

echo "pmm-post-picker-test passed\n";
