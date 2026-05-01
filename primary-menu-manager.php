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
add_action( 'wp_ajax_pmm_search_posts', 'pmm_ajax_search_posts' );
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

function pmm_get_searchable_post_types() {
	$post_types = get_post_types( array( 'public' => true ), 'names' );
	$post_types = is_array( $post_types ) ? array_values( array_diff( $post_types, array( 'attachment' ) ) ) : array( 'post', 'page' );

	return apply_filters( 'pmm_searchable_post_types', $post_types );
}

function pmm_get_post_picker_items( $post_ids ) {
	$items = array();

	foreach ( pmm_sanitize_id_list( $post_ids ) as $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			continue;
		}

		$items[] = pmm_format_post_picker_item( $post );
	}

	return $items;
}

function pmm_format_post_picker_item( $post ) {
	$post_type_label   = pmm_get_post_type_label( $post->post_type );
	$post_status_label = pmm_get_post_status_label( $post->post_status );

	return array(
		'id'    => (int) $post->ID,
		'title' => get_the_title( $post ),
		'meta'  => sprintf(
			'%s #%d%s',
			$post_type_label,
			(int) $post->ID,
			$post_status_label ? ' - ' . $post_status_label : ''
		),
	);
}

function pmm_get_post_type_label( $post_type ) {
	$post_type_object = get_post_type_object( $post_type );

	if ( $post_type_object && ! empty( $post_type_object->labels->singular_name ) ) {
		return $post_type_object->labels->singular_name;
	}

	return $post_type;
}

function pmm_get_post_status_label( $post_status ) {
	$post_status_object = get_post_status_object( $post_status );

	if ( $post_status_object && ! empty( $post_status_object->label ) ) {
		return $post_status_object->label;
	}

	return $post_status;
}

function pmm_ajax_search_posts() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to search posts.', 'primary-menu-manager' ) ), 403 );
	}

	check_ajax_referer( 'pmm_search_posts', 'nonce' );

	$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

	wp_send_json_success(
		array(
			'items' => pmm_search_post_picker_items( $query ),
		)
	);
}

function pmm_search_post_picker_items( $query ) {
	$query = trim( (string) $query );

	if ( '' === $query ) {
		return array();
	}

	$items = array();

	if ( ctype_digit( $query ) ) {
		$post = get_post( absint( $query ) );

		if ( $post && in_array( $post->post_type, pmm_get_searchable_post_types(), true ) ) {
			$items[ (int) $post->ID ] = pmm_format_post_picker_item( $post );
		}
	}

	$post_query = new WP_Query(
		array(
			'post_type'              => pmm_get_searchable_post_types(),
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			's'                      => $query,
			'posts_per_page'         => 12,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $post_query->posts as $post ) {
		$items[ (int) $post->ID ] = pmm_format_post_picker_item( $post );
	}

	return array_values( $items );
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
	if ( isset( $rule['menu_locations'] ) && is_array( $rule['menu_locations'] ) ) {
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

function pmm_get_rule_card_classes( $rule ) {
	$classes = array( 'pmm-card', 'is-collapsed' );

	if ( empty( $rule['enabled'] ) ) {
		$classes[] = 'is-disabled';
	}

	$classes = apply_filters( 'pmm_rule_card_classes', $classes, $rule );

	return implode( ' ', array_map( 'sanitize_html_class', array_filter( $classes ) ) );
}

function pmm_render_rule_card( $rule, $rule_index, $menu_locations ) {
	$rule = wp_parse_args( $rule, pmm_blank_rule() );
	$selected_locations = pmm_get_rule_menu_locations( $rule );
	$rule_title         = $rule['title'] ? $rule['title'] : 'Menu rule';
	$location_count     = count( $selected_locations );
	?>
	<section class="<?php echo esc_attr( pmm_get_rule_card_classes( $rule ) ); ?>" data-rule>
		<div class="pmm-card__toggle">
			<button type="button" class="pmm-card__toggle-button" data-toggle-rule aria-expanded="false">
				<span class="pmm-card__identity">
					<span class="pmm-card__eyebrow">Menu rule</span>
					<span class="pmm-card__title" data-rule-title><?php echo esc_html( $rule_title ); ?></span>
				</span>
				<span class="pmm-card__summary">
					<span class="pmm-card__meta" data-location-count><?php echo esc_html( $location_count . ' vị trí menu' ); ?></span>
					<span class="pmm-status<?php echo ! empty( $rule['enabled'] ) ? ' is-active' : ''; ?>" data-rule-status><?php echo ! empty( $rule['enabled'] ) ? 'Đang bật' : 'Đang tắt'; ?></span>
				</span>
				<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
			</button>
			<div class="pmm-card__header-actions">
				<label class="pmm-switch pmm-switch--compact">
					<input type="checkbox" data-enabled-input name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>>
					<span>Bật</span>
				</label>
				<button type="button" class="button-link-delete pmm-rule-delete" data-remove-rule>
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					Xóa
				</button>
			</div>
		</div>

		<div class="pmm-card__body" data-rule-body>
			<div class="pmm-rule-layout">
				<div class="pmm-rule-layout__left">
					<div class="pmm-section pmm-section--settings">
						<div class="pmm-section__head">
							<h3>Thiết lập rule</h3>
							<span data-location-count><?php echo esc_html( $location_count . ' vị trí đã chọn' ); ?></span>
						</div>
						<div class="pmm-grid pmm-grid--top">
							<label class="pmm-field">
								<span>Tên rule</span>
								<input type="text" class="regular-text" data-title-input name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][title]" value="<?php echo esc_attr( $rule['title'] ); ?>" placeholder="VD: Menu Landing Blanca City">
							</label>
							<label class="pmm-field pmm-field--priority">
								<span>Ưu tiên</span>
								<input type="number" min="1" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][priority]" value="<?php echo esc_attr( $rule['priority'] ); ?>">
							</label>
						</div>

						<div class="pmm-field">
							<span>Menu location</span>
							<div class="pmm-location-picker" data-location-picker>
								<input type="hidden" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][menu_locations][]" value="">
								<?php if ( empty( $menu_locations ) ) : ?>
									<p class="pmm-muted">Theme hiện chưa đăng ký menu location nào.</p>
								<?php else : ?>
									<?php foreach ( $menu_locations as $location => $label ) : ?>
										<label class="pmm-location-pill">
											<input type="checkbox" data-location-input name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][menu_locations][]" value="<?php echo esc_attr( $location ); ?>" <?php checked( in_array( $location, $selected_locations, true ) ); ?>>
											<span class="pmm-location-pill__mark" aria-hidden="true"></span>
											<span class="pmm-location-pill__text">
												<span><?php echo esc_html( $label ); ?></span>
												<code><?php echo esc_html( $location ); ?></code>
											</span>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>

						<div class="pmm-rule-conditions">
							<div class="pmm-field pmm-post-picker" data-post-picker>
								<span>ID page/post cụ thể</span>
								<input type="hidden" data-post-ids name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][post_ids]" value="<?php echo esc_attr( implode( ',', (array) $rule['post_ids'] ) ); ?>">
								<div class="pmm-post-picker__selected" data-selected-posts>
									<?php foreach ( pmm_get_post_picker_items( $rule['post_ids'] ) as $post_item ) : ?>
										<span class="pmm-post-chip" data-post-id="<?php echo esc_attr( $post_item['id'] ); ?>">
											<span>
												<strong><?php echo esc_html( $post_item['title'] ); ?></strong>
												<small><?php echo esc_html( $post_item['meta'] ); ?></small>
											</span>
											<button type="button" data-remove-post aria-label="Bỏ <?php echo esc_attr( $post_item['title'] ); ?>">
												<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
											</button>
										</span>
									<?php endforeach; ?>
								</div>
								<div class="pmm-post-picker__search">
									<input type="search" data-post-search placeholder="Gõ tên page/post để chọn">
									<div class="pmm-post-picker__results" data-post-results hidden></div>
								</div>
							</div>
							<label class="pmm-field">
								<span>URL path chứa</span>
								<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][path_contains]" value="<?php echo esc_attr( $rule['path_contains'] ); ?>" placeholder="/landing/">
							</label>
						</div>
					</div>
				</div>

				<div class="pmm-rule-layout__right">
					<div class="pmm-section">
						<div class="pmm-section__head">
							<h3>Logo header</h3>
							<label class="pmm-switch">
								<input type="checkbox" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][logo_enabled]" value="1" <?php checked( ! empty( $rule['logo_enabled'] ) ); ?>>
								<span>Đổi logo/link logo</span>
							</label>
						</div>
						<div class="pmm-grid">
							<label class="pmm-field">
								<span>Logo URL</span>
								<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][logo_url]" value="<?php echo esc_attr( $rule['logo_url'] ); ?>" placeholder="https://.../logo.svg hoặc /uploads/logo.png">
							</label>
							<label class="pmm-field">
								<span>Link logo</span>
								<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][logo_link_url]" value="<?php echo esc_attr( $rule['logo_link_url'] ); ?>" placeholder="/landing-page/">
							</label>
						</div>
					</div>

					<div class="pmm-section">
						<div class="pmm-section__head">
							<h3>Menu items</h3>
							<button type="button" class="button pmm-button-secondary" data-add-item>Thêm item</button>
						</div>
						<div class="pmm-items" data-items>
							<?php foreach ( (array) $rule['items'] as $item_index => $item ) : ?>
								<?php pmm_render_menu_item_row( $rule_index, $item_index, $item ); ?>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
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
		<span class="pmm-item__handle dashicons dashicons-menu" aria-hidden="true"></span>
		<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][label]" value="<?php echo esc_attr( $item['label'] ); ?>" placeholder="Tên item">
		<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][url]" value="<?php echo esc_attr( $item['url'] ); ?>" placeholder="https://... hoặc /duong-dan/ hoặc #id-section">
		<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][class]" value="<?php echo esc_attr( $item['class'] ); ?>" placeholder="CSS class">
		<label class="pmm-mini-check">
			<input type="checkbox" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][target]" value="_blank" <?php checked( '_blank', $item['target'] ); ?>>
			Tab mới
		</label>
		<input type="text" name="pmm_rules[<?php echo esc_attr( $rule_index ); ?>][items][<?php echo esc_attr( $item_index ); ?>][rel]" value="<?php echo esc_attr( $item['rel'] ); ?>" placeholder="rel">
		<button type="button" class="button-link-delete pmm-icon-delete" data-remove-item aria-label="Xóa item">
			<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
		</button>
	</div>
	<?php
}

function pmm_render_admin_assets() {
	?>
	<style>
		.pmm-wrap { --pmm-ink: #172033; --pmm-muted: #687386; --pmm-line: #d9e0ea; --pmm-panel: #ffffff; --pmm-soft: #f6f8fb; --pmm-accent: #2563eb; --pmm-accent-soft: #e8f0ff; --pmm-success: #15803d; --pmm-danger: #b42318; }
		.pmm-wrap .description { color: var(--pmm-muted); max-width: 880px; }
		.pmm-card { background: var(--pmm-panel); border: 1px solid var(--pmm-line); border-radius: 8px; box-shadow: 0 14px 36px rgba(23, 32, 51, 0.07); margin: 18px 0; overflow: hidden; }
		.pmm-card.is-disabled { opacity: 0.78; }
		.pmm-card__toggle { align-items: center; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); display: grid; gap: 12px; grid-template-columns: minmax(0, 1fr) auto; padding: 14px 16px; }
		.pmm-card__toggle:hover { background: #f3f6fb; }
		.pmm-card__toggle-button { align-items: center; background: transparent; border: 0; cursor: pointer; display: flex; gap: 14px; min-width: 0; padding: 0; text-align: left; width: 100%; }
		.pmm-card__toggle-button:focus { box-shadow: 0 0 0 2px var(--pmm-accent); outline: 2px solid transparent; }
		.pmm-card__header-actions { align-items: center; display: inline-flex; gap: 10px; justify-content: flex-end; }
		.pmm-card__identity { display: grid; flex: 1; gap: 3px; min-width: 0; }
		.pmm-card__eyebrow { color: var(--pmm-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; }
		.pmm-card__title { color: var(--pmm-ink); font-size: 16px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.pmm-card__summary { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
		.pmm-card__meta { background: #eef2f7; border: 1px solid #e0e7ef; border-radius: 999px; color: #475569; display: inline-flex; font-size: 12px; font-weight: 600; padding: 4px 9px; }
		.pmm-status { background: #fff1f0; border: 1px solid #ffd7d2; border-radius: 999px; color: var(--pmm-danger); display: inline-flex; font-size: 12px; font-weight: 700; padding: 4px 9px; }
		.pmm-status.is-active { background: #eaf8ef; border-color: #c8ecd3; color: var(--pmm-success); }
		.pmm-card__body { border-top: 1px solid var(--pmm-line); padding: 18px; }
		.pmm-card.is-collapsed .pmm-card__body { display: none; }
		.pmm-card.is-collapsed .pmm-card__toggle-button > .dashicons { transform: rotate(180deg); }
		.pmm-rule-delete { align-items: center; display: inline-flex; gap: 4px; text-decoration: none; }
		.pmm-rule-layout { align-items: start; display: grid; gap: 16px; grid-template-columns: minmax(300px, 0.85fr) minmax(420px, 1.15fr); }
		.pmm-rule-layout__left, .pmm-rule-layout__right { display: grid; gap: 16px; }
		.pmm-grid { display: grid; gap: 14px 16px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); margin: 0; }
		.pmm-grid--top { grid-template-columns: minmax(190px, 1fr) 110px; }
		.pmm-field > span { color: #334155; display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; }
		.pmm-field input[type="text"], .pmm-field input[type="number"], .pmm-field input[type="search"] { border-color: #cbd5e1; border-radius: 6px; min-height: 36px; width: 100%; }
		.pmm-field input:focus { border-color: var(--pmm-accent); box-shadow: 0 0 0 1px var(--pmm-accent); }
		.pmm-field--priority input { max-width: 110px; }
		.pmm-section { background: var(--pmm-soft); border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
		.pmm-section--settings { display: grid; gap: 14px; }
		.pmm-section__head { align-items: center; display: flex; gap: 12px; justify-content: space-between; margin-bottom: 12px; }
		.pmm-section h3, .pmm-section__head h3 { color: var(--pmm-ink); font-size: 14px; margin: 0 0 12px; }
		.pmm-section__head h3 { margin: 0; }
		.pmm-section__head > span { color: var(--pmm-muted); font-size: 12px; font-weight: 700; }
		.pmm-muted { color: var(--pmm-muted); margin: 0; }
		.pmm-rule-conditions { display: grid; gap: 14px; }
		.pmm-location-picker { display: flex; flex-wrap: wrap; gap: 8px; }
		.pmm-location-pill { align-items: center; background: #fff; border: 1px solid #d8e0eb; border-radius: 999px; cursor: pointer; display: inline-flex; gap: 7px; min-height: 34px; padding: 6px 10px; position: relative; }
		.pmm-location-pill:hover { border-color: #9db5d8; box-shadow: 0 8px 18px rgba(37, 99, 235, 0.08); }
		.pmm-location-pill input { height: 1px; opacity: 0; position: absolute; width: 1px; }
		.pmm-location-pill__mark { align-items: center; border: 1px solid #b9c5d6; border-radius: 999px; display: inline-flex; flex: 0 0 18px; height: 18px; justify-content: center; width: 18px; }
		.pmm-location-pill__mark::after { color: #fff; content: "\f147"; display: none; font-family: dashicons; font-size: 14px; line-height: 1; }
		.pmm-location-pill__text { align-items: baseline; display: inline-flex; gap: 5px; min-width: 0; }
		.pmm-location-pill__text span { color: var(--pmm-ink); font-size: 12px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.pmm-location-pill__text code { background: transparent; color: var(--pmm-muted); font-size: 11px; padding: 0; }
		.pmm-location-pill input:checked + .pmm-location-pill__mark { background: var(--pmm-accent); border-color: var(--pmm-accent); }
		.pmm-location-pill input:checked + .pmm-location-pill__mark::after { display: block; }
		.pmm-location-pill input:checked ~ .pmm-location-pill__text span { color: #174ea6; }
		.pmm-switch { align-items: center; color: #334155; display: inline-flex; gap: 8px; font-weight: 700; }
		.pmm-switch input { margin: 0; }
		.pmm-switch--compact { background: #eef2f7; border: 1px solid #dce5ef; border-radius: 999px; padding: 5px 10px; }
		.pmm-post-picker { position: relative; }
		.pmm-post-picker__selected { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; min-height: 0; }
		.pmm-post-chip { align-items: center; background: var(--pmm-accent-soft); border: 1px solid #bfd2ff; border-radius: 8px; display: inline-flex; gap: 8px; max-width: 100%; padding: 7px 8px 7px 10px; }
		.pmm-post-chip strong { color: #174ea6; display: block; font-size: 13px; line-height: 1.2; }
		.pmm-post-chip small { color: #58709a; display: block; font-size: 11px; line-height: 1.2; margin-top: 2px; }
		.pmm-post-chip button { align-items: center; background: #fff; border: 1px solid #b9ccf5; border-radius: 6px; color: #355da8; cursor: pointer; display: inline-flex; height: 24px; justify-content: center; padding: 0; width: 24px; }
		.pmm-post-picker__search { position: relative; }
		.pmm-post-picker__results { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 16px 32px rgba(23, 32, 51, 0.14); left: 0; max-height: 260px; overflow: auto; padding: 6px; position: absolute; right: 0; top: calc(100% + 6px); z-index: 30; }
		.pmm-post-result { background: #fff; border: 0; border-radius: 6px; cursor: pointer; display: grid; gap: 3px; padding: 9px 10px; text-align: left; width: 100%; }
		.pmm-post-result:hover, .pmm-post-result:focus { background: #eef4ff; outline: none; }
		.pmm-post-result strong { color: var(--pmm-ink); font-size: 13px; }
		.pmm-post-result small { color: var(--pmm-muted); font-size: 12px; }
		.pmm-items { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
		.pmm-item { align-items: center; border-top: 1px solid #edf2f7; display: grid; gap: 8px; grid-template-columns: 24px minmax(130px, 1fr) minmax(180px, 1.45fr) minmax(110px, 0.75fr) 82px minmax(90px, 0.65fr) 34px; padding: 10px; }
		.pmm-item:first-child { border-top: 0; }
		.pmm-item__handle { color: #94a3b8; cursor: default; }
		.pmm-item input[type="text"] { border-color: #cbd5e1; border-radius: 6px; min-height: 34px; width: 100%; }
		.pmm-mini-check { align-items: center; display: inline-flex; gap: 6px; white-space: nowrap; }
		.pmm-icon-delete { align-items: center; border: 1px solid #ffd7d2; border-radius: 6px; color: var(--pmm-danger); display: inline-flex; height: 30px; justify-content: center; padding: 0; text-decoration: none; width: 30px; }
		.pmm-icon-delete:hover { background: #fff1f0; color: var(--pmm-danger); }
		.pmm-button-secondary { border-radius: 6px !important; }
		@media (max-width: 900px) {
			.pmm-card__toggle, .pmm-rule-layout { grid-template-columns: 1fr; }
			.pmm-card__summary, .pmm-section__head { align-items: flex-start; flex-direction: column; }
			.pmm-card__header-actions { justify-content: flex-start; }
			.pmm-grid--top, .pmm-item { grid-template-columns: 1fr; }
			.pmm-field--priority input { max-width: 100%; }
			.pmm-item__handle { display: none; }
		}
	</style>
	<script>
		(function () {
			const rules = document.getElementById('pmm-rules');
			const ruleTemplate = document.getElementById('pmm-rule-template');
			const addRule = document.getElementById('pmm-add-rule');
			const postPickerConfig = {
				ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
				nonce: <?php echo wp_json_encode( wp_create_nonce( 'pmm_search_posts' ) ); ?>
			};

			function nextRuleIndex() {
				return String(Date.now());
			}

			function nextItemIndex(rule) {
				return String(rule.querySelectorAll('[data-item]').length + Date.now());
			}

			function blankItemHtml(ruleIndex, itemIndex) {
				return '<div class="pmm-item" data-item>' +
					'<span class="pmm-item__handle dashicons dashicons-menu" aria-hidden="true"></span>' +
					'<input type="text" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][label]" placeholder="Tên item">' +
					'<input type="text" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][url]" placeholder="https://... hoặc /duong-dan/ hoặc #id-section">' +
					'<input type="text" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][class]" placeholder="CSS class">' +
					'<label class="pmm-mini-check"><input type="checkbox" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][target]" value="_blank"> Tab mới</label>' +
					'<input type="text" name="pmm_rules[' + ruleIndex + '][items][' + itemIndex + '][rel]" placeholder="rel">' +
					'<button type="button" class="button-link-delete pmm-icon-delete" data-remove-item aria-label="Xóa item"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' +
				'</div>';
			}

			function refreshRuleTitle(input) {
				const rule = input.closest('[data-rule]');
				const title = rule.querySelector('[data-rule-title]');
				const value = input.value.trim();

				title.textContent = value || 'Menu rule';
			}

			function refreshLocationCount(rule) {
				const count = rule.querySelectorAll('[data-location-input]:checked').length;
				const labels = rule.querySelectorAll('[data-location-count]');
				const text = count + ' vị trí menu';

				labels.forEach(function (label) {
					label.textContent = label.closest('.pmm-section__head') ? count + ' vị trí đã chọn' : text;
				});
			}

			function refreshRuleStatus(input) {
				const rule = input.closest('[data-rule]');
				const status = rule.querySelector('[data-rule-status]');

				rule.classList.toggle('is-disabled', !input.checked);
				status.classList.toggle('is-active', input.checked);
				status.textContent = input.checked ? 'Đang bật' : 'Đang tắt';
			}

			function selectedPostIds(picker) {
				return picker.querySelector('[data-post-ids]').value.split(',').map(function (id) {
					return id.trim();
				}).filter(Boolean);
			}

			function syncSelectedPostIds(picker) {
				const ids = Array.from(picker.querySelectorAll('[data-post-id]')).map(function (chip) {
					return chip.getAttribute('data-post-id');
				});

				picker.querySelector('[data-post-ids]').value = ids.join(',');
			}

			function createPostChip(item) {
				const chip = document.createElement('span');
				const content = document.createElement('span');
				const title = document.createElement('strong');
				const meta = document.createElement('small');
				const button = document.createElement('button');
				const icon = document.createElement('span');

				chip.className = 'pmm-post-chip';
				chip.setAttribute('data-post-id', item.id);
				title.textContent = item.title;
				meta.textContent = item.meta;
				button.type = 'button';
				button.setAttribute('data-remove-post', '');
				button.setAttribute('aria-label', 'Bỏ ' + item.title);
				icon.className = 'dashicons dashicons-no-alt';
				icon.setAttribute('aria-hidden', 'true');

				content.append(title, meta);
				button.append(icon);
				chip.append(content, button);

				return chip;
			}

			function renderPostResults(picker, items) {
				const results = picker.querySelector('[data-post-results]');
				results.innerHTML = '';

				if (!items.length) {
					const empty = document.createElement('div');
					empty.className = 'pmm-post-result';
					empty.textContent = 'Không tìm thấy page/post phù hợp';
					results.append(empty);
					results.hidden = false;
					return;
				}

				items.forEach(function (item) {
					const button = document.createElement('button');
					const title = document.createElement('strong');
					const meta = document.createElement('small');

					button.type = 'button';
					button.className = 'pmm-post-result';
					button.setAttribute('data-select-post', '');
					button.dataset.id = item.id;
					button.dataset.title = item.title;
					button.dataset.meta = item.meta;
					title.textContent = item.title;
					meta.textContent = item.meta;
					button.append(title, meta);
					results.append(button);
				});

				results.hidden = false;
			}

			function searchPosts(input) {
				const picker = input.closest('[data-post-picker]');
				const query = input.value.trim();
				const results = picker.querySelector('[data-post-results]');

				clearTimeout(input.pmmSearchTimer);

				if (query.length < 2) {
					results.hidden = true;
					results.innerHTML = '';
					return;
				}

				input.pmmSearchTimer = setTimeout(function () {
					const params = new URLSearchParams({
						action: 'pmm_search_posts',
						nonce: postPickerConfig.nonce,
						q: query
					});

					fetch(postPickerConfig.ajaxUrl + '?' + params.toString(), {
						credentials: 'same-origin'
					})
						.then(function (response) {
							return response.json();
						})
						.then(function (payload) {
							renderPostResults(picker, payload.success ? payload.data.items : []);
						})
						.catch(function () {
							renderPostResults(picker, []);
						});
				}, 220);
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
				const removePost = event.target.closest('[data-remove-post]');
				const selectPost = event.target.closest('[data-select-post]');

				if (toggleRule) {
					const rule = toggleRule.closest('[data-rule]');
					const willOpen = rule.classList.contains('is-collapsed');

					rules.querySelectorAll('[data-rule]').forEach(function (card) {
						card.classList.add('is-collapsed');
						const button = card.querySelector('[data-toggle-rule]');

						if (button) {
							button.setAttribute('aria-expanded', 'false');
						}
					});

					if (willOpen) {
						rule.classList.remove('is-collapsed');
						toggleRule.setAttribute('aria-expanded', 'true');
					} else {
						toggleRule.setAttribute('aria-expanded', 'false');
					}

					return;
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

				if (removePost) {
					event.preventDefault();
					const picker = removePost.closest('[data-post-picker]');
					removePost.closest('[data-post-id]').remove();
					syncSelectedPostIds(picker);
				}

				if (selectPost) {
					event.preventDefault();
					const picker = selectPost.closest('[data-post-picker]');
					const ids = selectedPostIds(picker);
					const item = {
						id: selectPost.dataset.id,
						title: selectPost.dataset.title,
						meta: selectPost.dataset.meta
					};

					if (!ids.includes(item.id)) {
						picker.querySelector('[data-selected-posts]').append(createPostChip(item));
						syncSelectedPostIds(picker);
					}

					picker.querySelector('[data-post-search]').value = '';
					picker.querySelector('[data-post-results]').hidden = true;
					picker.querySelector('[data-post-results]').innerHTML = '';
				}
			});

			rules.addEventListener('input', function (event) {
				if (event.target.matches('[data-title-input]')) {
					refreshRuleTitle(event.target);
				}

				if (event.target.matches('[data-post-search]')) {
					searchPosts(event.target);
				}
			});

			rules.addEventListener('change', function (event) {
				if (event.target.matches('[data-location-input]')) {
					refreshLocationCount(event.target.closest('[data-rule]'));
				}

				if (event.target.matches('[data-enabled-input]')) {
					refreshRuleStatus(event.target);
				}
			});
		}());
	</script>
	<?php
}
