<?php

define( 'ABSPATH', __DIR__ );

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

require dirname( __DIR__ ) . '/primary-menu-manager.php';

function pmm_assert_contains( $needle, $haystack, $message ) {
	if ( false === strpos( $haystack, $needle ) ) {
		fwrite( STDERR, $message . PHP_EOL );
		fwrite( STDERR, 'Expected to find: ' . $needle . PHP_EOL );
		fwrite( STDERR, 'Actual: ' . $haystack . PHP_EOL );
		exit( 1 );
	}
}

function pmm_assert_not_contains( $needle, $haystack, $message ) {
	if ( false !== strpos( $haystack, $needle ) ) {
		fwrite( STDERR, $message . PHP_EOL );
		fwrite( STDERR, 'Did not expect to find: ' . $needle . PHP_EOL );
		fwrite( STDERR, 'Actual: ' . $haystack . PHP_EOL );
		exit( 1 );
	}
}

$enabled_classes = pmm_get_rule_card_classes( array( 'enabled' => true ) );
$disabled_classes = pmm_get_rule_card_classes( array( 'enabled' => false ) );
$plugin_source = file_get_contents( dirname( __DIR__ ) . '/primary-menu-manager.php' );

pmm_assert_contains( 'is-collapsed', $enabled_classes, 'Enabled rule cards should start collapsed by default.' );
pmm_assert_not_contains( 'is-disabled', $enabled_classes, 'Enabled rule cards should not include the disabled class.' );
pmm_assert_contains( 'is-collapsed', $disabled_classes, 'Disabled rule cards should also start collapsed by default.' );
pmm_assert_contains( 'is-disabled', $disabled_classes, 'Disabled rule cards should include the disabled class.' );
pmm_assert_contains( 'grid-template-columns: minmax(260px, 0.82fr) minmax(0, 1.18fr);', $plugin_source, 'Rule layout should let the right column shrink instead of clipping.' );
pmm_assert_contains( '@media (max-width: 1280px)', $plugin_source, 'Admin UI should collapse before medium WordPress admin screens clip the right column.' );
pmm_assert_contains( 'grid-template-columns: 22px minmax(120px, 0.9fr) minmax(140px, 1.25fr) minmax(100px, 0.75fr) 76px minmax(76px, 0.55fr) 32px;', $plugin_source, 'Menu item rows should use shrinkable columns.' );

echo "pmm-admin-collapse-test passed\n";
