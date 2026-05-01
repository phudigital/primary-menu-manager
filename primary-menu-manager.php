<?php
/**
 * Plugin Name: Primary Menu Manager
 * Description: Manage conditional primary menu items and header logo/link for landing pages without changing the theme header layout or mobile behavior.
 * Version: 1.1.0
 * Author: PDL Solutions (Phú Digital)
 * Author URI: https://pdl.vn
 * Plugin URI: https://pdl.vn
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: primary-menu-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PMM_VERSION', '1.1.0' );
define( 'PMM_OPTION', 'pmm_rules' );
define( 'PMM_AUTHOR_NAME', 'PDL Solutions (Phú Digital)' );
define( 'PMM_AUTHOR_URL', 'https://pdl.vn' );

add_action( 'admin_menu', 'pmm_register_admin_page' );
add_action( 'admin_init', 'pmm_handle_save' );
add_filter( 'wp_nav_menu_objects', 'pmm_filter_menu_objects', 20, 2 );
add_filter( 'get_custom_logo', 'pmm_filter_custom_logo_html', 20, 2 );
add_filter( 'generate_logo', 'pmm_filter_theme_logo_url', 20 );
add_filter( 'generate_navigation_logo', 'pmm_filter_theme_logo_url', 20 );
add_filter( 'generate_mobile_header_logo', 'pmm_filter_theme_logo_url', 20 );
add_filter( 'generate_logo_href', 'pmm_filter_theme_logo_link', 20 );

function pmm_register_admin_page() {
	add_options_page(
		'Primary Menu Manager',
		'Primary Menu Manager',
		'manage_options',
		'primary-menu-manager',
		'pmm_render_admin_page'
	);
}

function pmm_get_rules() {
	$rules = get_option( PMM_OPTION, array() );

	$rules = is_array( $rules ) ? $rules : array();

	/**
	 * Filters all stored Primary Menu Manager rules before runtime matching.
	 *
	 * This is the main extension point for vibe-code additions that need to
	 * inject generated rules, migrate legacy data, or sync rule data from a CMS.
	 */
	return apply_filters( 'pmm_rules', $rules );
}

function pmm_handle_save() {
	if ( ! isset( $_POST['pmm_save'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage this menu.', 'primary-menu-manager' ) );
	}

	check_admin_referer( 'pmm_save_rules' );

	$raw_rules = isset( $_POST['pmm_rules'] ) && is_array( $_POST['pmm_rules'] ) ? wp_unslash( $_POST['pmm_rules'] ) : array();
	$rules     = array();

	foreach ( $raw_rules as $raw_rule ) {
		$rule = pmm_sanitize_rule( $raw_rule );

		if ( pmm_rule_has_content( $rule ) ) {
			$rules[] = $rule;
		}
	}

	update_option( PMM_OPTION, $rules, false );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'primary-menu-manager',
				'updated' => '1',
			),
			admin_url( 'options-general.php' )
		)
	);
	exit;
}

function pmm_sanitize_rule( $raw_rule ) {
	$rule = array(
		'enabled'        => ! empty( $raw_rule['enabled'] ),
		'title'          => isset( $raw_rule['title'] ) ? sanitize_text_field( $raw_rule['title'] ) : '',
		'menu_locations' => isset( $raw_rule['menu_locations'] ) ? pmm_sanitize_key_list( $raw_rule['menu_locations'] ) : pmm_sanitize_legacy_menu_locations( $raw_rule ),
		'priority'       => isset( $raw_rule['priority'] ) ? absint( $raw_rule['priority'] ) : 10,
		'post_ids'       => isset( $raw_rule['post_ids'] ) ? pmm_sanitize_id_list( $raw_rule['post_ids'] ) : array(),
		'path_contains'  => isset( $raw_rule['path_contains'] ) ? sanitize_text_field( $raw_rule['path_contains'] ) : '',
		'logo_enabled'   => ! empty( $raw_rule['logo_enabled'] ),
		'logo_url'       => isset( $raw_rule['logo_url'] ) ? pmm_sanitize_logo_url( $raw_rule['logo_url'] ) : '',
		'logo_link_url'  => isset( $raw_rule['logo_link_url'] ) ? pmm_sanitize_menu_item_url( $raw_rule['logo_link_url'] ) : '',
		'items'          => array(),
	);

	if ( isset( $raw_rule['items'] ) && is_array( $raw_rule['items'] ) ) {
		foreach ( $raw_rule['items'] as $raw_item ) {
			$label = isset( $raw_item['label'] ) ? sanitize_text_field( $raw_item['label'] ) : '';
			$url   = isset( $raw_item['url'] ) ? pmm_sanitize_menu_item_url( $raw_item['url'] ) : '';

			if ( '' === $label || '' === $url ) {
				continue;
			}

			$rule['items'][] = array(
				'label'  => $label,
				'url'    => $url,
				'target' => isset( $raw_item['target'] ) && '_blank' === $raw_item['target'] ? '_blank' : '',
				'rel'    => isset( $raw_item['rel'] ) ? sanitize_text_field( $raw_item['rel'] ) : '',
				'class'  => isset( $raw_item['class'] ) ? sanitize_html_class( $raw_item['class'] ) : '',
			);
		}
	}

	return apply_filters( 'pmm_sanitized_rule', $rule, $raw_rule );
}

function pmm_rule_has_content( $rule ) {
	return ! empty( $rule['title'] ) || ! empty( $rule['items'] ) || ! empty( $rule['logo_url'] ) || ! empty( $rule['logo_link_url'] );
}

function pmm_sanitize_id_list( $value ) {
	$value = is_array( $value ) ? implode( ',', $value ) : (string) $value;
	$ids   = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );

	return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

function pmm_sanitize_key_list( $value ) {
	$value = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );

	return array_values( array_unique( array_filter( array_map( 'sanitize_key', $value ) ) ) );
}

function pmm_sanitize_legacy_menu_locations( $raw_rule ) {
	$location = isset( $raw_rule['menu_location'] ) ? sanitize_key( $raw_rule['menu_location'] ) : 'primary';

	return $location ? array( $location ) : array();
}

function pmm_sanitize_menu_item_url( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '';
	}

	if ( '#' === $url[0] ) {
		return sanitize_text_field( $url );
	}

	return esc_url_raw( $url );
}

function pmm_sanitize_logo_url( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url || '#' === $url[0] ) {
		return '';
	}

	return esc_url_raw( $url );
}

function pmm_filter_menu_objects( $items, $args ) {
	$location = isset( $args->theme_location ) ? (string) $args->theme_location : '';
	$rule     = pmm_find_matching_rule( $location );

	if ( ! $rule ) {
		return $items;
	}

	$custom_items = array();
	$menu_id      = isset( $args->menu ) && is_object( $args->menu ) && isset( $args->menu->term_id ) ? (int) $args->menu->term_id : 0;

	foreach ( $rule['items'] as $index => $item ) {
		$custom_items[] = pmm_build_menu_item( $item, $index, $menu_id );
	}

	return apply_filters( 'pmm_filtered_menu_items', $custom_items, $items, $rule, $args );
}

function pmm_find_matching_rule( $location ) {
	if ( is_admin() || wp_doing_ajax() || ! $location ) {
		return false;
	}

	$rules = pmm_get_rules();
	usort(
		$rules,
		static function ( $a, $b ) {
			return (int) ( $a['priority'] ?? 10 ) <=> (int) ( $b['priority'] ?? 10 );
		}
	);

	foreach ( $rules as $rule ) {
		$target_locations = pmm_get_rule_menu_locations( $rule );

		if ( empty( $rule['enabled'] ) || empty( $rule['items'] ) || ! in_array( $location, $target_locations, true ) ) {
			continue;
		}

		if ( pmm_rule_matches_current_request( $rule ) ) {
			return apply_filters( 'pmm_matching_menu_rule', $rule, $location );
		}
	}

	return apply_filters( 'pmm_matching_menu_rule', false, $location );
}

function pmm_get_rule_menu_locations( $rule ) {
	if ( ! empty( $rule['menu_locations'] ) && is_array( $rule['menu_locations'] ) ) {
		return pmm_sanitize_key_list( $rule['menu_locations'] );
	}

	if ( ! empty( $rule['menu_location'] ) ) {
		return array( sanitize_key( $rule['menu_location'] ) );
	}

	return array( 'primary' );
}

function pmm_rule_matches_current_request( $rule ) {
	$current_id = get_queried_object_id();
	$matches    = false;

	if ( $current_id && ! empty( $rule['post_ids'] ) && in_array( $current_id, $rule['post_ids'], true ) ) {
		$matches = true;
	}

	if ( ! $matches && ! empty( $rule['path_contains'] ) ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( $request_uri && false !== strpos( $request_uri, $rule['path_contains'] ) ) {
			$matches = true;
		}
	}

	return (bool) apply_filters( 'pmm_rule_matches_current_request', $matches, $rule, $current_id );
}

function pmm_get_current_logo_config() {
	if ( is_admin() || wp_doing_ajax() ) {
		return false;
	}

	$rules = pmm_get_rules();
	usort(
		$rules,
		static function ( $a, $b ) {
			return (int) ( $a['priority'] ?? 10 ) <=> (int) ( $b['priority'] ?? 10 );
		}
	);

	foreach ( $rules as $rule ) {
		if ( empty( $rule['enabled'] ) || empty( $rule['logo_enabled'] ) || ! pmm_rule_matches_current_request( $rule ) ) {
			continue;
		}

		$config = array(
			'logo_url'      => ! empty( $rule['logo_url'] ) ? pmm_sanitize_logo_url( $rule['logo_url'] ) : '',
			'logo_link_url' => ! empty( $rule['logo_link_url'] ) ? pmm_sanitize_menu_item_url( $rule['logo_link_url'] ) : '',
		);

		if ( $config['logo_url'] || $config['logo_link_url'] ) {
			return apply_filters( 'pmm_current_logo_config', $config, $rule );
		}
	}

	return apply_filters( 'pmm_current_logo_config', false, false );
}

function pmm_filter_custom_logo_html( $html, $blog_id = 0 ) {
	$config = pmm_get_current_logo_config();

	if ( ! $config ) {
		return $html;
	}

	if ( ! empty( $config['logo_link_url'] ) ) {
		$html = pmm_replace_first_tag_attribute( $html, 'a', 'href', esc_url( $config['logo_link_url'] ) );
	}

	if ( ! empty( $config['logo_url'] ) ) {
		$html = pmm_replace_first_tag_attribute( $html, 'img', 'src', esc_url( $config['logo_url'] ) );
		$html = preg_replace( '/\s(?:srcset|sizes)=(["\']).*?\1/i', '', $html );
	}

	return $html;
}

function pmm_replace_first_tag_attribute( $html, $tag, $attribute, $value ) {
	$pattern = '/<' . preg_quote( $tag, '/' ) . '\b[^>]*>/i';

	return preg_replace_callback(
		$pattern,
		static function ( $matches ) use ( $attribute, $value ) {
			$tag_html          = $matches[0];
			$attribute_pattern = '/\s' . preg_quote( $attribute, '/' ) . '=(["\']).*?\1/i';
			$attribute_html    = ' ' . $attribute . '="' . esc_attr( $value ) . '"';

			if ( preg_match( $attribute_pattern, $tag_html ) ) {
				return preg_replace_callback(
					$attribute_pattern,
					static function () use ( $attribute_html ) {
						return $attribute_html;
					},
					$tag_html,
					1
				);
			}

			return preg_replace( '/>$/', $attribute_html . '>', $tag_html, 1 );
		},
		$html,
		1
	);
}

function pmm_filter_theme_logo_url( $url ) {
	$config = pmm_get_current_logo_config();

	return $config && ! empty( $config['logo_url'] ) ? $config['logo_url'] : $url;
}

function pmm_filter_theme_logo_link( $url ) {
	$config = pmm_get_current_logo_config();

	return $config && ! empty( $config['logo_link_url'] ) ? $config['logo_link_url'] : $url;
}

function pmm_build_menu_item( $item, $index, $menu_id ) {
	$item_id = -1000 - $index;
	$title   = $item['label'];
	$url     = $item['url'];

	$menu_item = (object) array(
		'ID'               => $item_id,
		'db_id'            => $item_id,
		'menu_item_parent' => 0,
		'object_id'        => $item_id,
		'object'           => 'custom',
		'type'             => 'custom',
		'type_label'       => __( 'Custom Link', 'primary-menu-manager' ),
		'title'            => $title,
		'url'              => $url,
		'target'           => $item['target'],
		'attr_title'       => '',
		'description'      => '',
		'classes'          => array_filter( array( 'menu-item', 'menu-item-type-custom', 'menu-item-object-custom', $item['class'] ) ),
		'xfn'              => $item['rel'],
		'current'          => pmm_url_matches_current_request( $url ),
		'current_item_ancestor' => false,
		'current_item_parent'   => false,
		'menu_order'       => $index + 1,
		'status'           => 'publish',
		'post_status'      => 'publish',
		'post_parent'      => 0,
		'post_type'        => 'nav_menu_item',
		'post_title'       => $title,
		'post_name'        => sanitize_title( $title ),
		'filter'           => 'raw',
		'guid'             => $url,
		'term_id'          => $menu_id,
	);

	if ( $menu_item->current ) {
		$menu_item->classes[] = 'current-menu-item';
	}

	return apply_filters( 'pmm_menu_item_object', $menu_item, $item, $index, $menu_id );
}

function pmm_url_matches_current_request( $url ) {
	$current_url = home_url( add_query_arg( null, null ) );

	return untrailingslashit( $current_url ) === untrailingslashit( $url );
}

function pmm_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$rules          = pmm_get_rules();
	$menu_locations = get_registered_nav_menus();

	if ( empty( $rules ) ) {
		$rules[] = pmm_blank_rule();
	}
	?>
	<div class="wrap pmm-wrap">
		<h1>Primary Menu Manager</h1>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Đã lưu cấu hình primary menu.</p></div>
		<?php endif; ?>

		<p class="description">Plugin thay menu items và có thể đổi logo/link logo theo rule. Header layout, hiệu ứng và mobile menu vẫn do theme xử lý. Phát triển bởi <a href="<?php echo esc_url( PMM_AUTHOR_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( PMM_AUTHOR_NAME ); ?></a>.</p>

		<form method="post" action="">
			<?php wp_nonce_field( 'pmm_save_rules' ); ?>

			<div id="pmm-rules">
				<?php foreach ( $rules as $rule_index => $rule ) : ?>
					<?php pmm_render_rule_card( $rule, $rule_index, $menu_locations ); ?>
				<?php endforeach; ?>
			</div>

			<p>
				<button type="button" class="button" id="pmm-add-rule">Thêm menu rule</button>
				<button type="submit" class="button button-primary" name="pmm_save" value="1">Lưu cấu hình</button>
			</p>
		</form>

		<template id="pmm-rule-template">
			<?php pmm_render_rule_card( pmm_blank_rule(), '__RULE_INDEX__', $menu_locations ); ?>
		</template>
	</div>
	<?php
	pmm_render_admin_assets();
}

function pmm_blank_rule() {
	return array(
		'enabled'        => true,
		'title'          => '',
		'menu_locations' => array( 'primary' ),
		'priority'       => 10,
		'post_ids'       => array(),
		'path_contains'  => '',
		'logo_enabled'   => false,
		'logo_url'       => '',
		'logo_link_url'  => '',
		'items'          => array(
			array(
				'label'  => '',
				'url'    => '',
				'target' => '',
				'rel'    => '',
				'class'  => '',
			),
		),
	);
}

function pmm_render_rule_card( $rule, $rule_index, $menu_locations ) {
	$rule = wp_parse_args( $rule, pmm_blank_rule() );
	$selected_locations = pmm_get_rule_menu_locations( $rule );
	$rule_title         = $rule['title'] ? $rule['title'] : 'Menu rule';
	?>
	<section class="pmm-card" data-rule>
		<button type="button" class="pmm-card__toggle" data-toggle-rule aria-expanded="true">
			<span class="pmm-card__title" data-rule-title><?php echo esc_html( $rule_title ); ?></span>
			<span class="pmm-card__meta"><?php echo ! empty( $rule['enabled'] ) ? 'Đang bật' : 'Đang tắt'; ?></span>
			<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
		</button>

		<div class="pmm-card__body" data-rule-body>
			<div class="pmm-card__actions">
				<label>
					<input type="checkbox" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>>
					Bật rule này
				</label>
				<button type="button" class="button-link-delete" data-remove-rule>Xóa rule</button>
			</div>

			<div class="pmm-grid">
				<label>
					<span>Tên rule</span>
					<input type="text" class="regular-text" data-title-input name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][title]" value="<?php echo esc_attr( $rule['title'] ); ?>" placeholder="VD: Menu Landing Blanca City">
				</label>
				<label>
					<span>Menu location</span>
					<select multiple name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][menu_locations][]">
						<?php foreach ( $menu_locations as $location => $label ) : ?>
							<option value="<?php echo esc_attr( $location ); ?>" <?php selected( in_array( $location, $selected_locations, true ) ); ?>><?php echo esc_html( $label . ' (' . $location . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span>Ưu tiên</span>
					<input type="number" min="1" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][priority]" value="<?php echo esc_attr( $rule['priority'] ); ?>">
				</label>
			</div>

			<h3>Điều kiện áp dụng</h3>
			<div class="pmm-grid">
				<label>
					<span>ID page/post cụ thể</span>
					<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][post_ids]" value="<?php echo esc_attr( implode( ',', (array) $rule['post_ids'] ) ); ?>" placeholder="27,42,108">
				</label>
				<label>
					<span>URL path chứa</span>
					<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][path_contains]" value="<?php echo esc_attr( $rule['path_contains'] ); ?>" placeholder="/landing/">
				</label>
			</div>

			<h3>Logo header</h3>
			<div class="pmm-grid">
				<label class="pmm-check">
					<input type="checkbox" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][logo_enabled]" value="1" <?php checked( ! empty( $rule['logo_enabled'] ) ); ?>>
					Đổi logo/link logo cho rule này
				</label>
				<label>
					<span>Logo URL</span>
					<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][logo_url]" value="<?php echo esc_attr( $rule['logo_url'] ); ?>" placeholder="https://.../logo.svg hoặc /uploads/logo.png">
				</label>
				<label>
					<span>Link logo</span>
					<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][logo_link_url]" value="<?php echo esc_attr( $rule['logo_link_url'] ); ?>" placeholder="/landing-page/">
				</label>
			</div>

			<h3>Menu items</h3>
			<div class="pmm-items" data-items>
				<?php foreach ( (array) $rule['items'] as $item_index => $item ) : ?>
					<?php pmm_render_menu_item_row( $rule_index, $item_index, $item ); ?>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="button" data-add-item>Thêm item</button></p>
		</div>
	</section>
	<?php
}

function pmm_render_menu_item_row( $rule_index, $item_index, $item ) {
	$item = wp_parse_args(
		$item,
		array(
			'label'  => '',
			'url'    => '',
			'target' => '',
			'rel'    => '',
			'class'  => '',
		)
	);
	?>
	<div class="pmm-item" data-item>
		<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][label]" value="<?php echo esc_attr( $item['label'] ); ?>" placeholder="Tên item">
		<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][url]" value="<?php echo esc_attr( $item['url'] ); ?>" placeholder="https://... hoặc /duong-dan/ hoặc #id-section">
		<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][class]" value="<?php echo esc_attr( $item['class'] ); ?>" placeholder="CSS class">
		<label>
			<input type="checkbox" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][target]" value="_blank" <?php checked( '_blank', $item['target'] ); ?>>
			Tab mới
		</label>
		<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][rel]" value="<?php echo esc_attr( $item['rel'] ); ?>" placeholder="rel">
		<button type="button" class="button-link-delete" data-remove-item>Xóa</button>
	</div>
	<?php
}

function pmm_render_admin_assets() {
	?>
	<style>
		.pmm-wrap .description { max-width: 760px; }
		.pmm-card { background: #fff; border: 1px solid #ccd0d4; margin: 16px 0; }
		.pmm-card__toggle { align-items: center; background: #fff; border: 0; cursor: pointer; display: flex; gap: 12px; padding: 14px 16px; text-align: left; width: 100%; }
		.pmm-card__toggle:hover { background: #f6f7f7; }
		.pmm-card__title { color: #1d2327; flex: 1; font-size: 15px; font-weight: 600; }
		.pmm-card__meta { color: #646970; font-size: 12px; }
		.pmm-card__body { border-top: 1px solid #dcdcde; padding: 16px; }
		.pmm-card.is-collapsed .pmm-card__body { display: none; }
		.pmm-card.is-collapsed .dashicons { transform: rotate(180deg); }
		.pmm-card__actions { align-items: center; display: flex; gap: 16px; justify-content: flex-end; }
		.pmm-grid { display: grid; gap: 12px 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin: 12px 0 18px; }
		.pmm-grid label span { display: block; font-weight: 600; margin-bottom: 4px; }
		.pmm-grid input[type="text"], .pmm-grid input[type="number"], .pmm-grid select { width: 100%; max-width: 100%; }
		.pmm-grid select[multiple] { min-height: 108px; }
		.pmm-check { align-items: center; display: flex; gap: 8px; }
		.pmm-item { align-items: center; border-top: 1px solid #f0f0f1; display: grid; gap: 8px; grid-template-columns: 1fr 1.4fr 0.8fr auto 0.7fr auto; padding: 10px 0; }
		.pmm-item input[type="text"] { width: 100%; }
		@media (max-width: 900px) { .pmm-item { grid-template-columns: 1fr; } }
	</style>
	<script>
		(function () {
			const rules = document.getElementById('pmm-rules');
			const ruleTemplate = document.getElementById('pmm-rule-template');
			const addRule = document.getElementById('pmm-add-rule');

			function nextRuleIndex() {
				return String(Date.now());
			}

			function nextItemIndex(rule) {
				return String(rule.querySelectorAll('[data-item]').length + Date.now());
			}

			function blankItemHtml(ruleIndex, itemIndex) {
				return '<div class="pmm-item" data-item>' +
					'<input type="text" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][label]" placeholder="Tên item">' +
					'<input type="text" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][url]" placeholder="https://... hoặc /duong-dan/ hoặc #id-section">' +
					'<input type="text" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][class]" placeholder="CSS class">' +
					'<label><input type="checkbox" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][target]" value="_blank"> Tab mới</label>' +
					'<input type="text" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][rel]" placeholder="rel">' +
					'<button type="button" class="button-link-delete" data-remove-item>Xóa</button>' +
				'</div>';
			}

			function refreshRuleTitle(input) {
				const rule = input.closest('[data-rule]');
				const title = rule.querySelector('[data-rule-title]');
				const value = input.value.trim();

				title.textContent = value || 'Menu rule';
			}

			addRule.addEventListener('click', function () {
				const ruleIndex = nextRuleIndex();
				rules.insertAdjacentHTML('beforeend', ruleTemplate.innerHTML.replaceAll('__RULE_INDEX__', ruleIndex));
			});

			rules.addEventListener('click', function (event) {
				const removeRule = event.target.closest('[data-remove-rule]');
				const removeItem = event.target.closest('[data-remove-item]');
				const addItem = event.target.closest('[data-add-item]');
				const toggleRule = event.target.closest('[data-toggle-rule]');

				if (toggleRule) {
					const rule = toggleRule.closest('[data-rule]');
					const isCollapsed = rule.classList.toggle('is-collapsed');

					toggleRule.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
				}

				if (removeRule) {
					event.preventDefault();
					removeRule.closest('[data-rule]').remove();
				}

				if (removeItem) {
					event.preventDefault();
					removeItem.closest('[data-item]').remove();
				}

				if (addItem) {
					event.preventDefault();
					const rule = addItem.closest('[data-rule]');
					const input = rule.querySelector('[name^="pmm_rules["]');
					const match = input.name.match(/^pmm_rules\[([^\]]+)\]/);

					if (match) {
						rule.querySelector('[data-items]').insertAdjacentHTML('beforeend', blankItemHtml(match[1], nextItemIndex(rule)));
					}
				}
			});

			rules.addEventListener('input', function (event) {
				if (event.target.matches('[data-title-input]')) {
					refreshRuleTitle(event.target);
				}
			});
		}());
	</script>
	<?php
}
