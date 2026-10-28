<?php
/**
 * Plugin Name: Website Stylekit
 * Plugin URI: https://rinodeboer.com
 * Description: Import and export Elementor V4 and Bricks variables and global classes as portable CSS.
 * Version: 2.6
 * Author: Rino de Boer
 * Author URI: https://rinodeboer.com
 * License: GPL v2 or later
 * Text Domain: websitestylekit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSK_VERSION', '2.6' );
define( 'WSK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
class Website_Stylekit {

	private static $instance = null;
	const BACKUPS_META_KEY = '_wsk_backups';
	const MAX_BACKUPS = 10;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_wsk_export_variables', array( $this, 'ajax_export_variables' ) );
		add_action( 'wp_ajax_wsk_import_variables', array( $this, 'ajax_import_variables' ) );
		add_action( 'wp_ajax_wsk_reorder_variables', array( $this, 'ajax_reorder_variables' ) );
		add_action( 'wp_ajax_wsk_create_backup', array( $this, 'ajax_create_backup' ) );
		add_action( 'wp_ajax_wsk_restore_backup', array( $this, 'ajax_restore_backup' ) );
		add_action( 'wp_ajax_wsk_delete_backup', array( $this, 'ajax_delete_backup' ) );
		add_action( 'wp_ajax_wsk_download_backup', array( $this, 'ajax_download_backup' ) );
		add_action( 'wp_ajax_wsk_view_backup', array( $this, 'ajax_view_backup' ) );
		add_action( 'wp_ajax_wsk_upload_backup', array( $this, 'ajax_upload_backup' ) );
	}

	/**
	 * Add menu item under Settings.
	 */
	public function add_admin_menu() {
		$menu_icon_svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 120 120"><path fill="#a7aaad" d="M83 0C103.435 0 120 16.5655 120 37V83C120 103.435 103.435 120 83 120H37C16.5655 120 0 103.435 0 83V37C0 16.5655 16.5655 0 37 0H83ZM101.04 32.9424C96.6498 29.4405 90.2472 27.9148 84.5342 29.002C75.9223 30.6259 70.8418 36.5007 70.8418 44.832C70.8419 52.4289 73.6003 55.7611 84.6182 61.4658C91.2881 64.9254 93.3423 67.1995 93.3564 71.125C93.3705 80.4447 80.8613 84.1305 72.9951 77.127C68.7736 73.3708 67.5633 69.967 59.7676 40.0449C57.7835 32.3915 58.3601 32.9424 52.2676 32.9424C50.0315 32.9424 48.7026 32.7792 47.7598 33.1836L47.3164 33.0615L47.1846 33.5439C45.9052 34.6532 45.3204 37.6377 43.2754 45.5098C41.6853 51.6383 39.5036 59.9697 38.4482 63.9941C37.3796 68.0297 36.5081 71.5014 36.5068 71.7178C36.5068 73.0027 35.6904 71.4354 35.0713 68.9219C30.105 49.0863 26.2499 34.2331 25.9248 33.7188C25.319 32.7728 14.4705 32.8431 14.3291 33.7891C14.2589 34.3264 17.2703 46.2028 24.6719 74.458C27.6124 85.7247 28.1622 87.533 28.7812 88.084C29.7808 88.9594 40.5032 89.0728 41.5586 88.2256C42.1363 87.7423 47.4826 69.0198 50.958 55.3096C51.9571 51.3699 52.267 50.9181 52.6328 52.8525C53.9134 59.7436 58.3042 75.1498 59.8662 78.2705C66.9022 92.3348 87.6442 95.8649 98.6201 84.8506C104.938 78.4962 106.655 68.2587 102.546 61.2266C100.843 58.3318 98.1692 56.1995 91.7246 52.6553C82.8172 47.7553 80.4113 44.5209 82.6768 40.4541C85.0128 36.3029 90.6128 36.3458 95.6504 40.5537C98.2675 42.7423 98.17 42.7845 101.505 38.0684C103.602 35.1313 103.587 34.9615 101.04 32.9424Z"/></svg>' );
		add_menu_page(
			__( 'Website Stylekit', 'websitestylekit' ),
			__( 'Website Stylekit', 'websitestylekit' ),
			'manage_options',
			'website-stylekit',
			array( $this, 'render_admin_page' ),
			$menu_icon_svg
		);
	}

	/**
	 * Enqueue CSS and JS on our admin page only.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_website-stylekit' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wsk-admin',
			WSK_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WSK_VERSION
		);

		wp_enqueue_script(
			'wsk-admin',
			WSK_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WSK_VERSION,
			true
		);

		wp_localize_script( 'wsk-admin', 'wskData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wsk_nonce' ),
		) );
	}

	/**
	 * Get the active Elementor kit post ID.
	 */
	private function get_kit_id() {
		$kit_id = get_option( 'elementor_active_kit' );

		if ( ! $kit_id ) {
			return false;
		}

		return (int) $kit_id;
	}

	/**
	 * Read raw variables data from the kit post meta.
	 */
	private function get_raw_variables() {
		$kit_id = $this->get_kit_id();

		if ( ! $kit_id ) {
			return new WP_Error( 'no_kit', 'No active Elementor kit found.' );
		}

		$meta = get_post_meta( $kit_id, '_elementor_global_variables', true );

		if ( empty( $meta ) ) {
			return new WP_Error( 'no_variables', 'No variables found. Make sure you have created variables in Elementor.' );
		}

		if ( is_string( $meta ) ) {
			$meta = json_decode( $meta, true );
		}

		if ( ! is_array( $meta ) || ! isset( $meta['data'] ) ) {
			return new WP_Error( 'invalid_data', 'Variable data is in an unexpected format.' );
		}

		return $meta;
	}

	/**
	 * Which variable types to include based on the filter.
	 */
	private function get_type_filter( $filter ) {
		$types = array(
			'colors' => array( 'global-color-variable' ),
			'sizes'  => array( 'global-size-variable', 'global-custom-size-variable' ),
			'fonts'  => array( 'global-font-variable' ),
			'all'    => array( 'global-color-variable', 'global-size-variable', 'global-custom-size-variable', 'global-font-variable' ),
		);

		return $types[ $filter ] ?? $types['all'];
	}

	/**
	 * Convert an Elementor internal type to this plugin's import type.
	 */
	private function get_import_type_from_elementor_type( $elementor_type ) {
		if ( 'global-color-variable' === $elementor_type ) {
			return 'colors';
		}

		if ( in_array( $elementor_type, array( 'global-size-variable', 'global-custom-size-variable' ), true ) ) {
			return 'sizes';
		}

		return false;
	}

	/**
	 * Extract a clean, readable value from Elementor's nested storage format.
	 */
	private function extract_clean_value( $value, $type ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$inner = $value['value'] ?? $value;

		// Colors: { "$$type": "color", "value": "#hex" }
		if ( 'global-color-variable' === $type ) {
			return is_array( $inner ) ? ( $inner['value'] ?? $inner ) : $inner;
		}

		// Sizes: { "$$type": "size", "value": { "size": 16, "unit": "px" } }
		if ( in_array( $type, array( 'global-size-variable', 'global-custom-size-variable' ), true ) ) {
			if ( is_array( $inner ) && isset( $inner['unit'] ) ) {
				if ( 'custom' === $inner['unit'] ) {
					// Clamp or other custom value.
					return $inner['size'] ?? '';
				}
				if ( 'auto' === $inner['unit'] ) {
					return 'auto';
				}
				return ( $inner['size'] ?? '0' ) . $inner['unit'];
			}
			return $inner;
		}

		// Fonts: { "$$type": "string", "value": "Inter" }
		if ( 'global-font-variable' === $type ) {
			return is_array( $inner ) ? ( $inner['value'] ?? $inner ) : $inner;
		}

		return $inner;
	}

	/**
	 * Export variables as clean JSON.
	 */
	public function export_clean_variables( $filter = 'all' ) {
		$raw = $this->get_raw_variables();

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$allowed_types = $this->get_type_filter( $filter );
		$result = array();

		foreach ( $raw['data'] as $id => $variable ) {
			if ( ! empty( $variable['deleted_at'] ) ) {
				continue;
			}

			$type = $variable['type'] ?? '';

			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}

			$result[ $id ] = array(
				'type'  => $type,
				'label' => $variable['label'] ?? '',
				'value' => $this->extract_clean_value( $variable['value'] ?? '', $type ),
				'order' => $variable['order'] ?? 0,
			);
		}

		uasort( $result, function( $a, $b ) {
			return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
		} );

		return $result;
	}

	/**
	 * Export variables as CSS custom properties.
	 */
	public function export_css_variables( $filter = 'all' ) {
		$variables = $this->export_clean_variables( $filter );

		if ( is_wp_error( $variables ) ) {
			return $variables;
		}

		return $this->build_css_variables_output( $variables );
	}

	/**
	 * Build CSS custom properties from clean variables.
	 */
	private function build_css_variables_output( $variables ) {
		$lines = array( ':root {' );

		foreach ( $variables as $variable ) {
			$type = $variable['type'] ?? '';
			$label = $this->name_to_label( $variable['label'] ?? '' );
			$value = $variable['value'] ?? '';

			if ( '' === $label || is_array( $value ) ) {
				continue;
			}

			// No type prefix on the name. The framework and the Website Stylekit
			// design.md export both use the bare form (--primary-brand-1), and
			// nothing else in the system carries a type prefix either.
			// Imports still accept the older --color-* names.
			$name = $label;

			$lines[] = '  --' . $name . ': ' . $value . ';';
		}

		$lines[] = '}';

		return implode( "\n", $lines );
	}

	/**
	 * Export variables and Elementor's saved global classes as CSS.
	 */
	public function export_css_framework( $filter = 'all' ) {
		$variables = $this->export_clean_variables( $filter );

		if ( is_wp_error( $variables ) ) {
			return $variables;
		}

		$css = $this->build_css_variables_output( $variables );
		$classes_css = $this->export_elementor_global_classes_css();

		if ( '' === $classes_css ) {
			return $css;
		}

		return $css . "\n\n/* Elementor global classes */\n" . $this->format_css_output( $classes_css );
	}

	/**
	 * Remove Elementor's frontend scope from exported class CSS.
	 */
	private function unprefix_elementor_scope( $css ) {
		$css = preg_replace( '/(^|{|})\\s*\\.elementor\\s+/', '$1', $css );
		$css = preg_replace( '/,\\s*\\.elementor\\s+/', ',', $css );

		return $css;
	}

	/**
	 * Make minified CSS readable without changing how browsers or importers parse it.
	 */
	private function format_css_output( $css ) {
		$css = trim( $css );

		if ( '' === $css ) {
			return '';
		}

		$css = preg_replace( '/\\s+/', ' ', $css );
		$output = '';
		$indent = 0;
		$paren_depth = 0;
		$in_selector = true;
		$length = strlen( $css );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $css[ $i ];

			if ( '(' === $char ) {
				$paren_depth++;
				$output .= $char;
				continue;
			}

			if ( ')' === $char ) {
				$paren_depth = max( 0, $paren_depth - 1 );
				$output .= $char;
				continue;
			}

			if ( ',' === $char && $in_selector && 0 === $paren_depth ) {
				$output = rtrim( $output ) . ",\n" . str_repeat( '  ', $indent );
				continue;
			}

			if ( '{' === $char ) {
				$output = rtrim( $output ) . " {\n";
				$indent++;
				$output .= str_repeat( '  ', $indent );
				$in_selector = false;
				continue;
			}

			if ( ';' === $char ) {
				$output = rtrim( $output ) . ";\n" . str_repeat( '  ', $indent );
				continue;
			}

			if ( '}' === $char ) {
				$indent = max( 0, $indent - 1 );
				$output = rtrim( $output ) . "\n" . str_repeat( '  ', $indent ) . "}\n\n" . str_repeat( '  ', $indent );
				$in_selector = true;
				continue;
			}

			$output .= $char;
		}

		$output = preg_replace( "/\\n{3,}/", "\n\n", $output );

		return trim( $output );
	}

	/**
	 * Export Elementor global classes using Elementor's own atomic styles renderer.
	 */
	private function export_elementor_global_classes_css() {
		$repository_class = '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';

		if ( ! class_exists( $repository_class ) ) {
			return '';
		}

		$global_classes = $repository_class::make()->all()->get();

		return $this->render_global_classes_css( $global_classes );
	}

	/**
	 * Render a global classes data payload as readable CSS.
	 */
	private function render_global_classes_css( $global_classes ) {
		$renderer_class = '\\Elementor\\Modules\\AtomicWidgets\\Styles\\Styles_Renderer';
		$plugin_class = '\\Elementor\\Plugin';

		if ( ! class_exists( $renderer_class ) || ! class_exists( $plugin_class ) ) {
			return '';
		}

		$items = $global_classes['items'] ?? array();
		$order = $global_classes['order'] ?? array();

		if ( empty( $items ) || empty( $order ) ) {
			return '';
		}

		$ordered_items = array();

		foreach ( $order as $class_id ) {
			if ( empty( $items[ $class_id ] ) || empty( $items[ $class_id ]['label'] ) ) {
				continue;
			}

			$item = $items[ $class_id ];
			$item['id'] = $this->name_to_label( $item['label'] );
			$ordered_items[] = $item;
		}

		if ( empty( $ordered_items ) ) {
			return '';
		}

		$plugin = $plugin_class::$instance ?? null;
		$breakpoints = array();

		if ( $plugin && isset( $plugin->breakpoints ) && method_exists( $plugin->breakpoints, 'get_breakpoints_config' ) ) {
			$breakpoints = $plugin->breakpoints->get_breakpoints_config();
		}

		$css = $renderer_class::make( $breakpoints )->render( array_reverse( $ordered_items ) );

		return $this->unprefix_elementor_scope( $css );
	}

	/**
	 * Export the full raw variable data (mirrors Elementor's storage).
	 */
	public function export_raw_variables( $filter = 'all' ) {
		$raw = $this->get_raw_variables();

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$allowed_types = $this->get_type_filter( $filter );
		$clean_data = array();

		foreach ( $raw['data'] as $id => $variable ) {
			if ( ! empty( $variable['deleted_at'] ) ) {
				continue;
			}

			$type = $variable['type'] ?? '';

			if ( in_array( $type, $allowed_types, true ) ) {
				$clean_data[ $id ] = $variable;
			}
		}

		return array(
			'data'      => $clean_data,
			'watermark' => $raw['watermark'] ?? 0,
			'version'   => $raw['version'] ?? 1,
		);
	}

	/**
	 * AJAX handler for exporting variables.
	 */
	public function ajax_export_variables() {
		check_ajax_referer( 'wsk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'framework';
		$format = in_array( $format, array( 'framework', 'css', 'clean', 'raw' ), true ) ? $format : 'framework';

		$filter = isset( $_POST['filter'] ) ? sanitize_text_field( wp_unslash( $_POST['filter'] ) ) : 'all';
		$filter = in_array( $filter, array( 'all', 'colors', 'sizes', 'fonts' ), true ) ? $filter : 'all';

		if ( 'framework' === $format ) {
			$result = $this->export_css_framework( $filter );
		} elseif ( 'css' === $format ) {
			$result = $this->export_css_variables( $filter );
		} elseif ( 'raw' === $format ) {
			$result = $this->export_raw_variables( $filter );
		} else {
			$result = $this->export_clean_variables( $filter );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Generate a random Elementor-style variable ID.
	 */
	private function generate_variable_id() {
		return 'e-gv-' . substr( md5( wp_generate_uuid4() ), 0, 7 );
	}

	/**
	 * Convert a human-readable name to a valid Elementor label.
	 * "Primary Brand 1" becomes "primary-brand-1".
	 */
	private function name_to_label( $name ) {
		$label = strtolower( trim( $name ) );
		$label = preg_replace( '/[^a-z0-9]+/', '-', $label );
		$label = trim( $label, '-' );

		// Max 50 chars.
		if ( strlen( $label ) > 50 ) {
			$label = substr( $label, 0, 50 );
		}

		return $label;
	}

	/**
	 * Detect the Elementor variable type from a CSS custom property name and value.
	 */
	private function detect_css_variable_type( $name, $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return false;
		}

		$name = strtolower( trim( $name ) );
		$value = strtolower( trim( $value ) );

		if ( preg_match( '/^(color|primary|secondary|brand|light|dark)-/', $name ) || preg_match( '/^(#(?:[0-9a-f]{3,8})|rgb\\(|rgba\\(|hsl\\(|hsla\\()/', $value ) ) {
			return 'colors';
		}

		if ( preg_match( '/^(font-family|font)-/', $name ) || preg_match( '/(^|-)font$/', $name ) ) {
			return 'fonts';
		}

		if ( preg_match( '/^(font-size|title-size|body-size|size|space|spacing|radius|gap)-/', $name ) ) {
			return 'sizes';
		}

		if ( preg_match( '/^(clamp|calc|min|max|var)\\s*\\(/', $value ) || preg_match( '/^-?\\d*\\.?\\d+\\s*(px|rem|em|%|vw|vh|vmin|vmax)$/', $value ) || is_numeric( $value ) ) {
			return 'sizes';
		}

		return false;
	}

	/**
	 * Convert a CSS custom property name to a clean Elementor label.
	 */
	private function css_name_to_label( $name, $var_type ) {
		$label = $this->name_to_label( $name );

		if ( 'colors' === $var_type ) {
			$label = preg_replace( '/^color-/', '', $label );
		}

		if ( 'sizes' === $var_type ) {
			$label = preg_replace( '/^font-size-/', '', $label );
		}

		if ( 'fonts' === $var_type ) {
			$label = preg_replace( '/^font-family-/', '', $label );
		}

		return $label;
	}

	/**
	 * Parse CSS custom properties into Elementor-ready variables.
	 * Input: ":root { --primary-brand-1: #E9D816; --title-size-1: clamp(...); }"
	 */
	private function parse_css_variables( $css_string ) {
		$variables = array();
		$warnings = array();
		$supported_names = array();

		// Match each --variable-name: value;
		preg_match_all( '/--([a-zA-Z0-9_-]+)\s*:\s*(.+?)\s*;/', $css_string, $matches, PREG_SET_ORDER );

		foreach ( $matches as $index => $m ) {
			$name = trim( $m[1] );
			$value = trim( $m[2] );
			$var_type = $this->detect_css_variable_type( $name, $value );

			if ( ! $var_type ) {
				$warnings[] = 'Skipped unsupported variable --' . $name . '.';
				continue;
			}

			$supported_names[] = strtolower( $name );

			$variables[] = array(
				'id'    => $this->generate_variable_id(),
				'label' => $this->css_name_to_label( $name, $var_type ),
				'value' => $value,
				'order' => $index + 1,
				'type'  => $var_type,
			);
		}

		return array(
			'variables' => $variables,
			'warnings'  => $warnings,
			'names'     => $supported_names,
		);
	}

	/**
	 * Generate a stable Elementor-style global class ID.
	 */
	private function generate_class_id( $existing_ids = array() ) {
		do {
			$id = 'g-' . substr( md5( wp_generate_uuid4() ), 0, 7 );
		} while ( in_array( $id, $existing_ids, true ) );

		return $id;
	}

	/**
	 * Map a rendered media query back to the active Elementor breakpoint ID.
	 */
	private function get_breakpoint_id_from_media_query( $media_query ) {
		$renderer_class = '\\Elementor\\Modules\\AtomicWidgets\\Styles\\Styles_Renderer';
		$plugin_class = '\\Elementor\\Plugin';

		if ( ! class_exists( $renderer_class ) || ! class_exists( $plugin_class ) ) {
			return null;
		}

		$plugin = $plugin_class::$instance ?? null;

		if ( ! $plugin || ! isset( $plugin->breakpoints ) || ! method_exists( $plugin->breakpoints, 'get_breakpoints_config' ) ) {
			return null;
		}

		$normalize = function( $value ) {
			return preg_replace( '/\\s+/', '', strtolower( trim( $value ) ) );
		};

		$needle = $normalize( $media_query );
		$breakpoints = $plugin->breakpoints->get_breakpoints_config();

		foreach ( $breakpoints as $id => $breakpoint ) {
			if ( $needle === $normalize( $renderer_class::get_media_query( $breakpoint ) ?? '' ) ) {
				return $id;
			}
		}

		return null;
	}

	/**
	 * Split a CSS declaration block into a clean raw declaration string.
	 */
	private function clean_declarations( $declarations ) {
		$declarations = trim( $declarations );
		$lines = array();

		foreach ( explode( ';', $declarations ) as $declaration ) {
			$declaration = trim( $declaration );

			if ( '' === $declaration || false === strpos( $declaration, ':' ) || 0 === strpos( $declaration, '--' ) ) {
				continue;
			}

			$lines[] = rtrim( $declaration, ';' ) . ';';
		}

		return implode( "\n", $lines );
	}

	/**
	 * CSS properties that map straight to Elementor's Size PropType.
	 */
	private function get_atomic_size_props() {
		return array(
			'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
			'font-size', 'letter-spacing', 'word-spacing', 'line-height',
			'column-gap', 'outline-width', 'outline-offset',
			'scroll-margin-top', 'grid-auto-rows', 'grid-auto-columns',
		);
	}

	/**
	 * CSS properties that map straight to Elementor's Color PropType.
	 */
	private function get_atomic_color_props() {
		return array( 'color', 'border-color', 'outline-color' );
	}

	/**
	 * CSS properties that map to Elementor's dedicated Font Family PropType.
	 */
	private function get_atomic_font_family_props() {
		return array( 'font-family' );
	}

	/**
	 * CSS properties that map to Elementor's Number PropType.
	 */
	private function get_atomic_number_props() {
		return array( 'column-count', 'order', 'z-index' );
	}

	/**
	 * CSS properties that map to Elementor's String PropType, with the exact
	 * allowed values from Elementor's own Style_Schema (null = any string).
	 * A value outside the allowed list falls back to custom CSS instead of
	 * making Elementor reject the whole import.
	 */
	private function get_atomic_string_props() {
		$border_styles = array( 'none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset' );

		return array(
			'font-weight'     => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold', 'bolder', 'lighter' ),
			'text-align'      => array( 'start', 'center', 'end', 'justify' ),
			'font-style'      => array( 'normal', 'italic', 'oblique' ),
			'text-decoration' => null,
			'text-transform'  => array( 'none', 'capitalize', 'uppercase', 'lowercase' ),
			'direction'       => array( 'ltr', 'rtl' ),
			'display'         => array( 'block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid', 'inline-grid', 'flow-root', 'none', 'contents' ),
			'flex-direction'  => array( 'row', 'row-reverse', 'column', 'column-reverse' ),
			'flex-wrap'       => array( 'wrap', 'nowrap', 'wrap-reverse' ),
			'position'        => array( 'static', 'relative', 'absolute', 'fixed', 'sticky' ),
			'overflow'        => array( 'visible', 'hidden', 'auto' ),
			'object-fit'      => array( 'fill', 'cover', 'contain', 'none', 'scale-down' ),
			'justify-content' => array( 'center', 'start', 'end', 'flex-start', 'flex-end', 'left', 'right', 'normal', 'space-between', 'space-around', 'space-evenly', 'stretch' ),
			'justify-items'   => array( 'normal', 'stretch', 'center', 'start', 'end', 'flex-start', 'flex-end', 'left', 'right', 'anchor-center' ),
			'align-content'   => array( 'center', 'start', 'end', 'space-between', 'space-around', 'space-evenly' ),
			'align-items'     => array( 'normal', 'stretch', 'center', 'start', 'end', 'flex-start', 'flex-end', 'self-start', 'self-end', 'anchor-center' ),
			'align-self'      => array( 'auto', 'normal', 'center', 'start', 'end', 'self-start', 'self-end', 'flex-start', 'flex-end', 'anchor-center', 'baseline', 'first baseline', 'last baseline', 'stretch' ),
			'border-style'    => $border_styles,
			'outline-style'   => $border_styles,
			'mix-blend-mode'  => array( 'normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'saturation', 'color', 'difference', 'exclusion', 'hue', 'luminosity', 'soft-light', 'hard-light', 'color-burn' ),
			'cursor'          => array( 'pointer' ),
			'appearance'      => array( 'none', 'auto' ),
			'all'             => array( 'initial', 'inherit', 'unset', 'revert', 'revert-layer' ),
			'aspect-ratio'    => null,
			'clip-path'       => null,
			'content'         => null,
			'grid-auto-flow'  => array( 'row', 'column', 'row dense', 'column dense' ),
		);
	}

	/**
	 * CSS inset properties mapped to Elementor's logical inset prop names.
	 */
	private function get_atomic_inset_props() {
		return array(
			'top'                => 'inset-block-start',
			'right'              => 'inset-inline-end',
			'bottom'             => 'inset-block-end',
			'left'               => 'inset-inline-start',
			'inset-block-start'  => 'inset-block-start',
			'inset-block-end'    => 'inset-block-end',
			'inset-inline-start' => 'inset-inline-start',
			'inset-inline-end'   => 'inset-inline-end',
		);
	}

	/**
	 * If a value is exactly one var(--name) reference, return the variable name.
	 */
	private function get_full_var_name( $value ) {
		if ( preg_match( '/^var\(\s*--([a-zA-Z0-9_-]+)\s*\)$/i', trim( $value ), $m ) ) {
			return strtolower( $m[1] );
		}

		return null;
	}

	/**
	 * Build a placeholder for a var(--x) reference. Resolved to a real Elementor
	 * variable reference at import time (when all variables exist); the fallback
	 * literal is used when no matching variable is found.
	 */
	private function build_variable_marker( $var_name, $fallback ) {
		return array(
			'$$type' => 'wsk-variable-ref',
			'value'  => array(
				'name'     => strtolower( $var_name ),
				'fallback' => $fallback,
			),
		);
	}

	/**
	 * Split a CSS value on top-level commas (ignores commas inside parentheses).
	 */
	private function split_css_comma_list( $value ) {
		$parts = array();
		$current = '';
		$depth = 0;
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];

			if ( '(' === $char ) {
				$depth++;
			} elseif ( ')' === $char ) {
				$depth = max( 0, $depth - 1 );
			}

			if ( ',' === $char && 0 === $depth ) {
				$parts[] = trim( $current );
				$current = '';
				continue;
			}

			$current .= $char;
		}

		if ( '' !== trim( $current ) ) {
			$parts[] = trim( $current );
		}

		return $parts;
	}

	/**
	 * Whether a single CSS token looks like a color value.
	 */
	private function is_color_token( $token ) {
		$token = strtolower( trim( $token ) );

		if ( preg_match( '/^#[0-9a-f]{3,8}$/', $token ) || preg_match( '/^(rgb|rgba|hsl|hsla|oklch|color)\(/', $token ) ) {
			return true;
		}

		// Named colors: purely alphabetic, not a known keyword used in shadows.
		return (bool) preg_match( '/^[a-z]+$/', $token ) && ! in_array( $token, array( 'inset', 'none', 'initial', 'inherit', 'unset' ), true );
	}

	/**
	 * Parse a CSS box-shadow value into Elementor's box-shadow prop.
	 * Returns null when it can't be represented (falls back to custom CSS).
	 */
	private function parse_box_shadow_for_storage( $value ) {
		$shadows = array();

		foreach ( $this->split_css_comma_list( $value ) as $shadow_string ) {
			$tokens = $this->split_css_value_tokens( trim( $shadow_string ) );
			$inset = false;
			$lengths = array();
			$color = null;

			foreach ( $tokens as $token ) {
				if ( 'inset' === strtolower( $token ) ) {
					$inset = true;
				} elseif ( preg_match( '/^[-+]?[\d.]/', $token ) || preg_match( '/^(calc|var|min|max|clamp)\(/i', $token ) ) {
					$lengths[] = $token;
				} elseif ( $this->is_color_token( $token ) ) {
					$color = $token;
				} else {
					return null;
				}
			}

			if ( count( $lengths ) < 2 || count( $lengths ) > 4 ) {
				return null;
			}

			while ( count( $lengths ) < 4 ) {
				$lengths[] = '0px';
			}

			$sizes = array();
			foreach ( $lengths as $length ) {
				$sizes[] = $this->parse_size_for_storage( $length );
			}

			$shadows[] = array(
				'$$type' => 'shadow',
				'value'  => array(
					'hOffset'  => $sizes[0],
					'vOffset'  => $sizes[1],
					'blur'     => $sizes[2],
					'spread'   => $sizes[3],
					'color'    => array(
						'$$type' => 'color',
						'value'  => $color ? $color : 'rgba(0, 0, 0, 1)',
					),
					'position' => $inset ? array( '$$type' => 'string', 'value' => 'inset' ) : null,
				),
			);
		}

		return empty( $shadows ) ? null : array(
			'$$type' => 'box-shadow',
			'value'  => $shadows,
		);
	}

	/**
	 * Parse a CSS transition value into Elementor's transition prop.
	 * Only "all <duration>" (optionally with the default "ease" timing) maps cleanly;
	 * anything more specific keeps its exact behavior via custom CSS instead.
	 */
	private function parse_transition_for_storage( $value ) {
		$entries = array();

		foreach ( $this->split_css_comma_list( $value ) as $transition_string ) {
			$tokens = $this->split_css_value_tokens( trim( $transition_string ) );
			$property = 'all';
			$duration = null;
			$time_values = 0;

			foreach ( $tokens as $token ) {
				$token_lower = strtolower( $token );

				if ( preg_match( '/^[\d.]+(s|ms)$/', $token_lower, $m ) ) {
					$time_values++;

					if ( 1 === $time_values ) {
						$duration = array(
							'size' => (float) $token_lower,
							'unit' => $m[1],
						);
					} else {
						// A delay can't be represented in Elementor's control.
						return null;
					}
				} elseif ( 'ease' === $token_lower ) {
					// CSS default timing; dropping it doesn't change rendering.
					continue;
				} elseif ( preg_match( '/^(linear|ease-in|ease-out|ease-in-out|step-start|step-end)$/', $token_lower ) || preg_match( '/^(cubic-bezier|steps)\(/', $token_lower ) ) {
					// Non-default timing would be lost; keep exact behavior via custom CSS.
					return null;
				} else {
					$property = $token_lower;
				}
			}

			if ( 'all' !== $property || null === $duration ) {
				return null;
			}

			$entries[] = array(
				'$$type' => 'selection-size',
				'value'  => array(
					'selection' => array(
						'$$type' => 'key-value',
						'value'  => array(
							'key'   => array( '$$type' => 'string', 'value' => 'All properties' ),
							'value' => array( '$$type' => 'string', 'value' => 'all' ),
						),
					),
					'size'      => array(
						'$$type' => 'size',
						'value'  => $duration,
					),
				),
			);
		}

		return empty( $entries ) ? null : array(
			'$$type' => 'transition',
			'value'  => $entries,
		);
	}

	/**
	 * Split a CSS value like "translate(10px, 4px) scale(1.1)" into named functions
	 * with their raw argument strings. Handles nested parentheses (rgba, calc).
	 * Returns null when the value contains anything that isn't a function call.
	 */
	private function split_css_functions( $value ) {
		$functions = array();
		$value = trim( $value );
		$length = strlen( $value );
		$i = 0;

		while ( $i < $length ) {
			$char = $value[ $i ];

			if ( ' ' === $char || "\t" === $char || "\n" === $char ) {
				$i++;
				continue;
			}

			if ( ! preg_match( '/[a-zA-Z-]/', $char ) ) {
				return null;
			}

			$start = $i;
			while ( $i < $length && preg_match( '/[a-zA-Z0-9-]/', $value[ $i ] ) ) {
				$i++;
			}
			$name = substr( $value, $start, $i - $start );

			while ( $i < $length && ' ' === $value[ $i ] ) {
				$i++;
			}

			if ( $i >= $length || '(' !== $value[ $i ] ) {
				return null;
			}

			$depth = 0;
			$args_start = $i + 1;

			do {
				if ( '(' === $value[ $i ] ) {
					$depth++;
				} elseif ( ')' === $value[ $i ] ) {
					$depth--;
				}
				$i++;
			} while ( $i < $length && $depth > 0 );

			if ( 0 !== $depth ) {
				return null;
			}

			$functions[] = array(
				'name' => strtolower( $name ),
				'args' => trim( substr( $value, $args_start, $i - $args_start - 1 ) ),
			);
		}

		return empty( $functions ) ? null : $functions;
	}

	/**
	 * Parse a CSS border/outline shorthand ("3px double gold") into the separate
	 * width / style / color props Elementor uses ($prefix = 'border' or 'outline').
	 */
	private function parse_border_shorthand_value( $value, $prefix = 'border' ) {
		$styles = array( 'none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset' );
		$width_keywords = array(
			'thin'   => '1px',
			'medium' => '3px',
			'thick'  => '5px',
		);
		$out = array();

		foreach ( $this->split_css_value_tokens( trim( $value ) ) as $token ) {
			$token_lower = strtolower( trim( $token ) );

			if ( in_array( $token_lower, $styles, true ) ) {
				$out[ $prefix . '-style' ] = array( '$$type' => 'string', 'value' => $token_lower );
			} elseif ( isset( $width_keywords[ $token_lower ] ) ) {
				$out[ $prefix . '-width' ] = $this->parse_size_for_storage( $width_keywords[ $token_lower ] );
			} elseif ( preg_match( '/^[-+]?[\d.]/', $token_lower ) || preg_match( '/^(calc|var|min|max|clamp)\(/', $token_lower ) ) {
				$out[ $prefix . '-width' ] = $this->parse_size_for_storage( $token );
			} elseif ( $this->is_color_token( $token ) ) {
				$out[ $prefix . '-color' ] = array( '$$type' => 'color', 'value' => $token );
			} else {
				return null;
			}
		}

		return empty( $out ) ? null : $out;
	}

	/**
	 * Parse a CSS transform value into Elementor's transform prop.
	 * Supports translate/scale/rotate/skew (and their axis variants); anything
	 * else falls back to custom CSS so behavior is never silently changed.
	 */
	private function parse_transform_for_storage( $value ) {
		$parsed_functions = $this->split_css_functions( $value );

		if ( null === $parsed_functions ) {
			return null;
		}

		$functions = array();

		$size = function( $raw, $default_unit = 'px' ) {
			$raw = trim( $raw );
			if ( '' === $raw ) {
				return array( '$$type' => 'size', 'value' => array( 'size' => 0, 'unit' => $default_unit ) );
			}
			if ( is_numeric( $raw ) ) {
				return array( '$$type' => 'size', 'value' => array( 'size' => $raw + 0, 'unit' => $default_unit ) );
			}
			return $this->parse_size_for_storage( $raw );
		};
		$number = function( $raw, $default = 1 ) {
			$raw = trim( $raw );
			return array( '$$type' => 'number', 'value' => is_numeric( $raw ) ? $raw + 0 : $default );
		};

		foreach ( $parsed_functions as $parsed ) {
			$func = $parsed['name'];
			$args = array_map( 'trim', '' === $parsed['args'] ? array() : explode( ',', $parsed['args'] ) );

			if ( in_array( $func, array( 'translate', 'translatex', 'translatey', 'translatez' ), true ) ) {
				$x = '0px'; $y = '0px'; $z = '0px';
				if ( 'translate' === $func ) {
					$x = $args[0] ?? '0px';
					$y = $args[1] ?? '0px';
				} elseif ( 'translatex' === $func ) {
					$x = $args[0] ?? '0px';
				} elseif ( 'translatey' === $func ) {
					$y = $args[0] ?? '0px';
				} else {
					$z = $args[0] ?? '0px';
				}
				$functions[] = array(
					'$$type' => 'transform-move',
					'value'  => array( 'x' => $size( $x ), 'y' => $size( $y ), 'z' => $size( $z ) ),
				);
			} elseif ( in_array( $func, array( 'scale', 'scalex', 'scaley', 'scalez' ), true ) ) {
				$x = 1; $y = 1; $z = 1;
				if ( 'scale' === $func ) {
					$x = $args[0] ?? 1;
					$y = $args[1] ?? $x;
				} elseif ( 'scalex' === $func ) {
					$x = $args[0] ?? 1;
				} elseif ( 'scaley' === $func ) {
					$y = $args[0] ?? 1;
				} else {
					$z = $args[0] ?? 1;
				}
				$functions[] = array(
					'$$type' => 'transform-scale',
					'value'  => array( 'x' => $number( $x ), 'y' => $number( $y ), 'z' => $number( $z ) ),
				);
			} elseif ( in_array( $func, array( 'rotate', 'rotatex', 'rotatey', 'rotatez' ), true ) ) {
				$x = '0deg'; $y = '0deg'; $z = '0deg';
				if ( 'rotate' === $func || 'rotatez' === $func ) {
					$z = $args[0] ?? '0deg';
				} elseif ( 'rotatex' === $func ) {
					$x = $args[0] ?? '0deg';
				} else {
					$y = $args[0] ?? '0deg';
				}
				$functions[] = array(
					'$$type' => 'transform-rotate',
					'value'  => array( 'x' => $size( $x, 'deg' ), 'y' => $size( $y, 'deg' ), 'z' => $size( $z, 'deg' ) ),
				);
			} elseif ( in_array( $func, array( 'skew', 'skewx', 'skewy' ), true ) ) {
				$x = '0deg'; $y = '0deg';
				if ( 'skew' === $func ) {
					$x = $args[0] ?? '0deg';
					$y = $args[1] ?? '0deg';
				} elseif ( 'skewx' === $func ) {
					$x = $args[0] ?? '0deg';
				} else {
					$y = $args[0] ?? '0deg';
				}
				$functions[] = array(
					'$$type' => 'transform-skew',
					'value'  => array( 'x' => $size( $x, 'deg' ), 'y' => $size( $y, 'deg' ) ),
				);
			} else {
				return null;
			}
		}

		return empty( $functions ) ? null : array(
			'$$type' => 'transform',
			'value'  => array(
				'transform-functions' => array(
					'$$type' => 'transform-functions',
					'value'  => $functions,
				),
			),
		);
	}

	/**
	 * Parse a CSS filter/backdrop-filter value into Elementor's filter props.
	 */
	private function parse_css_filters_for_storage( $value, $prop_type ) {
		$parsed_functions = $this->split_css_functions( $value );

		if ( null === $parsed_functions ) {
			return null;
		}

		$percentage = function( $raw ) {
			$raw = trim( $raw );
			if ( preg_match( '/^([\d.]+)%$/', $raw, $m ) ) {
				return array( '$$type' => 'size', 'value' => array( 'size' => $m[1] + 0, 'unit' => '%' ) );
			}
			if ( is_numeric( $raw ) ) {
				return array( '$$type' => 'size', 'value' => array( 'size' => ( $raw + 0 ) * 100, 'unit' => '%' ) );
			}
			return null;
		};

		$entries = array();

		foreach ( $parsed_functions as $parsed ) {
			$func = $parsed['name'];
			$arg = $parsed['args'];
			$args = null;

			if ( 'blur' === $func ) {
				$args = array(
					'$$type' => 'blur',
					'value'  => array( 'size' => $this->parse_size_for_storage( '' === $arg ? '0px' : $arg ) ),
				);
			} elseif ( in_array( $func, array( 'grayscale', 'sepia', 'invert' ), true ) ) {
				$pct = $percentage( '' === $arg ? '100%' : $arg );
				if ( null === $pct ) {
					return null;
				}
				$args = array( '$$type' => 'color-tone', 'value' => array( 'size' => $pct ) );
			} elseif ( in_array( $func, array( 'brightness', 'contrast', 'saturate' ), true ) ) {
				$pct = $percentage( '' === $arg ? '100%' : $arg );
				if ( null === $pct ) {
					return null;
				}
				$args = array( '$$type' => 'intensity', 'value' => array( 'size' => $pct ) );
			} elseif ( 'hue-rotate' === $func ) {
				$args = array(
					'$$type' => 'hue-rotate',
					'value'  => array( 'size' => $this->parse_size_for_storage( '' === $arg ? '0deg' : $arg ) ),
				);
			} elseif ( 'drop-shadow' === $func ) {
				$lengths = array();
				$color = null;

				foreach ( $this->split_css_value_tokens( $arg ) as $token ) {
					if ( preg_match( '/^[-+]?[\d.]/', $token ) || preg_match( '/^(calc|var|min|max|clamp)\(/i', $token ) ) {
						$lengths[] = $token;
					} elseif ( $this->is_color_token( $token ) ) {
						$color = $token;
					} else {
						return null;
					}
				}

				if ( count( $lengths ) < 2 || count( $lengths ) > 3 ) {
					return null;
				}

				while ( count( $lengths ) < 3 ) {
					$lengths[] = '0px';
				}

				$args = array(
					'$$type' => 'drop-shadow',
					'value'  => array(
						'xAxis' => $this->parse_size_for_storage( $lengths[0] ),
						'yAxis' => $this->parse_size_for_storage( $lengths[1] ),
						'blur'  => $this->parse_size_for_storage( $lengths[2] ),
						'color' => array(
							'$$type' => 'color',
							'value'  => $color ? $color : 'rgba(0, 0, 0, 1)',
						),
					),
				);
			} else {
				return null;
			}

			$entries[] = array(
				'$$type' => 'css-filter-func',
				'value'  => array(
					'func' => array( '$$type' => 'string', 'value' => $func ),
					'args' => $args,
				),
			);
		}

		return empty( $entries ) ? null : array(
			'$$type' => $prop_type,
			'value'  => $entries,
		);
	}

	/**
	 * Parse a CSS linear-gradient(...) into Elementor's gradient overlay entry.
	 */
	private function parse_linear_gradient_for_storage( $value ) {
		if ( ! preg_match( '/^linear-gradient\((.*)\)$/is', trim( $value ), $m ) ) {
			return null;
		}

		$parts = $this->split_css_comma_list( $m[1] );

		if ( empty( $parts ) ) {
			return null;
		}

		$angle = 180; // CSS default: to bottom.
		$first = strtolower( trim( $parts[0] ) );

		if ( preg_match( '/^([\d.]+)deg$/', $first, $am ) ) {
			$angle = $am[1] + 0;
			array_shift( $parts );
		} elseif ( 0 === strpos( $first, 'to ' ) ) {
			$directions = array(
				'to top'    => 0,
				'to right'  => 90,
				'to bottom' => 180,
				'to left'   => 270,
			);
			if ( ! isset( $directions[ $first ] ) ) {
				return null; // Corner directions can't be expressed as a plain angle.
			}
			$angle = $directions[ $first ];
			array_shift( $parts );
		}

		if ( count( $parts ) < 2 ) {
			return null;
		}

		$stops = array();
		$count = count( $parts );

		foreach ( $parts as $index => $stop_string ) {
			$tokens = $this->split_css_value_tokens( trim( $stop_string ) );
			$color = null;
			$offset = null;

			foreach ( $tokens as $token ) {
				if ( preg_match( '/^([\d.]+)%$/', $token, $om ) ) {
					$offset = $om[1] + 0;
				} elseif ( $this->is_color_token( $token ) ) {
					$color = $token;
				} else {
					return null;
				}
			}

			if ( null === $color ) {
				return null;
			}

			if ( null === $offset ) {
				$offset = ( $count > 1 ) ? round( $index * 100 / ( $count - 1 ) ) : 0;
			}

			$stops[] = array(
				'$$type' => 'color-stop',
				'value'  => array(
					'color'  => array( '$$type' => 'color', 'value' => $color ),
					'offset' => array( '$$type' => 'number', 'value' => $offset + 0 ),
				),
			);
		}

		return array(
			'$$type' => 'background-gradient-overlay',
			'value'  => array(
				'type'  => array( '$$type' => 'string', 'value' => 'linear' ),
				'angle' => array( '$$type' => 'number', 'value' => $angle ),
				'stops' => array(
					'$$type' => 'gradient-color-stop',
					'value'  => $stops,
				),
			),
		);
	}

	/**
	 * Parse grid-template-columns/rows. Simple track lists map to Elementor's
	 * grid-track-size; anything else is stored as a custom expression.
	 */
	private function parse_grid_track_for_storage( $value ) {
		$value = trim( $value );

		if ( preg_match( '/^repeat\(\s*(\d+)\s*,\s*1fr\s*\)$/i', $value, $m ) || preg_match( '/^(\d+(?:\.\d+)?)fr$/i', $value, $m ) || preg_match( '/^(\d+)$/', $value, $m ) ) {
			return array(
				'$$type' => 'grid-track-size',
				'value'  => array(
					'size' => $m[1] + 0,
					'unit' => 'fr',
				),
			);
		}

		return array(
			'$$type' => 'grid-track-size',
			'value'  => array(
				'size' => $value,
				'unit' => 'custom',
			),
		);
	}

	/**
	 * Box-model shorthand properties and the Elementor logical side keys they expand into,
	 * in CSS shorthand order (top, right, bottom, left / top-left, top-right, bottom-right, bottom-left).
	 */
	private function get_atomic_box_shorthands() {
		return array(
			'padding'       => array(
				'$$type' => 'dimensions',
				'sides'  => array( 'block-start', 'inline-end', 'block-end', 'inline-start' ),
			),
			'margin'        => array(
				'$$type' => 'dimensions',
				'sides'  => array( 'block-start', 'inline-end', 'block-end', 'inline-start' ),
			),
			'border-width'  => array(
				'$$type' => 'border-width-v2',
				'sides'  => array( 'block-start', 'inline-end', 'block-end', 'inline-start' ),
			),
			'border-radius' => array(
				'$$type' => 'border-radius-v2',
				'sides'  => array( 'start-start', 'start-end', 'end-end', 'end-start' ),
			),
		);
	}

	/**
	 * Longhand box-model properties mapped to their shorthand key and side.
	 */
	private function get_atomic_box_longhands() {
		return array(
			'padding-top'                => array( 'padding', 'block-start' ),
			'padding-right'              => array( 'padding', 'inline-end' ),
			'padding-bottom'             => array( 'padding', 'block-end' ),
			'padding-left'               => array( 'padding', 'inline-start' ),
			'margin-top'                 => array( 'margin', 'block-start' ),
			'margin-right'               => array( 'margin', 'inline-end' ),
			'margin-bottom'              => array( 'margin', 'block-end' ),
			'margin-left'                => array( 'margin', 'inline-start' ),
			'border-top-width'           => array( 'border-width', 'block-start' ),
			'border-right-width'         => array( 'border-width', 'inline-end' ),
			'border-bottom-width'        => array( 'border-width', 'block-end' ),
			'border-left-width'          => array( 'border-width', 'inline-start' ),
			'border-top-left-radius'     => array( 'border-radius', 'start-start' ),
			'border-top-right-radius'    => array( 'border-radius', 'start-end' ),
			'border-bottom-right-radius' => array( 'border-radius', 'end-end' ),
			'border-bottom-left-radius'  => array( 'border-radius', 'end-start' ),
		);
	}

	/**
	 * Split a CSS value into space-separated tokens without breaking values
	 * inside parentheses, e.g. "clamp(1rem, 2vw, 3rem) 1rem" stays 2 tokens.
	 */
	private function split_css_value_tokens( $value ) {
		$tokens = array();
		$current = '';
		$depth = 0;
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];

			if ( '(' === $char ) {
				$depth++;
			} elseif ( ')' === $char ) {
				$depth = max( 0, $depth - 1 );
			}

			if ( ' ' === $char && 0 === $depth ) {
				if ( '' !== $current ) {
					$tokens[] = $current;
					$current = '';
				}
				continue;
			}

			$current .= $char;
		}

		if ( '' !== $current ) {
			$tokens[] = $current;
		}

		return $tokens;
	}

	/**
	 * Build an Elementor Size PropType value, or null if the value can't be represented as one.
	 */
	private function build_atomic_size_value( $value ) {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		// A bare var(--x) is a reference to a size variable, not a custom value.
		// Storing it as a custom unit renders correctly but leaves the control
		// showing a raw CSS function instead of the variable, so it cannot be
		// picked or re-pointed from Elementor's dropdown. Emit a marker here and
		// resolve_variable_markers() turns it into a real variable reference at
		// import time, falling back to the literal when no such variable exists.
		$var_name = $this->get_full_var_name( $value );

		if ( $var_name ) {
			return $this->build_variable_marker( $var_name, $this->parse_size_for_storage( $value ) );
		}

		return $this->parse_size_for_storage( $value );
	}

	/**
	 * Build a Size PropType value for a unitless CSS number.
	 *
	 * "line-height: 1" is unitless in CSS, but Elementor's typography units are
	 * px/em/rem/vw/vh/ch/%/custom with no unitless option, so a bare number
	 * would otherwise be stored as 1px. 'custom' passes the value through
	 * verbatim, which is exactly what unitless needs.
	 */
	private function build_atomic_unitless_value( $value ) {
		return array(
			'$$type' => 'size',
			'value'  => array(
				'size' => trim( $value ),
				'unit' => 'custom',
			),
		);
	}

	/**
	 * Build an Elementor Size PropType value for opacity specifically.
	 * A bare 0-1 decimal ("0.8") is CSS-standard but Elementor's opacity
	 * control expects a 0-100 percentage, so convert it.
	 */
	private function build_atomic_opacity_value( $value ) {
		$value = trim( $value );

		if ( preg_match( '/^(0(\.\d+)?|1(\.0+)?)$/', $value ) ) {
			return array(
				'$$type' => 'size',
				'value'  => array(
					'size' => ( (float) $value ) * 100,
					'unit' => '%',
				),
			);
		}

		return $this->build_atomic_size_value( $value );
	}

	/**
	 * Expand a box-model shorthand value ("10px 20px", etc.) into its logical sides.
	 * Returns null if the value doesn't look like 1-4 usable size tokens.
	 */
	private function expand_box_shorthand_value( $value, $sides ) {
		$tokens = $this->split_css_value_tokens( trim( $value ) );
		$count = count( $tokens );

		if ( $count < 1 || $count > 4 ) {
			return null;
		}

		// CSS shorthand order -> top, right, bottom, left (or the 4-corner equivalent).
		if ( 1 === $count ) {
			$by_position = array( $tokens[0], $tokens[0], $tokens[0], $tokens[0] );
		} elseif ( 2 === $count ) {
			$by_position = array( $tokens[0], $tokens[1], $tokens[0], $tokens[1] );
		} elseif ( 3 === $count ) {
			$by_position = array( $tokens[0], $tokens[1], $tokens[2], $tokens[1] );
		} else {
			$by_position = array( $tokens[0], $tokens[1], $tokens[2], $tokens[3] );
		}

		$result = array();

		foreach ( $sides as $index => $side_key ) {
			$size = $this->build_atomic_size_value( $by_position[ $index ] );

			if ( null === $size ) {
				return null;
			}

			$result[ $side_key ] = $size;
		}

		return $result;
	}

	/**
	 * Convert a block of (already cleaned) CSS declarations into Elementor's structured
	 * atomic style props, falling back to raw custom CSS for anything not (yet) mapped.
	 *
	 * This is what lets an imported class stay editable field-by-field in Elementor's
	 * Style tab, instead of landing only as an opaque Custom CSS block.
	 */
	private function build_atomic_props_from_declarations( $declarations ) {
		$declarations = trim( $declarations );
		$props = array();
		$box_values = array(); // shorthand_key => array( side_key => size_value ).
		$leftover = array();

		$size_props = $this->get_atomic_size_props();
		$color_props = $this->get_atomic_color_props();
		$font_family_props = $this->get_atomic_font_family_props();
		$number_props = $this->get_atomic_number_props();
		$string_props = $this->get_atomic_string_props();
		$box_shorthands = $this->get_atomic_box_shorthands();
		$box_longhands = $this->get_atomic_box_longhands();
		$inset_props = $this->get_atomic_inset_props();
		$background_color = null;
		$background_overlays = array();
		$flex_values = array();

		foreach ( explode( ';', $declarations ) as $declaration ) {
			$declaration = trim( $declaration );

			if ( '' === $declaration || false === strpos( $declaration, ':' ) ) {
				continue;
			}

			list( $property, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) );
			$property = strtolower( $property );

			if ( '' === $property || 0 === strpos( $property, '--' ) || '' === $value ) {
				continue;
			}

			$original_line = rtrim( $declaration, ';' ) . ';';
			$mapped = false;
			$var_name = $this->get_full_var_name( $value );

			if ( isset( $box_shorthands[ $property ] ) ) {
				$shape = $box_shorthands[ $property ];
				$expanded = $this->expand_box_shorthand_value( $value, $shape['sides'] );

				if ( null !== $expanded ) {
					if ( ! isset( $box_values[ $property ] ) ) {
						$box_values[ $property ] = array();
					}
					$box_values[ $property ] = array_merge( $box_values[ $property ], $expanded );
					$mapped = true;
				}
			} elseif ( isset( $box_longhands[ $property ] ) ) {
				list( $shorthand_key, $side_key ) = $box_longhands[ $property ];
				$size = $this->build_atomic_size_value( $value );

				if ( null !== $size ) {
					if ( ! isset( $box_values[ $shorthand_key ] ) ) {
						$box_values[ $shorthand_key ] = array();
					}
					$box_values[ $shorthand_key ][ $side_key ] = $size;
					$mapped = true;
				}
			} elseif ( in_array( $property, $color_props, true ) ) {
				$literal = array(
					'$$type' => 'color',
					'value'  => $value,
				);
				$props[ $property ] = $var_name ? $this->build_variable_marker( $var_name, $literal ) : $literal;
				$mapped = true;
			} elseif ( in_array( $property, $font_family_props, true ) ) {
				// Elementor's font field holds one family, so fallback stacks are dropped:
				// "var(--heading), sans-serif" links to the variable, "Roboto, sans-serif"
				// becomes Roboto (unquoted). The browser fallback is redundant once the
				// value is managed by Elementor anyway.
				$family_parts = $this->split_css_comma_list( $value );
				$family = trim( $family_parts[0] ?? $value );
				$family_var = $this->get_full_var_name( $family );

				if ( ! $family_var ) {
					$family = trim( $family, " \t\n\r\0\x0B\"'" );
				}

				$literal = array(
					'$$type' => 'font-family',
					'value'  => $family,
				);
				$props[ $property ] = $family_var ? $this->build_variable_marker( $family_var, $literal ) : $literal;
				$mapped = true;
			} elseif ( isset( $inset_props[ $property ] ) ) {
				$size = $this->build_atomic_size_value( $value );

				if ( null !== $size ) {
					// build_atomic_size_value() already links a bare var(--x).
					$props[ $inset_props[ $property ] ] = $size;
					$mapped = true;
				}
			} elseif ( 'border' === $property || 'outline' === $property ) {
				$edge = $this->parse_border_shorthand_value( $value, $property );

				if ( null !== $edge ) {
					$props = array_merge( $props, $edge );
					$mapped = true;
				}
			} elseif ( 'gap' === $property || 'row-gap' === $property ) {
				$tokens = $this->split_css_value_tokens( trim( $value ) );

				if ( 'row-gap' === $property ) {
					$row = $this->build_atomic_size_value( $tokens[0] ?? '' );
					if ( null !== $row ) {
						$props['gap'] = array( '$$type' => 'layout-direction', 'value' => array( 'row' => $row ) );
						$mapped = true;
					}
				} elseif ( 1 === count( $tokens ) ) {
					$size = $this->build_atomic_size_value( $tokens[0] );
					if ( null !== $size ) {
						$props['gap'] = $size;
						$mapped = true;
					}
				} elseif ( 2 === count( $tokens ) ) {
					$row = $this->build_atomic_size_value( $tokens[0] );
					$column = $this->build_atomic_size_value( $tokens[1] );
					if ( null !== $row && null !== $column ) {
						$props['gap'] = array( '$$type' => 'layout-direction', 'value' => array( 'row' => $row, 'column' => $column ) );
						$mapped = true;
					}
				}
			} elseif ( '-webkit-text-stroke' === $property || 'text-stroke' === $property ) {
				$stroke = array();
				$stroke_ok = true;

				foreach ( $this->split_css_value_tokens( trim( $value ) ) as $token ) {
					if ( preg_match( '/^[-+]?[\d.]/', $token ) || preg_match( '/^(calc|var|min|max|clamp)\(/i', $token ) ) {
						$stroke['width'] = $this->parse_size_for_storage( $token );
					} elseif ( $this->is_color_token( $token ) ) {
						$stroke['color'] = array( '$$type' => 'color', 'value' => $token );
					} else {
						$stroke_ok = false;
						break;
					}
				}

				if ( $stroke_ok && ! empty( $stroke ) ) {
					$props['stroke'] = array( '$$type' => 'stroke', 'value' => $stroke );
					$mapped = true;
				}
			} elseif ( 'flex' === $property ) {
				$value_lower = strtolower( trim( $value ) );

				if ( 'none' === $value_lower ) {
					$flex_values['flexGrow'] = 0;
					$flex_values['flexShrink'] = 0;
					$flex_values['flexBasis'] = 'auto';
					$mapped = true;
				} else {
					$numbers = array();
					$basis = null;
					$flex_ok = true;

					foreach ( $this->split_css_value_tokens( $value_lower ) as $token ) {
						if ( is_numeric( $token ) ) {
							$numbers[] = $token + 0;
						} elseif ( 'auto' === $token || preg_match( '/^[-+]?[\d.].*[a-z%]/', $token ) || preg_match( '/^(calc|var|min|max|clamp)\(/', $token ) ) {
							$basis = $token;
						} else {
							$flex_ok = false;
							break;
						}
					}

					if ( $flex_ok && ( ! empty( $numbers ) || null !== $basis ) ) {
						$flex_values['flexGrow'] = $numbers[0] ?? 1;
						$flex_values['flexShrink'] = $numbers[1] ?? 1;
						$flex_values['flexBasis'] = null !== $basis ? $basis : ( isset( $numbers[0] ) ? '0%' : 'auto' );
						$mapped = true;
					}
				}
			} elseif ( 'flex-grow' === $property && is_numeric( $value ) ) {
				$flex_values['flexGrow'] = $value + 0;
				$mapped = true;
			} elseif ( 'flex-shrink' === $property && is_numeric( $value ) ) {
				$flex_values['flexShrink'] = $value + 0;
				$mapped = true;
			} elseif ( 'flex-basis' === $property ) {
				$flex_values['flexBasis'] = trim( $value );
				$mapped = true;
			} elseif ( 'grid-column' === $property || 'grid-row' === $property ) {
				if ( ! preg_match( '/;|https?:\/\//i', $value ) ) {
					$props[ $property ] = array( '$$type' => 'span', 'value' => trim( $value ) );
					$mapped = true;
				}
			} elseif ( 'object-position' === $property ) {
				$normalized = strtolower( preg_replace( '/\s+/', ' ', trim( $value ) ) );
				$parts = explode( ' ', $normalized );

				if ( count( $parts ) <= 2 ) {
					$vertical = 'center';
					$horizontal = 'center';
					$keywords_ok = true;

					foreach ( $parts as $part ) {
						if ( 'top' === $part || 'bottom' === $part ) {
							$vertical = $part;
						} elseif ( 'left' === $part || 'right' === $part ) {
							$horizontal = $part;
						} elseif ( 'center' !== $part ) {
							$keywords_ok = false;
							break;
						}
					}

					if ( $keywords_ok ) {
						$props[ $property ] = array( '$$type' => 'string', 'value' => $vertical . ' ' . $horizontal );
						$mapped = true;
					}
				}
			} elseif ( 'box-shadow' === $property ) {
				$shadow = $this->parse_box_shadow_for_storage( $value );

				if ( null !== $shadow ) {
					$props[ $property ] = $shadow;
					$mapped = true;
				}
			} elseif ( 'transition' === $property ) {
				$transition = $this->parse_transition_for_storage( $value );

				if ( null !== $transition ) {
					$props[ $property ] = $transition;
					$mapped = true;
				}
			} elseif ( 'transform' === $property ) {
				$transform = $this->parse_transform_for_storage( $value );

				if ( null !== $transform ) {
					$props[ $property ] = $transform;
					$mapped = true;
				}
			} elseif ( 'filter' === $property || 'backdrop-filter' === $property ) {
				$filter = $this->parse_css_filters_for_storage( $value, $property );

				if ( null !== $filter ) {
					$props[ $property ] = $filter;
					$mapped = true;
				}
			} elseif ( 'background-color' === $property ) {
				$literal = array(
					'$$type' => 'color',
					'value'  => $value,
				);
				$background_color = $var_name ? $this->build_variable_marker( $var_name, $literal ) : $literal;
				$mapped = true;
			} elseif ( 'background' === $property || 'background-image' === $property ) {
				$layers_ok = true;
				$layer_color = null;
				$layer_overlays = array();

				foreach ( $this->split_css_comma_list( $value ) as $layer ) {
					$layer = trim( $layer );
					$layer_var = $this->get_full_var_name( $layer );

					if ( 0 === stripos( $layer, 'linear-gradient(' ) ) {
						$gradient = $this->parse_linear_gradient_for_storage( $layer );

						if ( null === $gradient ) {
							$layers_ok = false;
							break;
						}

						$layer_overlays[] = $gradient;
					} elseif ( 'background' === $property && ( $layer_var || $this->is_color_token( $layer ) ) ) {
						$literal = array(
							'$$type' => 'color',
							'value'  => $layer,
						);
						$layer_color = $layer_var ? $this->build_variable_marker( $layer_var, $literal ) : $literal;
					} else {
						// url(...) images and other backgrounds need a media library ID; keep via custom CSS.
						$layers_ok = false;
						break;
					}
				}

				if ( $layers_ok && ( null !== $layer_color || ! empty( $layer_overlays ) ) ) {
					if ( null !== $layer_color ) {
						$background_color = $layer_color;
					}
					$background_overlays = array_merge( $background_overlays, $layer_overlays );
					$mapped = true;
				}
			} elseif ( 'grid-template-columns' === $property || 'grid-template-rows' === $property ) {
				$props[ $property ] = $this->parse_grid_track_for_storage( $value );
				$mapped = true;
			} elseif ( in_array( $property, $number_props, true ) && is_numeric( $value ) ) {
				$props[ $property ] = array(
					'$$type' => 'number',
					'value'  => $value + 0,
				);
				$mapped = true;
			} elseif ( 'opacity' === $property ) {
				$size = $this->build_atomic_opacity_value( $value );

				if ( null !== $size ) {
					$props[ $property ] = $size;
					$mapped = true;
				}
			} elseif ( in_array( $property, $size_props, true ) ) {
				$size = ( 'line-height' === $property && is_numeric( trim( $value ) ) )
					? $this->build_atomic_unitless_value( $value )
					: $this->build_atomic_size_value( $value );

				if ( null !== $size ) {
					$props[ $property ] = $size;
					$mapped = true;
				}
			} elseif ( array_key_exists( $property, $string_props ) ) {
				$allowed = $string_props[ $property ];
				$normalized = strtolower( trim( $value ) );

				// CSS text-align left/right are called start/end in Elementor.
				if ( 'text-align' === $property ) {
					if ( 'left' === $normalized ) {
						$normalized = 'start';
					} elseif ( 'right' === $normalized ) {
						$normalized = 'end';
					}
				}

				if ( null === $allowed ) {
					$props[ $property ] = array(
						'$$type' => 'string',
						'value'  => $value,
					);
					$mapped = true;
				} elseif ( in_array( $normalized, $allowed, true ) ) {
					$props[ $property ] = array(
						'$$type' => 'string',
						'value'  => $normalized,
					);
					$mapped = true;
				}
				// Values Elementor's schema doesn't allow fall through to custom CSS.
			}

			if ( ! $mapped ) {
				$leftover[] = $original_line;
			}
		}

		foreach ( $box_values as $property => $sides ) {
			// Native Elementor stores a uniform box value flat (one linked field in the
			// editor) and only expands per-side when the sides differ. Match that.
			$unique_sides = array_unique( array_map( 'wp_json_encode', array_values( $sides ) ) );

			if ( 4 === count( $sides ) && 1 === count( $unique_sides ) ) {
				$props[ $property ] = reset( $sides );
				continue;
			}

			$props[ $property ] = array(
				'$$type' => $box_shorthands[ $property ]['$$type'],
				'value'  => $sides,
			);
		}

		if ( ! empty( $flex_values ) ) {
			$flex_prop = array();

			if ( isset( $flex_values['flexGrow'] ) ) {
				$flex_prop['flexGrow'] = array( '$$type' => 'number', 'value' => $flex_values['flexGrow'] );
			}
			if ( isset( $flex_values['flexShrink'] ) ) {
				$flex_prop['flexShrink'] = array( '$$type' => 'number', 'value' => $flex_values['flexShrink'] );
			}
			if ( isset( $flex_values['flexBasis'] ) ) {
				$basis = $flex_values['flexBasis'];
				$flex_prop['flexBasis'] = ( 'auto' === strtolower( (string) $basis ) )
					? array( '$$type' => 'size', 'value' => array( 'size' => '', 'unit' => 'auto' ) )
					: $this->parse_size_for_storage( $basis );
			}

			$props['flex'] = array(
				'$$type' => 'flex',
				'value'  => $flex_prop,
			);
		}

		if ( null !== $background_color || ! empty( $background_overlays ) ) {
			$background_value = array();

			if ( null !== $background_color ) {
				$background_value['color'] = $background_color;
			}

			if ( ! empty( $background_overlays ) ) {
				$background_value['background-overlay'] = array(
					'$$type' => 'background-overlay',
					'value'  => $background_overlays,
				);
			}

			$props['background'] = array(
				'$$type' => 'background',
				'value'  => $background_value,
			);
		}

		return array(
			'props' => $props,
			'raw'   => implode( "\n", $leftover ),
		);
	}

	/**
	 * Build a lookup of active Elementor variables: lowercase label => id + type.
	 */
	private function get_variable_label_lookup() {
		$kit_id = $this->get_kit_id();

		if ( ! $kit_id ) {
			return array();
		}

		$meta = get_post_meta( $kit_id, '_elementor_global_variables', true );

		if ( is_string( $meta ) ) {
			$meta = json_decode( $meta, true );
		}

		if ( ! is_array( $meta ) || empty( $meta['data'] ) ) {
			return array();
		}

		$lookup = array();

		foreach ( $meta['data'] as $id => $variable ) {
			if ( ! empty( $variable['deleted_at'] ) || empty( $variable['label'] ) ) {
				continue;
			}

			$lookup[ strtolower( $variable['label'] ) ] = array(
				'id'   => $id,
				'type' => $variable['type'] ?? 'global-color-variable',
			);
		}

		return $lookup;
	}

	/**
	 * Recursively replace wsk-variable-ref markers with real Elementor variable
	 * references (by ID). Unmatched names fall back to their literal value.
	 */
	private function resolve_variable_markers( $data, $lookup, &$unresolved = array() ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( isset( $data['$$type'] ) && 'wsk-variable-ref' === $data['$$type'] ) {
			$name = $data['value']['name'] ?? '';
			$fallback = $data['value']['fallback'] ?? null;

			// The CSS export prefixes variable names by type; labels are stored without.
			$candidates = array_unique( array(
				$name,
				preg_replace( '/^color-/', '', $name ),
				preg_replace( '/^font-family-/', '', $name ),
				preg_replace( '/^font-size-/', '', $name ),
			) );

			foreach ( $candidates as $candidate ) {
				if ( isset( $lookup[ $candidate ] ) ) {
					return array(
						'$$type' => $this->get_variable_reference_type( $lookup[ $candidate ]['type'] ),
						'value'  => $lookup[ $candidate ]['id'],
					);
				}
			}

			$unresolved[] = $name;

			return $fallback;
		}

		foreach ( $data as $key => $item ) {
			$data[ $key ] = $this->resolve_variable_markers( $item, $lookup, $unresolved );
		}

		return $data;
	}

	/**
	 * The $$type used when referencing a variable from a class prop.
	 */
	private function get_variable_reference_type( $variable_type ) {
		if ( 'global-custom-size-variable' === $variable_type ) {
			return 'global-size-variable';
		}

		return $variable_type;
	}

	/**
	 * Parse a selector into one or more class/state targets Elementor can store.
	 */
	private function parse_class_selector_targets( $selector ) {
		$targets = array();
		$selector = trim( preg_replace( '/\\s+/', ' ', $selector ) );
		$selector = preg_replace( '/^\\.elementor\\s+/', '', $selector );

		foreach ( explode( ',', $selector ) as $part ) {
			$part = trim( $part );
			$part = preg_replace( '/^\\.elementor\\s+/', '', $part );

			if ( preg_match( '/^\\.([a-zA-Z_-][a-zA-Z0-9_-]*)(?::(hover|active|focus|checked|focus-visible))?$/', $part, $m ) ) {
				$state = $m[2] ?? null;

				if ( 'focus-visible' === $state ) {
					$state = 'hover';
				}

				$targets[] = array(
					'label' => $this->name_to_label( $m[1] ),
					'state' => $state,
				);
			}
		}

		return $targets;
	}

	/**
	 * Parse CSS class rules into Elementor global class variants.
	 */
	private function parse_css_classes( $css_string, $media_query = null ) {
		$classes = array();
		$warnings = array();
		$css_string = preg_replace( '/\\/\\*.*?\\*\\//s', '', $css_string );
		$length = strlen( $css_string );
		$offset = 0;
		$breakpoint = null;

		if ( $media_query ) {
			$breakpoint = $this->get_breakpoint_id_from_media_query( $media_query );

			if ( ! $breakpoint ) {
				return array(
					'classes'  => array(),
					'warnings' => array( 'Skipped rules inside unsupported media query ' . $media_query . '.' ),
				);
			}
		}

		while ( $offset < $length && preg_match( '/([^{}]+)\\{/', $css_string, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
			$selector = trim( $match[1][0] );
			$open_pos = $match[0][1] + strlen( $match[0][0] ) - 1;
			$depth = 1;
			$pos = $open_pos + 1;

			while ( $pos < $length && $depth > 0 ) {
				if ( '{' === $css_string[ $pos ] ) {
					$depth++;
				} elseif ( '}' === $css_string[ $pos ] ) {
					$depth--;
				}
				$pos++;
			}

			if ( 0 !== $depth ) {
				$warnings[] = 'Skipped a CSS rule because its braces did not match.';
				break;
			}

			$body = substr( $css_string, $open_pos + 1, $pos - $open_pos - 2 );
			$offset = $pos;

			if ( 0 === strpos( trim( $selector ), '@media' ) ) {
				if ( preg_match( '/@media\\s*(.+)$/', trim( $selector ), $media_match ) ) {
					$nested = $this->parse_css_classes( $body, trim( $media_match[1] ) );
					foreach ( $nested['classes'] as $label => $class ) {
						if ( ! isset( $classes[ $label ] ) ) {
							$classes[ $label ] = $class;
						} else {
							$classes[ $label ]['variants'] = array_merge( $classes[ $label ]['variants'], $class['variants'] );
						}
					}
					$warnings = array_merge( $warnings, $nested['warnings'] );
				}
				continue;
			}

			if ( ':root' === trim( $selector ) ) {
				continue;
			}

			$targets = $this->parse_class_selector_targets( $selector );
			$declarations = $this->clean_declarations( $body );

			if ( empty( $targets ) || '' === $declarations ) {
				if ( false !== strpos( $selector, '.' ) ) {
					$warnings[] = 'Skipped unsupported selector "' . $selector . '".';
				}
				continue;
			}

			$atomic = $this->build_atomic_props_from_declarations( $declarations );

			foreach ( $targets as $target ) {
				$label = $target['label'];

				if ( '' === $label ) {
					continue;
				}

				if ( ! isset( $classes[ $label ] ) ) {
					$classes[ $label ] = array(
						'label'    => $label,
						'variants' => array(),
					);
				}

				$classes[ $label ]['variants'][] = array(
					'props'      => $atomic['props'],
					'custom_css' => '' === $atomic['raw'] ? null : array( 'raw' => base64_encode( $atomic['raw'] ) ),
					'meta'       => array(
						'state'      => $target['state'],
						// Elementor's editor matches variants by breakpoint ID; the base
						// variant must be "desktop", not empty, or the class shows as empty.
						'breakpoint' => $breakpoint ?? 'desktop',
					),
				);
			}
		}

		return array(
			'classes'  => $classes,
			'warnings' => $warnings,
		);
	}

	/**
	 * Detect the format of the pasted input and normalize it into variables/classes.
	 */
	private function parse_import_input( $input ) {
		$input = trim( $input );

		if ( ! preg_match( '/--[a-zA-Z0-9_-]+\\s*:/', $input ) && ! preg_match( '/\\.[a-zA-Z_-][a-zA-Z0-9_-]*\\s*[:,{]/', $input ) ) {
			return new WP_Error( 'invalid_input', 'Paste CSS from Website Stylekit, for example :root { --primary-brand-1: #000; } or .title-1 { font-size: var(--title-size-1); }' );
		}

		$variable_result = $this->parse_css_variables( $input );
		$class_result = $this->parse_css_classes( $input );
		$variables = $variable_result['variables'];
		$classes = $class_result['classes'];
		$warnings = array_merge( $variable_result['warnings'], $class_result['warnings'] );

		if ( empty( $variables ) && empty( $classes ) ) {
			return new WP_Error( 'parse_failed', 'Could not find supported variables or classes. Use tokens like --primary-brand-1: #000; or .title-1 { font-size: var(--title-size-1); }' );
		}

		return array(
			'variables' => $variables,
			'classes'   => $classes,
			'warnings'  => $warnings,
		);
	}

	/**
	 * Parse a size value string into Elementor's storage format.
	 * "1rem" => { "size": 1, "unit": "rem" }
	 * "clamp(...)" => { "size": "clamp(...)", "unit": "custom" }
	 */
	private function parse_size_for_storage( $value ) {
		$value = trim( $value );

		// Clamp, calc, or other function-based values are stored as custom.
		if ( preg_match( '/^(clamp|calc|min|max|var)\s*\(/', $value ) ) {
			return array(
				'$$type' => 'size',
				'value'  => array(
					'size' => $value,
					'unit' => 'custom',
				),
			);
		}

		// "auto"
		if ( 'auto' === strtolower( $value ) ) {
			return array(
				'$$type' => 'size',
				'value'  => array(
					'size' => '',
					'unit' => 'auto',
				),
			);
		}

		// Simple numeric value with unit: "16px", "2rem", "1.5em", "50%"
		if ( preg_match( '/^(-?\d*\.?\d+)\s*(%|[a-z]+)$/i', $value, $m ) ) {
			return array(
				'$$type' => 'size',
				'value'  => array(
					'size' => $m[1] + 0, // Cast to number.
					'unit' => strtolower( $m[2] ),
				),
			);
		}

		// Just a number with no unit, default to px.
		if ( is_numeric( $value ) ) {
			return array(
				'$$type' => 'size',
				'value'  => array(
					'size' => $value + 0,
					'unit' => 'px',
				),
			);
		}

		// Fallback: treat as custom.
		return array(
			'$$type' => 'size',
			'value'  => array(
				'size' => $value,
				'unit' => 'custom',
			),
		);
	}

	/**
	 * Build a lookup of existing active variable labels to their IDs, filtered by type.
	 */
	private function get_existing_label_map( $existing, $elementor_types = array() ) {
		$map = array();

		foreach ( $existing as $id => $var ) {
			if ( isset( $var['deleted_at'] ) ) {
				continue;
			}
			if ( ! empty( $elementor_types ) && ! in_array( $var['type'] ?? '', $elementor_types, true ) ) {
				continue;
			}
			$map[ strtolower( $var['label'] ?? '' ) ] = $id;
		}

		return $map;
	}

	/**
	 * Find the highest order number among ALL active variables (colors, sizes, fonts, etc.).
	 */
	private function get_max_order( $existing ) {
		$max = 0;

		foreach ( $existing as $var ) {
			if ( isset( $var['deleted_at'] ) ) {
				continue;
			}
			$order = $var['order'] ?? 0;
			if ( $order > $max ) {
				$max = $order;
			}
		}

		return $max;
	}

	/**
	 * Build the Elementor V2 storage value for a variable based on its type.
	 */
	private function build_elementor_value( $raw_value, $var_type ) {
		if ( 'sizes' === $var_type ) {
			return $this->parse_size_for_storage( $raw_value );
		}

		if ( 'fonts' === $var_type ) {
			// Trim whitespace only here. A blind trim() of quote characters
			// corrupts a normal font stack like `"Fraunces", Georgia, serif`
			// (it eats the opening quote off the first name). Only unwrap a
			// value that is ENTIRELY one quoted string, e.g. `"Inter"`.
			$font_value = trim( $raw_value, " \t\n\r\0\x0B" );

			if ( strlen( $font_value ) > 1 ) {
				$first_char = $font_value[0];
				$last_char  = $font_value[ strlen( $font_value ) - 1 ];

				if ( ( '"' === $first_char && '"' === $last_char ) || ( "'" === $first_char && "'" === $last_char ) ) {
					$font_value = substr( $font_value, 1, -1 );
				}
			}

			return array(
				'$$type' => 'string',
				'value'  => $font_value,
			);
		}

		// Colors.
		return array(
			'$$type' => 'color',
			'value'  => $raw_value,
		);
	}

	/**
	 * Get the Elementor internal type string for a variable type.
	 */
	private function get_elementor_type( $var_type ) {
		$map = array(
			'colors' => 'global-color-variable',
			'sizes'  => 'global-size-variable',
			'fonts'  => 'global-font-variable',
		);

		return $map[ $var_type ] ?? 'global-color-variable';
	}

	/**
	 * Get all Elementor type strings that belong to a variable type (for filtering).
	 */
	private function get_elementor_types_for_filter( $var_type ) {
		$map = array(
			'colors' => array( 'global-color-variable' ),
			'sizes'  => array( 'global-size-variable', 'global-custom-size-variable' ),
			'fonts'  => array( 'global-font-variable' ),
		);

		return $map[ $var_type ] ?? array( 'global-color-variable' );
	}

	/**
	 * Return the import types present in a parsed variable list.
	 */
	private function get_import_types_in_variables( $variables, $fallback_type = 'colors' ) {
		$types = array();

		foreach ( $variables as $variable ) {
			$type = $variable['type'] ?? $fallback_type;
			if ( in_array( $type, array( 'colors', 'sizes', 'fonts' ), true ) ) {
				$types[ $type ] = true;
			}
		}

		return array_keys( $types );
	}

	/**
	 * Convert parsed variables into Elementor V2 storage format and save.
	 *
	 * Modes:
	 * - "merge"   : Add new variables. If a label already exists, update its value.
	 * - "replace" : Delete all existing variables of this type and import fresh.
	 */
	private function import_variables( $variables, $mode = 'merge', $var_type = 'colors' ) {
		$kit_id = $this->get_kit_id();

		if ( ! $kit_id ) {
			return new WP_Error( 'no_kit', 'No active Elementor kit found. Make sure Elementor is installed and active.' );
		}

		if ( empty( $variables ) ) {
			return array(
				'imported' => 0,
				'updated'  => 0,
				'total'    => 0,
				'counts'   => array(),
			);
		}

		// Read existing data.
		$meta = get_post_meta( $kit_id, '_elementor_global_variables', true );
		$existing = array();
		$watermark = 0;

		if ( ! empty( $meta ) ) {
			if ( is_string( $meta ) ) {
				$meta = json_decode( $meta, true );
			}
			if ( is_array( $meta ) ) {
				$existing = $meta['data'] ?? array();
				$watermark = $meta['watermark'] ?? 0;
			}
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		$import_types = $this->get_import_types_in_variables( $variables, $var_type );
		$label_maps = array();
		$imported = 0;
		$updated = 0;
		$counts = array();
		$names = array();

		foreach ( $import_types as $import_type ) {
			$label_maps[ $import_type ] = $this->get_existing_label_map( $existing, $this->get_elementor_types_for_filter( $import_type ) );
			$counts[ $import_type ] = array(
				'imported' => 0,
				'updated'  => 0,
			);
			$names[ $import_type ] = array(
				'imported' => array(),
				'updated'  => array(),
			);
		}

		if ( 'replace' === $mode ) {
			// Soft-delete all existing variables of the imported types.
			foreach ( $existing as $id => &$var ) {
				foreach ( $import_types as $import_type ) {
					if ( isset( $var['type'] ) && in_array( $var['type'], $this->get_elementor_types_for_filter( $import_type ), true ) && ! isset( $var['deleted_at'] ) ) {
						$var['deleted_at'] = $now;
						break;
					}
				}
			}
			unset( $var );

			foreach ( $import_types as $import_type ) {
				$label_maps[ $import_type ] = array();
			}
		}

		// Determine starting order for new variables.
		$next_order = ( 'replace' === $mode ) ? 1 : $this->get_max_order( $existing ) + 1;

		foreach ( $variables as $variable ) {
			$current_type = $variable['type'] ?? $var_type;

			if ( ! in_array( $current_type, array( 'colors', 'sizes', 'fonts' ), true ) ) {
				continue;
			}

			$label = $variable['label'];
			$elementor_type = $this->get_elementor_type( $current_type );
			$label_map = $label_maps[ $current_type ] ?? array();
			$existing_id = $label_map[ strtolower( $label ) ] ?? null;
			$storage_value = $this->build_elementor_value( $variable['value'], $current_type );

			if ( 'merge' === $mode && $existing_id ) {
				// Update the existing variable's value.
				$existing[ $existing_id ]['value'] = $storage_value;
				$existing[ $existing_id ]['updated_at'] = $now;
				$updated++;
				$counts[ $current_type ]['updated']++;
				$names[ $current_type ]['updated'][] = $label;

			} else {
				// New variable (all modes).
				$new_id = $variable['id'] ?? $this->generate_variable_id();
				$order = ( 'replace' === $mode ) ? $variable['order'] : $next_order++;

				$existing[ $new_id ] = array(
					'type'       => $elementor_type,
					'label'      => $label,
					'value'      => $storage_value,
					'order'      => $order,
					'created_at' => $now,
					'updated_at' => $now,
				);

				$label_maps[ $current_type ][ strtolower( $label ) ] = $new_id;
				$imported++;
				$counts[ $current_type ]['imported']++;
				$names[ $current_type ]['imported'][] = $label;
			}
		}

		$watermark++;

		$record = array(
			'data'      => $existing,
			'watermark' => $watermark,
			'version'   => 2,
		);

		$result = update_post_meta(
			$kit_id,
			'_elementor_global_variables',
			wp_slash( wp_json_encode( $record ) )
		);

		if ( false === $result ) {
			return new WP_Error( 'save_failed', 'Failed to save variables to the database.' );
		}

		return array(
			'imported' => $imported,
			'updated'  => $updated,
			'total'    => $imported + $updated,
			'counts'   => $counts,
			'names'    => $names,
		);
	}

	/**
	 * Read Elementor global classes from the active kit.
	 */
	private function get_existing_global_classes() {
		$repository_class = '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';

		if ( ! class_exists( $repository_class ) ) {
			return new WP_Error( 'classes_unavailable', 'Elementor global classes are not available on this site.' );
		}

		try {
			$data = $repository_class::make()->all()->get();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'classes_read_failed', 'Could not read Elementor global classes: ' . $e->getMessage() );
		}

		return array(
			'items' => $data['items'] ?? array(),
			'order' => $data['order'] ?? array(),
		);
	}

	/**
	 * Build a lookup of existing class labels to class IDs.
	 */
	private function get_existing_class_label_map( $items ) {
		$map = array();

		foreach ( $items as $id => $item ) {
			if ( empty( $item['label'] ) ) {
				continue;
			}

			$map[ strtolower( $this->name_to_label( $item['label'] ) ) ] = $id;
		}

		return $map;
	}

	/**
	 * Create an Elementor global class item from parsed CSS.
	 */
	private function build_class_item( $class_id, $class_data ) {
		// Match the exact item shape of Elementor's own design system export:
		// id, label, type, variants. No extra keys.
		return array(
			'id'       => $class_id,
			'label'    => $class_data['label'],
			'type'     => 'class',
			'variants' => $class_data['variants'],
		);
	}

	/**
	 * Save parsed CSS classes into Elementor global classes.
	 */
	private function import_classes( $classes, $mode = 'merge' ) {
		if ( empty( $classes ) ) {
			return array(
				'imported' => 0,
				'updated'  => 0,
				'total'    => 0,
			);
		}

		$repository_class = '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';
		$parser_class = '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Parser';

		if ( ! class_exists( $repository_class ) || ! class_exists( $parser_class ) ) {
			return new WP_Error( 'classes_unavailable', 'Elementor global classes are not available on this site.' );
		}

		$existing = $this->get_existing_global_classes();

		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$items = ( 'replace' === $mode ) ? array() : ( $existing['items'] ?? array() );
		$order = ( 'replace' === $mode ) ? array() : ( $existing['order'] ?? array() );
		$label_map = ( 'replace' === $mode ) ? array() : $this->get_existing_class_label_map( $items );
		$imported = 0;
		$updated = 0;
		$names = array(
			'imported' => array(),
			'updated'  => array(),
		);
		$warnings = array();

		// Resolve var(--x) references now that all variables (including ones from
		// this same import) exist in Elementor's storage.
		$variable_lookup = $this->get_variable_label_lookup();
		$unresolved = array();

		foreach ( $classes as $class_key => $class ) {
			foreach ( $class['variants'] as $variant_index => $variant ) {
				if ( ! empty( $variant['props'] ) ) {
					$classes[ $class_key ]['variants'][ $variant_index ]['props'] = $this->resolve_variable_markers( $variant['props'], $variable_lookup, $unresolved );
				}
			}
		}

		foreach ( array_unique( $unresolved ) as $name ) {
			$warnings[] = 'No Elementor variable found for --' . $name . '; kept the literal var() value.';
		}

		foreach ( $classes as $class ) {
			$label = $class['label'];
			$existing_id = $label_map[ strtolower( $label ) ] ?? null;

			if ( $existing_id ) {
				$items[ $existing_id ] = $this->build_class_item( $existing_id, $class );
				$updated++;
				$names['updated'][] = $label;
				continue;
			}

			$new_id = $this->generate_class_id( array_keys( $items ) );
			$items[ $new_id ] = $this->build_class_item( $new_id, $class );
			$order[] = $new_id;
			$label_map[ strtolower( $label ) ] = $new_id;
			$imported++;
			$names['imported'][] = $label;
		}

		$parsed = $parser_class::make()->parse(
			array(
				'items' => $items,
				'order' => $order,
			)
		);

		if ( ! $parsed->is_valid() ) {
			$details = method_exists( $parsed->errors(), 'to_string' ) ? $parsed->errors()->to_string() : '';

			return new WP_Error( 'classes_invalid', 'Elementor rejected one or more imported classes. ' . ( $details ? 'Details: ' . $details : 'Check for unsupported class names or CSS.' ) );
		}

		$validated = $parsed->unwrap();

		try {
			// Write the live ("frontend") context.
			$repository_class::make()->put( $validated['items'], $validated['order'] );

			// Elementor keeps a second copy of global classes for the editor panel
			// (the "preview"/draft context). Write that too, otherwise the editor
			// keeps showing its stale draft and the imported classes stay invisible.
			$preview_repository = $repository_class::make();

			if ( method_exists( $preview_repository, 'set_preview' ) ) {
				$preview_repository->set_preview( true )->put( $validated['items'], $validated['order'] );
			}
		} catch ( \Throwable $e ) {
			return new WP_Error( 'classes_save_failed', 'Failed to save Elementor global classes: ' . $e->getMessage() );
		}

		// Regenerate Elementor's compiled CSS so imported classes render without a manual cache clear.
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			} catch ( \Throwable $e ) {
				// Cache clear is best-effort; the import itself already succeeded.
				unset( $e );
			}
		}

		return array(
			'imported' => $imported,
			'updated'  => $updated,
			'total'    => $imported + $updated,
			'names'    => $names,
			'warnings' => $warnings,
		);
	}

	/**
	 * Read Website Stylekit backups from the active Elementor kit.
	 */
	private function get_backups() {
		$kit_id = $this->get_kit_id();

		if ( ! $kit_id ) {
			return array();
		}

		$backups = get_post_meta( $kit_id, self::BACKUPS_META_KEY, true );

		if ( is_string( $backups ) ) {
			$backups = json_decode( $backups, true );
		}

		return is_array( $backups ) ? $backups : array();
	}

	/**
	 * Save Website Stylekit backups on the active Elementor kit.
	 */
	private function save_backups( $backups ) {
		$kit_id = $this->get_kit_id();

		if ( ! $kit_id ) {
			return new WP_Error( 'no_kit', 'No active Elementor kit found.' );
		}

		return update_post_meta( $kit_id, self::BACKUPS_META_KEY, wp_slash( wp_json_encode( array_values( $backups ) ) ) );
	}

	/**
	 * Count active variables in a stored Elementor variables payload.
	 */
	private function count_backup_variables( $variables ) {
		$data = $variables['data'] ?? array();
		$count = 0;

		foreach ( $data as $variable ) {
			if ( empty( $variable['deleted_at'] ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Count global classes in a stored Elementor classes payload.
	 */
	private function count_backup_classes( $classes ) {
		return count( $classes['items'] ?? array() );
	}

	/**
	 * Create a snapshot of current Elementor variables and global classes.
	 */
	private function create_backup( $label = 'Manual backup', $mode = 'manual' ) {
		$kit_id = $this->get_kit_id();

		if ( ! $kit_id ) {
			return new WP_Error( 'no_kit', 'No active Elementor kit found.' );
		}

		$variables = get_post_meta( $kit_id, '_elementor_global_variables', true );

		if ( is_string( $variables ) ) {
			$variables = json_decode( $variables, true );
		}

		if ( ! is_array( $variables ) ) {
			$variables = array(
				'data'      => array(),
				'watermark' => 0,
				'version'   => 2,
			);
		}

		$classes = $this->get_existing_global_classes();

		if ( is_wp_error( $classes ) ) {
			$classes = array(
				'items' => array(),
				'order' => array(),
			);
		}

		$backup = array(
			'id'             => 'wsk-' . substr( md5( wp_generate_uuid4() ), 0, 12 ),
			'created_at'     => current_time( 'mysql' ),
			'timestamp'      => time(),
			'label'          => sanitize_text_field( $label ),
			'mode'           => sanitize_text_field( $mode ),
			'variables'      => $variables,
			'classes'        => $classes,
			'default_styles' => $this->get_elementor_default_styles_snapshot(),
			'variable_count' => $this->count_backup_variables( $variables ),
			'class_count'    => $this->count_backup_classes( $classes ),
		);

		$backups = $this->get_backups();
		array_unshift( $backups, $backup );
		$backups = array_slice( $backups, 0, self::MAX_BACKUPS );

		$saved = $this->save_backups( $backups );

		if ( false === $saved ) {
			return new WP_Error( 'backup_failed', 'Failed to save backup.' );
		}

		return $backup;
	}

	/**
	 * Add an uploaded backup file to the active kit's backup list.
	 */
	private function import_uploaded_backup( $backup ) {
		if ( ! is_array( $backup ) ) {
			return new WP_Error( 'invalid_backup', 'This backup file could not be read.' );
		}

		if ( empty( $backup['variables'] ) || ! is_array( $backup['variables'] ) || empty( $backup['classes'] ) || ! is_array( $backup['classes'] ) ) {
			return new WP_Error( 'invalid_backup', 'This does not look like a Website Stylekit backup file.' );
		}

		// Bricks backup files go into the Bricks backup store, keeping their own shape.
		if ( 'bricks' === ( $backup['target'] ?? '' ) ) {
			$imported = array(
				'id'                  => 'wskb-' . substr( md5( wp_generate_uuid4() ), 0, 12 ),
				'created_at'          => current_time( 'mysql' ),
				'timestamp'           => time(),
				'label'               => sanitize_text_field( $backup['label'] ?? 'Uploaded backup' ),
				'mode'                => 'uploaded backup',
				'target'              => 'bricks',
				'variables'           => $backup['variables'],
				'variable_categories' => is_array( $backup['variable_categories'] ?? null ) ? $backup['variable_categories'] : array(),
				'classes'             => $backup['classes'],
				'class_categories'    => is_array( $backup['class_categories'] ?? null ) ? $backup['class_categories'] : array(),
				'variable_count'      => count( $backup['variables'] ),
				'class_count'         => count( $backup['classes'] ),
			);

			$backups = $this->get_bricks_backups();
			array_unshift( $backups, $imported );
			$backups = array_slice( $backups, 0, self::MAX_BACKUPS );
			update_option( self::BRICKS_BACKUPS_OPTION, $backups, false );

			return $imported;
		}

		$imported = array(
			'id'             => 'wsk-' . substr( md5( wp_generate_uuid4() ), 0, 12 ),
			'created_at'     => current_time( 'mysql' ),
			'timestamp'      => time(),
			'label'          => sanitize_text_field( $backup['label'] ?? 'Uploaded backup' ),
			'mode'           => 'uploaded backup',
			'variables'      => $backup['variables'],
			'classes'        => $backup['classes'],
			'variable_count' => $this->count_backup_variables( $backup['variables'] ),
			'class_count'    => $this->count_backup_classes( $backup['classes'] ),
		);

		$backups = $this->get_backups();
		array_unshift( $backups, $imported );
		$backups = array_slice( $backups, 0, self::MAX_BACKUPS );

		$saved = $this->save_backups( $backups );

		if ( false === $saved ) {
			return new WP_Error( 'backup_failed', 'Failed to save uploaded backup.' );
		}

		return $imported;
	}

	/**
	 * Find one stored backup by ID.
	 */
	private function find_backup( $backup_id ) {
		foreach ( $this->get_backups() as $backup ) {
			if ( isset( $backup['id'] ) && $backup_id === $backup['id'] ) {
				return $backup;
			}
		}

		foreach ( $this->get_bricks_backups() as $backup ) {
			if ( isset( $backup['id'] ) && $backup_id === $backup['id'] ) {
				return $backup;
			}
		}

		return false;
	}

	/**
	 * Restore variables and classes from a backup.
	 */
	private function restore_backup( $backup_id ) {
		$kit_id = $this->get_kit_id();
		$backup = $this->find_backup( $backup_id );

		if ( ! $kit_id ) {
			return new WP_Error( 'no_kit', 'No active Elementor kit found.' );
		}

		if ( ! $backup ) {
			return new WP_Error( 'missing_backup', 'Backup not found.' );
		}

		$variables = $backup['variables'] ?? array(
			'data'      => array(),
			'watermark' => 0,
			'version'   => 2,
		);

		update_post_meta( $kit_id, '_elementor_global_variables', wp_slash( wp_json_encode( $variables ) ) );

		$repository_class = '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';
		$classes = $backup['classes'] ?? array();

		if ( class_exists( $repository_class ) && is_array( $classes ) ) {
			try {
				$repository_class::make()->put( $classes['items'] ?? array(), $classes['order'] ?? array() );
			} catch ( Exception $e ) {
				return new WP_Error( 'classes_restore_failed', 'Variables were restored, but Elementor classes could not be restored.' );
			}
		}

		// Older backups (before 2.6) carry no default styles; leave those untouched.
		if ( array_key_exists( 'default_styles', $backup ) ) {
			$this->restore_elementor_default_styles( $backup['default_styles'] );
		}

		return $backup;
	}

	/**
	 * Delete a stored backup.
	 */
	private function delete_backup( $backup_id ) {
		$backups = $this->get_backups();
		$filtered = array_values( array_filter( $backups, function( $backup ) use ( $backup_id ) {
			return ( $backup['id'] ?? '' ) !== $backup_id;
		} ) );

		if ( count( $filtered ) === count( $backups ) ) {
			return new WP_Error( 'missing_backup', 'Backup not found.' );
		}

		$saved = $this->save_backups( $filtered );

		if ( false === $saved ) {
			return new WP_Error( 'delete_failed', 'Failed to delete backup.' );
		}

		return true;
	}

	/**
	 * Convert a backup snapshot into readable CSS for preview.
	 */
	private function build_backup_css_preview( $backup ) {
		if ( 'bricks' === ( $backup['target'] ?? '' ) ) {
			return $this->build_bricks_backup_css_preview( $backup );
		}

		$clean_variables = array();
		$variables = $backup['variables']['data'] ?? array();

		foreach ( $variables as $id => $variable ) {
			if ( ! empty( $variable['deleted_at'] ) ) {
				continue;
			}

			$type = $variable['type'] ?? '';

			$clean_variables[ $id ] = array(
				'type'  => $type,
				'label' => $variable['label'] ?? '',
				'value' => $this->extract_clean_value( $variable['value'] ?? '', $type ),
				'order' => $variable['order'] ?? 0,
			);
		}

		uasort( $clean_variables, function( $a, $b ) {
			return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
		} );

		$css_parts = array();

		if ( ! empty( $clean_variables ) ) {
			$css_parts[] = '/* Variables */' . "\n" . $this->build_css_variables_output( $clean_variables );
		}

		$classes_css = $this->render_global_classes_css( $backup['classes'] ?? array() );

		if ( '' !== $classes_css ) {
			$css_parts[] = '/* Elementor global classes */' . "\n" . $this->format_css_output( $classes_css );
		}

		return implode( "\n\n", $css_parts );
	}

	/**
	 * Create a preview summary without changing Elementor.
	 */
	private function preview_import( $parsed, $mode = 'merge' ) {
		$variables = $parsed['variables'] ?? array();
		$classes = $parsed['classes'] ?? array();
		$warnings = $parsed['warnings'] ?? array();
		$counts = array(
			'colors'  => array( 'imported' => 0, 'updated' => 0 ),
			'fonts'   => array( 'imported' => 0, 'updated' => 0 ),
			'sizes'   => array( 'imported' => 0, 'updated' => 0 ),
			'classes' => array( 'imported' => 0, 'updated' => 0 ),
		);
		$names = array(
			'colors'  => array( 'imported' => array(), 'updated' => array() ),
			'fonts'   => array( 'imported' => array(), 'updated' => array() ),
			'sizes'   => array( 'imported' => array(), 'updated' => array() ),
			'classes' => array( 'imported' => array(), 'updated' => array() ),
		);

		$kit_id = $this->get_kit_id();
		$existing_variables = array();

		if ( $kit_id ) {
			$meta = get_post_meta( $kit_id, '_elementor_global_variables', true );

			if ( is_string( $meta ) ) {
				$meta = json_decode( $meta, true );
			}

			if ( is_array( $meta ) ) {
				$existing_variables = $meta['data'] ?? array();
			}
		}

		$variable_maps = array();
		foreach ( $this->get_import_types_in_variables( $variables ) as $type ) {
			$variable_maps[ $type ] = ( 'replace' === $mode ) ? array() : $this->get_existing_label_map( $existing_variables, $this->get_elementor_types_for_filter( $type ) );
		}

		foreach ( $variables as $variable ) {
			$type = $variable['type'] ?? 'colors';
			$label = $variable['label'] ?? '';
			$label_key = strtolower( $label );

			if ( 'merge' === $mode && isset( $variable_maps[ $type ][ $label_key ] ) ) {
				$counts[ $type ]['updated']++;
				$names[ $type ]['updated'][] = $label;
			} else {
				$counts[ $type ]['imported']++;
				$names[ $type ]['imported'][] = $label;
			}
		}

		$existing_classes = $this->get_existing_global_classes();

		if ( is_wp_error( $existing_classes ) ) {
			if ( ! empty( $classes ) ) {
				$warnings[] = $existing_classes->get_error_message();
			}
			$class_map = array();
		} else {
			$class_map = ( 'replace' === $mode ) ? array() : $this->get_existing_class_label_map( $existing_classes['items'] ?? array() );
		}

		foreach ( $classes as $class ) {
			$label = $class['label'] ?? '';
			$label_key = strtolower( $label );

			if ( 'merge' === $mode && isset( $class_map[ $label_key ] ) ) {
				$counts['classes']['updated']++;
				$names['classes']['updated'][] = $label;
			} else {
				$counts['classes']['imported']++;
				$names['classes']['imported'][] = $label;
			}
		}

		return array(
			'imported' => $counts['colors']['imported'] + $counts['fonts']['imported'] + $counts['sizes']['imported'] + $counts['classes']['imported'],
			'updated'  => $counts['colors']['updated'] + $counts['fonts']['updated'] + $counts['sizes']['updated'] + $counts['classes']['updated'],
			'total'    => array_sum( array_map( function( $count ) {
				return $count['imported'] + $count['updated'];
			}, $counts ) ),
			'counts'   => $counts,
			'names'    => $names,
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * AJAX handler for importing variables.
	 */
	public function ajax_import_variables() {
		check_ajax_referer( 'wsk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$json_input = isset( $_POST['json_input'] ) ? sanitize_textarea_field( wp_unslash( $_POST['json_input'] ) ) : '';
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'merge';
		$mode = in_array( $mode, array( 'merge', 'replace' ), true ) ? $mode : 'merge';

		if ( empty( $json_input ) ) {
			wp_send_json_error( 'No input provided.' );
		}

		$dry_run = ! empty( $_POST['dry_run'] );

		$builder = isset( $_POST['builder'] ) ? sanitize_text_field( wp_unslash( $_POST['builder'] ) ) : 'elementor';
		$builder = in_array( $builder, array( 'elementor', 'bricks' ), true ) ? $builder : 'elementor';

		if ( 'bricks' === $builder ) {
			$this->handle_bricks_import( $json_input, $mode, $dry_run, ! empty( $_POST['rem_fix'] ), ! empty( $_POST['palette_sync'] ), ! empty( $_POST['font_defaults'] ) );
			return;
		}

		$parsed = $this->parse_import_input( $json_input );

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( $parsed->get_error_message() );
		}

		if ( $dry_run ) {
			wp_send_json_success( $this->preview_import( $parsed, $mode ) );
		}

		try {
			$backup = $this->create_backup( 'Before stylekit import', $mode . ' import' );

			if ( is_wp_error( $backup ) ) {
				wp_send_json_error( $backup->get_error_message() );
			}

			$variable_result = $this->import_variables( $parsed['variables'], $mode );

			if ( is_wp_error( $variable_result ) ) {
				wp_send_json_error( $variable_result->get_error_message() );
			}

			$class_result = $this->import_classes( $parsed['classes'], $mode );

			if ( is_wp_error( $class_result ) ) {
				wp_send_json_error( $class_result->get_error_message() );
			}

			$defaults_result = $this->sync_elementor_default_styles( $parsed['classes'], $parsed['variables'] );
		} catch ( \Throwable $e ) {
			wp_send_json_error( 'Unexpected error during import: ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
		}

		$result = $this->preview_import( $parsed, $mode );
		$result['counts']['colors'] = $variable_result['counts']['colors'] ?? array( 'imported' => 0, 'updated' => 0 );
		$result['counts']['fonts'] = $variable_result['counts']['fonts'] ?? array( 'imported' => 0, 'updated' => 0 );
		$result['counts']['sizes'] = $variable_result['counts']['sizes'] ?? array( 'imported' => 0, 'updated' => 0 );
		$result['counts']['classes'] = array(
			'imported' => $class_result['imported'] ?? 0,
			'updated'  => $class_result['updated'] ?? 0,
		);
		$result['names']['colors'] = $variable_result['names']['colors'] ?? array( 'imported' => array(), 'updated' => array() );
		$result['names']['fonts'] = $variable_result['names']['fonts'] ?? array( 'imported' => array(), 'updated' => array() );
		$result['names']['sizes'] = $variable_result['names']['sizes'] ?? array( 'imported' => array(), 'updated' => array() );
		$result['names']['classes'] = $class_result['names'] ?? array( 'imported' => array(), 'updated' => array() );
		$result['imported'] = ( $variable_result['imported'] ?? 0 ) + ( $class_result['imported'] ?? 0 );
		$result['updated'] = ( $variable_result['updated'] ?? 0 ) + ( $class_result['updated'] ?? 0 );
		$result['total'] = $result['imported'] + $result['updated'];

		if ( ! empty( $class_result['warnings'] ) ) {
			$result['warnings'] = array_values( array_unique( array_merge( $result['warnings'] ?? array(), $class_result['warnings'] ) ) );
		}

		if ( ! empty( $defaults_result['changes'] ) ) {
			$result['changes'] = array_values( array_unique( array_merge( $result['changes'] ?? array(), $defaults_result['changes'] ) ) );
		}

		if ( ! empty( $defaults_result['warnings'] ) ) {
			$result['warnings'] = array_values( array_unique( array_merge( $result['warnings'] ?? array(), $defaults_result['warnings'] ) ) );
		}

		$result['backup'] = array(
			'id'    => $backup['id'],
			'label' => $backup['label'],
		);

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for creating a manual backup.
	 */
	public function ajax_create_backup() {
		check_ajax_referer( 'wsk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$target = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : 'elementor';

		if ( 'bricks' === $target ) {
			if ( ! $this->is_bricks_active() ) {
				wp_send_json_error( 'Bricks is not the active theme on this site.' );
			}
			$backup = $this->create_bricks_backup( 'Manual backup', 'manual' );
		} else {
			$backup = $this->create_backup( 'Manual backup', 'manual' );
		}

		if ( is_wp_error( $backup ) ) {
			wp_send_json_error( $backup->get_error_message() );
		}

		wp_send_json_success( array(
			'id' => $backup['id'],
		) );
	}

	/**
	 * AJAX handler for restoring a backup.
	 */
	public function ajax_restore_backup() {
		check_ajax_referer( 'wsk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$backup_id = isset( $_POST['backup_id'] ) ? sanitize_text_field( wp_unslash( $_POST['backup_id'] ) ) : '';
		$result = $this->is_bricks_backup_id( $backup_id ) ? $this->restore_bricks_backup( $backup_id ) : $this->restore_backup( $backup_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array(
			'id' => $backup_id,
		) );
	}

	/**
	 * AJAX handler for deleting a backup.
	 */
	public function ajax_delete_backup() {
		check_ajax_referer( 'wsk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$backup_id = isset( $_POST['backup_id'] ) ? sanitize_text_field( wp_unslash( $_POST['backup_id'] ) ) : '';
		$result = $this->is_bricks_backup_id( $backup_id ) ? $this->delete_bricks_backup( $backup_id ) : $this->delete_backup( $backup_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array(
			'id' => $backup_id,
		) );
	}

	/**
	 * AJAX handler for downloading a backup as JSON.
	 */
	public function ajax_download_backup() {
		check_ajax_referer( 'wsk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$backup_id = isset( $_POST['backup_id'] ) ? sanitize_text_field( wp_unslash( $_POST['backup_id'] ) ) : '';
		$backup = $this->find_backup( $backup_id );

		if ( ! $backup ) {
			wp_send_json_error( 'Backup not found.' );
		}

		wp_send_json_success( $backup );
	}

	/**
	 * AJAX handler for viewing a backup as readable CSS.
	 */
	public function ajax_view_backup() {
		check_ajax_referer( 'wsk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$backup_id = isset( $_POST['backup_id'] ) ? sanitize_text_field( wp_unslash( $_POST['backup_id'] ) ) : '';
		$backup = $this->find_backup( $backup_id );

		if ( ! $backup ) {
			wp_send_json_error( 'Backup not found.' );
		}

		wp_send_json_success( array(
			'id'             => $backup['id'] ?? '',
			'label'          => $backup['label'] ?? 'Backup',
			'created_at'     => $backup['created_at'] ?? '',
			'mode'           => $backup['mode'] ?? '',
			'variable_count' => (int) ( $backup['variable_count'] ?? 0 ),
			'class_count'    => (int) ( $backup['class_count'] ?? 0 ),
			'css'            => $this->build_backup_css_preview( $backup ),
		) );
	}

	/**
	 * AJAX handler for uploading a backup JSON file.
	 */
	public function ajax_upload_backup() {
		check_ajax_referer( 'wsk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$backup_json = isset( $_POST['backup_json'] ) ? wp_unslash( $_POST['backup_json'] ) : '';

		if ( '' === trim( $backup_json ) ) {
			wp_send_json_error( 'No backup file data found.' );
		}

		if ( strlen( $backup_json ) > 2 * 1024 * 1024 ) {
			wp_send_json_error( 'This backup file is too large.' );
		}

		$backup = json_decode( $backup_json, true );

		if ( ! is_array( $backup ) ) {
			wp_send_json_error( 'This backup file is not valid JSON.' );
		}

		$result = $this->import_uploaded_backup( $backup );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array(
			'id' => $result['id'],
		) );
	}

	/**
	 * Reorder all active variables: group by type, then assign sequential order numbers.
	 * Order: colors first, then fonts, then sizes.
	 */
	public function ajax_reorder_variables() {
		check_ajax_referer( 'wsk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$kit_id = $this->get_kit_id();

		if ( ! $kit_id ) {
			wp_send_json_error( 'No active Elementor kit found.' );
		}

		$meta = get_post_meta( $kit_id, '_elementor_global_variables', true );

		if ( empty( $meta ) ) {
			wp_send_json_error( 'No variables found.' );
		}

		if ( is_string( $meta ) ) {
			$meta = json_decode( $meta, true );
		}

		if ( ! is_array( $meta ) || ! isset( $meta['data'] ) ) {
			wp_send_json_error( 'Variable data is in an unexpected format.' );
		}

		$existing = $meta['data'];
		$watermark = $meta['watermark'] ?? 0;

		// Group active variables by type.
		$type_order = array(
			'global-color-variable'       => 1,
			'global-font-variable'        => 2,
			'global-size-variable'        => 3,
			'global-custom-size-variable' => 3,
		);

		$active = array();
		foreach ( $existing as $id => $var ) {
			if ( isset( $var['deleted_at'] ) ) {
				continue;
			}
			$active[ $id ] = $var;
		}

		// Sort by type group first, then by current order within the group.
		uasort( $active, function( $a, $b ) use ( $type_order ) {
			$type_a = $type_order[ $a['type'] ?? '' ] ?? 99;
			$type_b = $type_order[ $b['type'] ?? '' ] ?? 99;

			if ( $type_a !== $type_b ) {
				return $type_a - $type_b;
			}

			return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
		} );

		// Assign fresh sequential order numbers.
		$order = 1;
		$now = gmdate( 'Y-m-d H:i:s' );

		foreach ( $active as $id => $var ) {
			$existing[ $id ]['order'] = $order++;
			$existing[ $id ]['updated_at'] = $now;
		}

		$watermark++;

		$record = array(
			'data'      => $existing,
			'watermark' => $watermark,
			'version'   => 2,
		);

		$result = update_post_meta(
			$kit_id,
			'_elementor_global_variables',
			wp_slash( wp_json_encode( $record ) )
		);

		if ( false === $result ) {
			wp_send_json_error( 'Failed to save.' );
		}

		// Count by type for the summary.
		$counts = array();
		foreach ( $active as $var ) {
			$type = $var['type'] ?? 'unknown';
			$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
		}

		wp_send_json_success( array(
			'total'  => count( $active ),
			'counts' => $counts,
		) );
	}

	/* =========================================================================
	 * Elementor default styles (since 2.6)
	 * -------------------------------------------------------------------------
	 * Elementor 4.3 (Atomic Editor) can hold a default style per HTML tag, shown
	 * under Design system > Defaults. It is stored one post per tag with the
	 * same variants/props shape as a global class, rendered as
	 * .elementor .e-default-{tag}, and registered *below* global classes in the
	 * cascade. So it is the right place for a baseline: a heading or paragraph
	 * dropped on the canvas already looks like the framework, and a title-* or
	 * body-* class still wins whenever visual and semantic size differ.
	 *
	 * The defaults are derived from the parsed framework classes, not written
	 * out separately, so they can never drift from the classes:
	 *   h1..h6                        -> heading font + everything title-1..6 sets
	 *   p, a, button, span, li, label -> body font + everything body-m sets
	 *
	 * Only those props are written. Anything a user has set on a tag themselves
	 * is kept. Deliberately not touched: Site Settings > Theme Style, which is
	 * a separate literal-value system outside the Atomic Editor.
	 * ========================================================================= */

	/**
	 * Which framework class each tag's default is derived from.
	 */
	private function get_elementor_default_tag_map() {
		return array(
			'h1'     => array( 'class' => 'title-1', 'font' => 'heading' ),
			'h2'     => array( 'class' => 'title-2', 'font' => 'heading' ),
			'h3'     => array( 'class' => 'title-3', 'font' => 'heading' ),
			'h4'     => array( 'class' => 'title-4', 'font' => 'heading' ),
			'h5'     => array( 'class' => 'title-5', 'font' => 'heading' ),
			'h6'     => array( 'class' => 'title-6', 'font' => 'heading' ),
			'p'      => array( 'class' => 'body-m', 'font' => 'body' ),
			'a'      => array( 'class' => 'body-m', 'font' => 'body' ),
			'button' => array( 'class' => 'body-m', 'font' => 'body' ),
			'span'   => array( 'class' => 'body-m', 'font' => 'body' ),
			'li'     => array( 'class' => 'body-m', 'font' => 'body' ),
			'label'  => array( 'class' => 'body-m', 'font' => 'body' ),
		);
	}

	/**
	 * True when this Elementor has the Defaults feature and it is switched on.
	 */
	private function elementor_supports_default_styles() {
		return class_exists( '\\Elementor\\Modules\\DefaultStyles\\Default_Styles_Repository' );
	}

	/**
	 * Find the imported font variable for a role ('heading' or 'body') by the
	 * same name rule the Bricks theme style uses: a fonts-type variable whose
	 * name contains the role word.
	 */
	private function find_elementor_font_variable_id( $role, $variables, $lookup ) {
		// Parsed Elementor variables already carry their Elementor type and label.
		foreach ( $variables as $variable ) {
			// The parser tags variables with the short bucket name ('fonts').
			if ( ! in_array( $variable['type'] ?? '', array( 'fonts', 'global-font-variable' ), true ) ) {
				continue;
			}

			$label = strtolower( (string) ( $variable['label'] ?? '' ) );

			if ( '' === $label || false === strpos( $label, $role ) ) {
				continue;
			}

			if ( isset( $lookup[ $label ] ) ) {
				return $lookup[ $label ]['id'];
			}
		}

		return '';
	}

	/**
	 * Write the per-tag defaults derived from the framework classes.
	 * Returns array( 'changes' => [...], 'warnings' => [...] ).
	 */
	private function sync_elementor_default_styles( $classes, $variables ) {
		$out = array( 'changes' => array(), 'warnings' => array() );

		if ( ! $this->elementor_supports_default_styles() ) {
			$out['changes'][] = 'This Elementor version cannot set default styles per heading or paragraph yet (that arrives in 4.3), so add a title or body class to give text its look.';
			return $out;
		}

		$lookup     = $this->get_variable_label_lookup();
		$font_ids   = array(
			'heading' => $this->find_elementor_font_variable_id( 'heading', $variables, $lookup ),
			'body'    => $this->find_elementor_font_variable_id( 'body', $variables, $lookup ),
		);
		$by_label   = array();

		foreach ( $classes as $class ) {
			if ( isset( $class['label'] ) ) {
				$by_label[ strtolower( $class['label'] ) ] = $class;
			}
		}

		$repository = \Elementor\Modules\DefaultStyles\Default_Styles_Repository::make();
		$written    = array();
		$unresolved = array();

		foreach ( $this->get_elementor_default_tag_map() as $tag => $spec ) {
			$source = $by_label[ $spec['class'] ] ?? null;

			if ( ! $source ) {
				continue;
			}

			// Base variant of the source class only: no states, no breakpoints.
			$props = array();

			foreach ( $source['variants'] as $variant ) {
				$meta = $variant['meta'] ?? array();

				if ( empty( $meta['state'] ) && ( empty( $meta['breakpoint'] ) || 'desktop' === $meta['breakpoint'] ) ) {
					$props = $variant['props'] ?? array();
					break;
				}
			}

			$props = $this->resolve_variable_markers( $props, $lookup, $unresolved );

			if ( ! empty( $font_ids[ $spec['font'] ] ) ) {
				$props['font-family'] = array(
					'$$type' => 'global-font-variable',
					'value'  => $font_ids[ $spec['font'] ],
				);
			}

			if ( empty( $props ) ) {
				continue;
			}

			// Keep whatever the user already set on this tag; only our props change.
			$existing = $repository->get( $tag );
			$variants = is_array( $existing['variants'] ?? null ) ? $existing['variants'] : array();
			$merged   = false;

			foreach ( $variants as $index => $variant ) {
				$meta = $variant['meta'] ?? array();

				if ( empty( $meta['state'] ) && ( empty( $meta['breakpoint'] ) || 'desktop' === $meta['breakpoint'] ) ) {
					$variants[ $index ]['props'] = array_merge( (array) ( $variant['props'] ?? array() ), $props );
					$merged = true;
					break;
				}
			}

			if ( ! $merged ) {
				array_unshift( $variants, array(
					'meta'  => array( 'breakpoint' => 'desktop', 'state' => null ),
					'props' => $props,
				) );
			}

			try {
				if ( $repository->put( $tag, array( 'type' => 'class', 'variants' => $variants ) ) ) {
					$written[] = $tag;
				}
			} catch ( \Throwable $e ) {
				$out['warnings'][] = 'Could not write the default style for <' . $tag . '>: ' . $e->getMessage();
			}
		}

		if ( ! empty( $written ) ) {
			$out['changes'][] = 'Headings and text now look right the moment you add them, without picking a class: every heading gets your heading font and its size from the scale, and paragraphs, links and buttons get your body font. Add a class only when you want something different. You can see and change these under Design system > Defaults.';
		}

		return $out;
	}

	/**
	 * Snapshot of every tag default, for the backup.
	 */
	private function get_elementor_default_styles_snapshot() {
		if ( ! $this->elementor_supports_default_styles() ) {
			return array();
		}

		try {
			$items = \Elementor\Modules\DefaultStyles\Default_Styles_Repository::make()->all( true );
		} catch ( \Throwable $e ) {
			return array();
		}

		$snapshot = array();

		foreach ( (array) $items as $tag => $item ) {
			$snapshot[ $tag ] = array(
				'type'     => $item['type'] ?? 'class',
				'variants' => $item['variants'] ?? array(),
			);
		}

		return $snapshot;
	}

	/**
	 * Put the tag defaults back exactly as the snapshot had them: tags in the
	 * snapshot are rewritten, tags that were not there are removed.
	 */
	private function restore_elementor_default_styles( $snapshot ) {
		if ( ! $this->elementor_supports_default_styles() || ! is_array( $snapshot ) ) {
			return;
		}

		try {
			$repository = \Elementor\Modules\DefaultStyles\Default_Styles_Repository::make();
			$current    = $repository->all( true );

			foreach ( array_keys( (array) $current ) as $tag ) {
				if ( ! isset( $snapshot[ $tag ] ) ) {
					$repository->delete( $tag );
				}
			}

			foreach ( $snapshot as $tag => $data ) {
				$repository->put( $tag, array(
					'type'     => $data['type'] ?? 'class',
					'variants' => $data['variants'] ?? array(),
				) );
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}

	/* =========================================================================
	 * Bricks support (since 2.5)
	 *
	 * Bricks stores global classes and variables in plain wp_options with raw
	 * CSS strings as values, so no prop conversion layer is needed. We write
	 * directly to:
	 *   - bricks_global_variables / bricks_global_variables_categories
	 *   - bricks_global_classes   / bricks_global_classes_categories
	 *
	 * Design decisions, based on hands-on testing against Bricks 2.3.4:
	 * - Variables keep their raw CSS custom property names (Bricks references
	 *   variables by name, not by ID). Any CSS value is allowed (clamp, calc,
	 *   font stacks with quotes, etc.).
	 * - Merge mode updates existing values by name. Bricks' own Style Manager
	 *   import silently SKIPS duplicate names, so this importer is the only
	 *   reliable way to push brand updates into an existing install.
	 * - Class declarations are mapped to native Bricks settings only for
	 *   property shapes verified to work (typography, background-color,
	 *   padding/margin, border, flex display props). Everything else goes
	 *   into the class's _cssCustom, which Bricks renders as-is:
	 *   - font-family: var(--x) breaks natively (Bricks quotes it as a font
	 *     name), so it always goes to _cssCustom.
	 *   - border width/style/color map natively only when they cover the same
	 *     sides with one style and one color, because Bricks stores a width
	 *     per side but a single style and color for the whole element. See
	 *     plan_bricks_border().
	 *   - gap and box-shadow have no reliable native class mapping.
	 * - Non-base states (:hover etc.) go entirely into _cssCustom for
	 *   predictability.
	 * - Bricks defaults html { font-size: 62.5% } (1rem = 10px). The optional
	 *   rem fix appends html { font-size: 100%; } to Bricks' global custom
	 *   CSS so the framework's rem scale renders at its intended size.
	 * ========================================================================= */

	const BRICKS_BACKUPS_OPTION = 'wsk_bricks_backups';
	const BRICKS_CATEGORY_NAME  = 'Website Stylekit';

	/**
	 * Whether Bricks is the active theme (parent or child).
	 */
	public function is_bricks_active() {
		return 'bricks' === get_template();
	}

	/**
	 * Full AJAX flow for a Bricks import: parse, preview or backup + write.
	 */
	private function handle_bricks_import( $css_input, $mode, $dry_run, $rem_fix = false, $palette_sync = false, $font_defaults = false ) {
		if ( ! $this->is_bricks_active() ) {
			wp_send_json_error( 'Bricks is not the active theme on this site.' );
		}

		$parsed = $this->parse_bricks_import_input( $css_input );

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( $parsed->get_error_message() );
		}

		// Without the root font-size fix, Bricks keeps its intentional 10px rem
		// base. WSK values are designed on a 16px base, so convert every rem
		// value (x1.6) to keep the rendered sizes identical.
		if ( ! $rem_fix ) {
			$parsed = $this->scale_bricks_rem_values( $parsed );
		}

		if ( $dry_run ) {
			wp_send_json_success( $this->preview_bricks_import( $parsed, $mode ) );
		}

		try {
			$backup = $this->create_bricks_backup( 'Before stylekit import', $mode . ' import' );

			$variable_result = $this->import_variables_bricks( $parsed['variables'], $mode );
			$class_result    = $this->import_classes_bricks( $parsed['classes'], $mode );

			if ( $rem_fix ) {
				$this->apply_bricks_rem_fix();
			}

			if ( $palette_sync ) {
				$palette_count = $this->sync_bricks_color_palette( $parsed['variables'] );
			}

			$fonts_defaulted = false;

			if ( $font_defaults ) {
				$fonts_defaulted = $this->sync_bricks_theme_style_fonts( $parsed );
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error( 'Unexpected error during Bricks import: ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
		}

		$result = $this->preview_bricks_import( $parsed, $mode );

		$result['counts']  = array_merge( $variable_result['counts'], array( 'classes' => $class_result['counts'] ) );
		$result['names']   = array_merge( $variable_result['names'], array( 'classes' => $class_result['names'] ) );
		$result['imported'] = $variable_result['imported'] + $class_result['imported'];
		$result['updated']  = $variable_result['updated'] + $class_result['updated'];
		$result['total']    = $result['imported'] + $result['updated'];

		// Warnings are for things that need attention (a declaration we could not
		// map, for example). What the import touched beyond variables and classes
		// is reported separately as "What changed" - those are notices, not
		// warnings, and mixing them made every successful import look alarming.
		$warnings = array_merge( $parsed['warnings'], $class_result['warnings'] );
		$changes  = array();

		if ( $rem_fix ) {
			$changes[] = 'Standard sizing enabled (1rem = 16px), so all sizes render exactly as designed.';
		} else {
			$changes[] = 'Bricks\' own 10px scale kept: all rem sizes were converted automatically so they still render at the intended size.';
		}

		if ( ! empty( $palette_count ) ) {
			$changes[] = sprintf(
				1 === $palette_count
					? 'The Bricks color picker now shows your brand color as the "Website Stylekit" palette (default palette replaced, included in the backup).'
					: 'The Bricks color picker now shows your %d brand colors as the "Website Stylekit" palette (default palette replaced, included in the backup).',
				$palette_count
			);
		}

		$font_names = $this->get_imported_font_names( $parsed['variables'] );

		if ( ! empty( $fonts_defaulted ) ) {
			$changes[] = sprintf(
				'Your fonts (%s) are now the site-wide default via the "Website Stylekit" theme style, so they load on every page and apply to elements without classes too.',
				implode( ', ', $fonts_defaulted )
			);
		} elseif ( ! empty( $font_names ) ) {
			$result['next_steps'] = array(
				sprintf(
					'Your stylekit uses %s. Bricks only loads fonts you pick in its own settings, so set them once under Bricks > Settings > Theme Styles > Typography (or add them as custom fonts). After that, every stylekit class and variable uses them automatically.',
					implode( ' and ', array_map( function( $name ) { return '"' . $name . '"'; }, $font_names ) )
				),
			);
		}

		if ( ! empty( $warnings ) ) {
			$result['warnings'] = array_values( array_unique( $warnings ) );
		}

		if ( ! empty( $changes ) ) {
			$result['changes'] = array_values( array_unique( $changes ) );
		}

		if ( $backup && ! is_wp_error( $backup ) ) {
			$result['backup'] = array(
				'id'    => $backup['id'],
				'label' => $backup['label'],
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Parse pasted CSS into Bricks-ready variables and classes.
	 *
	 * Variables keep their raw custom-property name and value. Classes are
	 * returned as name => state => [ [prop, value], ... ] so the mapper can
	 * decide per declaration between native settings and custom CSS.
	 */
	private function parse_bricks_import_input( $input ) {
		$input = trim( $input );

		if ( ! preg_match( '/--[a-zA-Z0-9_-]+\s*:/', $input ) && ! preg_match( '/\.[a-zA-Z_-][a-zA-Z0-9_-]*\s*[:,{]/', $input ) ) {
			return new WP_Error( 'invalid_input', 'Paste CSS from Website Stylekit, for example :root { --primary-brand-1: #000; } or .title-1 { font-size: var(--title-size-1); }' );
		}

		$css = preg_replace( '/\/\*.*?\*\//s', '', $input );

		$variables = array();
		$warnings  = array();

		// Variables: every --name: value; pair, wherever it appears.
		preg_match_all( '/--([a-zA-Z0-9_-]+)\s*:\s*(.+?)\s*;/', $css, $matches, PREG_SET_ORDER );

		foreach ( $matches as $m ) {
			$variables[] = array(
				'name'  => trim( $m[1] ),
				'value' => trim( $m[2] ),
			);
		}

		// Classes: walk top-level rules, brace-aware.
		$classes = array();
		$length  = strlen( $css );
		$offset  = 0;

		while ( $offset < $length && preg_match( '/([^{}]+)\{/', $css, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
			$selector = trim( $match[1][0] );
			$open_pos = $match[0][1] + strlen( $match[0][0] ) - 1;
			$depth    = 1;
			$pos      = $open_pos + 1;

			while ( $pos < $length && $depth > 0 ) {
				if ( '{' === $css[ $pos ] ) {
					$depth++;
				} elseif ( '}' === $css[ $pos ] ) {
					$depth--;
				}
				$pos++;
			}

			if ( 0 !== $depth ) {
				$warnings[] = 'Skipped a CSS rule because its braces did not match.';
				break;
			}

			$body   = substr( $css, $open_pos + 1, $pos - $open_pos - 2 );
			$offset = $pos;

			if ( ':root' === $selector ) {
				continue;
			}

			// Media queries. When the query lines up with one of Bricks' own
			// breakpoints its rules become breakpoint-suffixed settings, so they
			// stay editable per device in the builder. Anything else (min-width,
			// an off-scale value, a query we cannot parse) falls back to the raw
			// block as custom CSS, which still renders correctly.
			if ( 0 === strpos( $selector, '@media' ) ) {
				$breakpoint = $this->bricks_breakpoint_key( $selector );
				$parsed_all = false;

				if ( '' !== $breakpoint ) {
					$inner_rules = $this->parse_bricks_rule_blocks( $body );

					if ( ! empty( $inner_rules ) ) {
						$parsed_all = true;

						foreach ( $inner_rules as $inner_rule ) {
							$inner_targets = $this->parse_class_selector_targets( $inner_rule['selector'] );

							if ( empty( $inner_targets ) ) {
								$parsed_all = false;
								break;
							}

							foreach ( $inner_targets as $inner_target ) {
								$inner_name = $inner_target['label'];

								if ( '' === $inner_name ) {
									$parsed_all = false;
									break 2;
								}

								$inner_state = $inner_target['state'] ? $inner_target['state'] : '';

								if ( ! isset( $classes[ $inner_name ] ) ) {
									$classes[ $inner_name ] = array( 'states' => array(), 'media' => array(), 'raw' => array() );
								}

								if ( ! isset( $classes[ $inner_name ]['media'][ $breakpoint ][ $inner_state ] ) ) {
									$classes[ $inner_name ]['media'][ $breakpoint ][ $inner_state ] = array();
								}

								$classes[ $inner_name ]['media'][ $breakpoint ][ $inner_state ] = array_merge(
									$classes[ $inner_name ]['media'][ $breakpoint ][ $inner_state ],
									$inner_rule['declarations']
								);
							}
						}
					}
				}

				if ( $parsed_all ) {
					continue;
				}

				if ( preg_match_all( '/\.([a-zA-Z_-][a-zA-Z0-9_-]*)/', $body, $inner ) ) {
					foreach ( array_unique( $inner[1] ) as $inner_name ) {
						if ( ! isset( $classes[ $inner_name ] ) ) {
							$classes[ $inner_name ] = array(
								'states' => array(),
								'media'  => array(),
								'raw'    => array(),
							);
						}
						$classes[ $inner_name ]['raw'][] = $selector . " {\n" . trim( $body ) . "\n}";
					}
				}
				continue;
			}

			$targets = $this->parse_class_selector_targets( $selector );

			if ( empty( $targets ) ) {
				if ( false !== strpos( $selector, '.' ) ) {
					$warnings[] = 'Skipped unsupported selector "' . $selector . '".';
				}
				continue;
			}

			// Split declarations.
			$declarations = array();
			foreach ( explode( ';', $body ) as $line ) {
				$line = trim( $line );
				if ( '' === $line || false === strpos( $line, ':' ) ) {
					continue;
				}
				list( $prop, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
				if ( '' === $prop || '' === $value ) {
					continue;
				}
				$declarations[] = array( strtolower( $prop ), $value );
			}

			if ( empty( $declarations ) ) {
				continue;
			}

			foreach ( $targets as $target ) {
				$name  = $target['label'];
				$state = $target['state'] ? $target['state'] : '';

				if ( '' === $name ) {
					continue;
				}

				if ( ! isset( $classes[ $name ] ) ) {
					$classes[ $name ] = array(
						'states' => array(),
						'media'  => array(),
						'raw'    => array(),
					);
				}

				if ( ! isset( $classes[ $name ]['states'][ $state ] ) ) {
					$classes[ $name ]['states'][ $state ] = array();
				}

				$classes[ $name ]['states'][ $state ] = array_merge( $classes[ $name ]['states'][ $state ], $declarations );
			}
		}

		if ( empty( $variables ) && empty( $classes ) ) {
			return new WP_Error( 'parse_failed', 'Could not find supported variables or classes in the pasted CSS.' );
		}

		return array(
			'variables' => $variables,
			'classes'   => $classes,
			'warnings'  => $warnings,
		);
	}

	/**
	 * Extract the actual font names from imported font-family variables,
	 * so the import summary can tell the user which fonts to activate in
	 * Bricks. "\"Playfair Display\", serif" becomes "Playfair Display".
	 */
	private function get_imported_font_names( $variables ) {
		$names = array();

		foreach ( $variables as $variable ) {
			if ( 'fonts' !== $this->detect_css_variable_type( $variable['name'], $variable['value'] ) ) {
				continue;
			}

			$first = trim( explode( ',', $variable['value'] )[0], " \t\"'" );

			// Skip generic families; they need no loading.
			if ( '' === $first || in_array( strtolower( $first ), array( 'serif', 'sans-serif', 'monospace', 'system-ui', 'cursive', 'fantasy' ), true ) ) {
				continue;
			}

			if ( ! in_array( $first, $names, true ) ) {
				$names[] = $first;
			}
		}

		return $names;
	}

	/**
	 * Set the imported heading/body fonts as site-wide defaults via a
	 * dedicated "Website Stylekit" Bricks theme style.
	 *
	 * Why: Bricks only loads a webfont when its name appears in a real
	 * font-family declaration in the generated CSS. Fonts referenced through
	 * variables never trigger that, and class CSS only exists on pages where
	 * the class is used. A theme style puts the font names in the CSS of
	 * every page, so the fonts always load AND elements without classes get
	 * the brand fonts as their default.
	 *
	 * Boundaries: we only ever touch our own style (key 'website-stylekit'),
	 * never the user's styles. Its condition is "entire website", which
	 * scores lower than any more specific user style, so user styles always
	 * win. The whole theme styles option is part of the pre-import backup.
	 *
	 * Font sources: heading/body font-family variables (e.g. --heading-font,
	 * --font-family-body). Weights are read from the imported title/body
	 * classes when present, falling back to the framework defaults (700/400),
	 * so Google loads the correct weights instead of faux-bolding.
	 *
	 * Returns an array of font names that were applied, or false.
	 */
	private function sync_bricks_theme_style_fonts( $parsed ) {
		$heading_font = null;
		$body_font    = null;

		foreach ( $parsed['variables'] as $variable ) {
			if ( 'fonts' !== $this->detect_css_variable_type( $variable['name'], $variable['value'] ) ) {
				continue;
			}

			$first = trim( explode( ',', $variable['value'] )[0], " \t\"'" );

			if ( '' === $first || in_array( strtolower( $first ), array( 'serif', 'sans-serif', 'monospace', 'system-ui', 'cursive', 'fantasy' ), true ) ) {
				continue;
			}

			$name = strtolower( $variable['name'] );

			if ( null === $heading_font && false !== strpos( $name, 'heading' ) ) {
				$heading_font = $first;
			} elseif ( null === $body_font && false !== strpos( $name, 'body' ) ) {
				$body_font = $first;
			}
		}

		if ( ! $heading_font && ! $body_font ) {
			return false;
		}

		// Read real weights from the imported classes; framework defaults otherwise.
		$heading_weight = $this->find_class_font_weight( $parsed['classes'], array( 'title-1', 'title-2', 'heading-font' ), '700' );
		$body_weight    = $this->find_class_font_weight( $parsed['classes'], array( 'body-m', 'body-s', 'body-font' ), '400' );

		$styles = get_option( 'bricks_theme_styles', array() );

		if ( ! is_array( $styles ) ) {
			$styles = array();
		}

		$style = isset( $styles['website-stylekit'] ) && is_array( $styles['website-stylekit'] ) ? $styles['website-stylekit'] : array(
			'label'    => 'Website Stylekit',
			'settings' => array(),
		);

		if ( ! isset( $style['settings'] ) || ! is_array( $style['settings'] ) ) {
			$style['settings'] = array();
		}

		// Condition: entire website (lowest specificity; user styles win).
		$style['settings']['conditions'] = array(
			'conditions' => array(
				array( 'main' => 'any' ),
			),
		);

		if ( ! isset( $style['settings']['typography'] ) || ! is_array( $style['settings']['typography'] ) ) {
			$style['settings']['typography'] = array();
		}

		$applied = array();

		if ( $body_font ) {
			$style['settings']['typography']['typographyBody'] = array_merge(
				is_array( $style['settings']['typography']['typographyBody'] ?? null ) ? $style['settings']['typography']['typographyBody'] : array(),
				array(
					'font-family' => $body_font,
					'font-weight' => (string) $body_weight,
				)
			);
			$applied[] = $body_font;
		}

		if ( $heading_font ) {
			$style['settings']['typography']['typographyHeadings'] = array_merge(
				is_array( $style['settings']['typography']['typographyHeadings'] ?? null ) ? $style['settings']['typography']['typographyHeadings'] : array(),
				array(
					'font-family' => $heading_font,
					'font-weight' => (string) $heading_weight,
				)
			);

			if ( ! in_array( $heading_font, $applied, true ) ) {
				$applied[] = $heading_font;
			}
		}

		$styles['website-stylekit'] = $style;

		update_option( 'bricks_theme_styles', $styles );

		return $applied;
	}

	/**
	 * Find the font-weight declared on the first matching imported class.
	 */
	private function find_class_font_weight( $classes, $class_names, $fallback ) {
		foreach ( $class_names as $class_name ) {
			if ( ! isset( $classes[ $class_name ]['states'][''] ) ) {
				continue;
			}

			foreach ( $classes[ $class_name ]['states'][''] as $declaration ) {
				if ( 'font-weight' === $declaration[0] && is_numeric( $declaration[1] ) ) {
					return $declaration[1];
				}
			}
		}

		return $fallback;
	}

	/**
	 * Multiply every rem value in a CSS string by 1.6, converting values
	 * designed on a 16px rem base to Bricks' default 10px rem base.
	 */
	private function scale_rem_in_string( $value ) {
		return preg_replace_callback(
			'/(-?\d*\.?\d+)rem\b/',
			function( $m ) {
				$scaled = (float) $m[1] * 1.6;
				$scaled = round( $scaled, 4 );

				// Trim trailing zeros without turning 0 into ''.
				$formatted = rtrim( rtrim( number_format( $scaled, 4, '.', '' ), '0' ), '.' );

				return ( '' === $formatted ? '0' : $formatted ) . 'rem';
			},
			$value
		);
	}

	/**
	 * Apply the rem conversion to a full parsed Bricks import payload.
	 */
	private function scale_bricks_rem_values( $parsed ) {
		foreach ( $parsed['variables'] as &$variable ) {
			$variable['value'] = $this->scale_rem_in_string( $variable['value'] );
		}
		unset( $variable );

		foreach ( $parsed['classes'] as &$class_data ) {
			foreach ( $class_data['states'] as &$declarations ) {
				foreach ( $declarations as &$declaration ) {
					$declaration[1] = $this->scale_rem_in_string( $declaration[1] );
				}
				unset( $declaration );
			}
			unset( $declarations );

			foreach ( $class_data['raw'] as &$raw_block ) {
				$raw_block = $this->scale_rem_in_string( $raw_block );
			}
			unset( $raw_block );
		}
		unset( $class_data );

		return $parsed;
	}

	/**
	 * Make the imported color variables THE Bricks color palette.
	 *
	 * Replaces bricks_color_palette with a single "Website Stylekit" palette
	 * (Bricks' default swatches serve no purpose next to a brand palette).
	 * Each color uses raw: var(--name), so a swatch always follows the
	 * variable's current value. An existing Website Stylekit palette keeps
	 * its id and gets merged by color name, so swatches already used on
	 * elements stay intact. The palette is included in the pre-import backup.
	 *
	 * Returns the number of colors synced.
	 */
	private function sync_bricks_color_palette( $variables ) {
		$colors = array();
		$values = array();

		foreach ( $variables as $variable ) {
			$values[ $variable['name'] ] = $variable['value'];

			if ( 'colors' === $this->detect_css_variable_type( $variable['name'], $variable['value'] ) ) {
				$colors[] = $variable['name'];
			}
		}

		if ( empty( $colors ) ) {
			return 0;
		}

		$palettes = get_option( 'bricks_color_palette', array() );

		if ( ! is_array( $palettes ) ) {
			$palettes = array();
		}

		// Reuse an existing Website Stylekit palette (and its colors) if present.
		$wsk_palette = array(
			'id'     => $this->generate_bricks_id(),
			'name'   => 'Website Stylekit',
			'colors' => array(),
		);

		foreach ( $palettes as $palette ) {
			if ( isset( $palette['name'] ) && 'Website Stylekit' === $palette['name'] ) {
				$wsk_palette           = $palette;
				$wsk_palette['colors'] = is_array( $palette['colors'] ?? null ) ? $palette['colors'] : array();
				break;
			}
		}

		$color_index = array();

		foreach ( $wsk_palette['colors'] as $index => $color ) {
			if ( isset( $color['id'] ) ) {
				$color_index[ $color['id'] ] = $index;
			}
		}

		foreach ( $colors as $name ) {
			// Bricks' color manager (2.x) reads the resolved value from 'light'.
			// Entries without 'light'/'dark' are flagged as "needs converting", so
			// we resolve the variable to its literal value here. 'name' is dropped
			// on purpose: Bricks removes it when it converts a color itself.
			$entry = array(
				'id'    => $name,
				'raw'   => 'var(--' . $name . ')',
				'light' => $this->resolve_bricks_color_value( $name, $values ),
			);

			if ( '' === $entry['light'] ) {
				unset( $entry['light'] );
			}

			if ( isset( $color_index[ $name ] ) ) {
				$existing = $wsk_palette['colors'][ $color_index[ $name ] ];
				unset( $existing['name'], $existing['hex'], $existing['rgb'], $existing['hsl'] );
				$wsk_palette['colors'][ $color_index[ $name ] ] = array_merge( $existing, $entry );
			} else {
				$wsk_palette['colors'][] = $entry;
				$color_index[ $name ]    = count( $wsk_palette['colors'] ) - 1;
			}
		}

		$wsk_palette['colors'] = array_values( $wsk_palette['colors'] );

		// Replace all palettes (including Bricks' default) with the brand palette.
		update_option( 'bricks_color_palette', array( $wsk_palette ) );

		return count( $colors );
	}

	/**
	 * Resolve a color variable to a literal value for the Bricks color manager.
	 *
	 * Follows var(--other) references (a few levels deep) so a color that points
	 * at another variable still gets a real swatch. Returns '' when it cannot be
	 * resolved to a literal.
	 */
	private function resolve_bricks_color_value( $name, $values, $depth = 0 ) {
		if ( $depth > 5 || ! isset( $values[ $name ] ) ) {
			return '';
		}

		$value = trim( (string) $values[ $name ] );

		if ( preg_match( '/^var\(\s*--([a-zA-Z0-9_-]+)\s*(?:,.*)?\)$/', $value, $m ) ) {
			return $this->resolve_bricks_color_value( $m[1], $values, $depth + 1 );
		}

		return '' === $value ? '' : $value;
	}

	/**
	 * Generate a Bricks-style 6-character lowercase id.
	 */
	private function generate_bricks_id( $existing = array() ) {
		do {
			$id = substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz' ), 0, 3 ) . substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz' ), 0, 3 );
		} while ( in_array( $id, $existing, true ) );

		return $id;
	}

	/**
	 * Find or create the Website Stylekit category in a Bricks category option.
	 * Returns the category id.
	 */
	private function ensure_bricks_category( $option_name ) {
		$categories = get_option( $option_name, array() );

		if ( ! is_array( $categories ) ) {
			$categories = array();
		}

		foreach ( $categories as $category ) {
			if ( isset( $category['name'] ) && strtolower( $category['name'] ) === strtolower( self::BRICKS_CATEGORY_NAME ) ) {
				return $category['id'];
			}
		}

		$id = $this->generate_bricks_id( wp_list_pluck( $categories, 'id' ) );

		$categories[] = array(
			'id'   => $id,
			'name' => self::BRICKS_CATEGORY_NAME,
		);

		update_option( $option_name, $categories );

		return $id;
	}

	/**
	 * Import variables into bricks_global_variables. Merge updates values by
	 * name (Bricks' own importer silently skips duplicates; we don't).
	 */
	private function import_variables_bricks( $variables, $mode = 'merge' ) {
		$empty_result = array(
			'imported' => 0,
			'updated'  => 0,
			'counts'   => array(
				'colors' => array( 'imported' => 0, 'updated' => 0 ),
				'fonts'  => array( 'imported' => 0, 'updated' => 0 ),
				'sizes'  => array( 'imported' => 0, 'updated' => 0 ),
			),
			'names'    => array(
				'colors' => array( 'imported' => array(), 'updated' => array() ),
				'fonts'  => array( 'imported' => array(), 'updated' => array() ),
				'sizes'  => array( 'imported' => array(), 'updated' => array() ),
			),
		);

		if ( empty( $variables ) ) {
			return $empty_result;
		}

		$category_id = $this->ensure_bricks_category( 'bricks_global_variables_categories' );
		$existing    = get_option( 'bricks_global_variables', array() );

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		if ( 'replace' === $mode ) {
			// Only clear variables previously imported by Website Stylekit.
			$existing = array_values( array_filter( $existing, function( $variable ) use ( $category_id ) {
				return ( $variable['category'] ?? '' ) !== $category_id;
			} ) );
		}

		$name_index = array();
		foreach ( $existing as $index => $variable ) {
			if ( isset( $variable['name'] ) ) {
				$name_index[ strtolower( $variable['name'] ) ] = $index;
			}
		}

		$result = $empty_result;
		$ids    = wp_list_pluck( $existing, 'id' );

		foreach ( $variables as $variable ) {
			$name   = $variable['name'];
			$value  = $variable['value'];
			$bucket = $this->detect_css_variable_type( $name, $value );
			$bucket = in_array( $bucket, array( 'colors', 'fonts', 'sizes' ), true ) ? $bucket : 'sizes';
			$key    = strtolower( $name );

			if ( isset( $name_index[ $key ] ) ) {
				$existing[ $name_index[ $key ] ]['value'] = $value;
				$result['updated']++;
				$result['counts'][ $bucket ]['updated']++;
				$result['names'][ $bucket ]['updated'][] = $name;
			} else {
				$id    = $this->generate_bricks_id( $ids );
				$ids[] = $id;

				$existing[] = array(
					'id'       => $id,
					'name'     => $name,
					'value'    => $value,
					'category' => $category_id,
				);

				$name_index[ $key ] = count( $existing ) - 1;
				$result['imported']++;
				$result['counts'][ $bucket ]['imported']++;
				$result['names'][ $bucket ]['imported'][] = $name;
			}
		}

		update_option( 'bricks_global_variables', array_values( $existing ) );

		return $result;
	}

	/**
	 * Property groups with a verified native Bricks class-settings mapping.
	 */
	private function get_bricks_native_typography_props() {
		return array( 'font-size', 'font-weight', 'line-height', 'text-align', 'letter-spacing', 'text-transform', 'text-decoration' );
	}

	/**
	 * Build one custom-CSS rule for the declarations that had no native mapping.
	 * Returns '' when there is nothing left over.
	 */
	private function build_bricks_custom_rule( $name, $state, $declarations ) {
		if ( empty( $declarations ) ) {
			return '';
		}

		$lines = array();

		foreach ( $declarations as $declaration ) {
			$lines[] = '  ' . $declaration[0] . ': ' . $declaration[1] . ';';
		}

		return '.' . $name . ( $state ? ':' . $state : '' ) . " {\n" . implode( "\n", $lines ) . "\n}";
	}

	/**
	 * Pixel width of a Bricks breakpoint key, for rebuilding a media query.
	 */
	private function bricks_breakpoint_width( $key ) {
		$defaults = array( 'tablet_portrait' => 991, 'mobile_landscape' => 767, 'mobile_portrait' => 478 );

		if ( class_exists( '\\Bricks\\Breakpoints' ) && ! empty( \Bricks\Breakpoints::$breakpoints ) ) {
			foreach ( \Bricks\Breakpoints::$breakpoints as $breakpoint ) {
				if ( isset( $breakpoint['key'] ) && $breakpoint['key'] === $key && isset( $breakpoint['width'] ) ) {
					return (int) $breakpoint['width'];
				}
			}
		}

		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : 0;
	}

	/**
	 * Split a block of CSS into its rules, returning selector + declarations.
	 * Returns an empty array when the block contains anything but plain rules
	 * (a nested at-rule, for example), so the caller can fall back to raw CSS.
	 */
	private function parse_bricks_rule_blocks( $css ) {
		$rules  = array();
		$length = strlen( $css );
		$offset = 0;

		while ( $offset < $length && preg_match( '/([^{}]+)\{([^{}]*)\}/', $css, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
			$selector = trim( $match[1][0] );
			$body     = $match[2][0];
			$offset   = $match[0][1] + strlen( $match[0][0] );

			if ( '' === $selector || 0 === strpos( $selector, '@' ) ) {
				return array();
			}

			$declarations = array();

			foreach ( explode( ';', $body ) as $line ) {
				$line = trim( $line );

				if ( '' === $line || false === strpos( $line, ':' ) ) {
					continue;
				}

				list( $prop, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );

				if ( '' === $prop || '' === $value ) {
					continue;
				}

				$declarations[] = array( strtolower( $prop ), $value );
			}

			if ( empty( $declarations ) ) {
				continue;
			}

			$rules[] = array( 'selector' => $selector, 'declarations' => $declarations );
		}

		// Leftover content that was not a complete rule: not safe to map.
		if ( '' !== trim( substr( $css, $offset ) ) ) {
			return array();
		}

		return $rules;
	}

	/**
	 * Parse a single box-shadow value into Bricks' box-shadow control shape.
	 *
	 * Handles "[inset] <offsetX> <offsetY> [blur] [spread] <color>". Multiple
	 * comma-separated shadows have no native equivalent, so those return null
	 * and fall through to custom CSS.
	 */
	private function parse_bricks_box_shadow( $value ) {
		$value = trim( $value );

		if ( '' === $value || 'none' === $value || false !== strpos( $value, ',' ) ) {
			return null;
		}

		$tokens = $this->split_css_value_tokens( $value );
		$inset  = false;

		foreach ( $tokens as $index => $token ) {
			if ( 'inset' === strtolower( $token ) ) {
				$inset = true;
				unset( $tokens[ $index ] );
			}
		}

		$tokens = array_values( $tokens );
		$color  = '';

		// The color is whichever token is not a length. Bricks stores it separately.
		foreach ( $tokens as $index => $token ) {
			if ( ! preg_match( '/^-?[0-9.]+([a-z%]+)?$/i', $token ) ) {
				if ( '' !== $color ) {
					return null;
				}

				$color = $token;
				unset( $tokens[ $index ] );
			}
		}

		$lengths = array_values( $tokens );

		if ( count( $lengths ) < 2 || count( $lengths ) > 4 ) {
			return null;
		}

		$keys   = array( 'offsetX', 'offsetY', 'blur', 'spread' );
		$values = array();

		foreach ( $keys as $index => $key ) {
			$values[ $key ] = isset( $lengths[ $index ] ) ? $lengths[ $index ] : 0;
		}

		$shadow = array( 'values' => $values );

		if ( '' !== $color ) {
			$shadow['color'] = array( 'raw' => $color );
		}

		if ( $inset ) {
			$shadow['inset'] = true;
		}

		return $shadow;
	}

	/**
	 * Bricks' breakpoint key for a max-width media query, or '' when the query
	 * does not line up with a breakpoint the builder knows about.
	 *
	 * Reads the site's own breakpoints so customised ones still match.
	 */
	private function bricks_breakpoint_key( $media ) {
		if ( ! preg_match( '/^@media\s*\(\s*max-width\s*:\s*([0-9]+)px\s*\)$/i', trim( $media ), $matches ) ) {
			return '';
		}

		$width       = (int) $matches[1];
		$breakpoints = array();

		if ( class_exists( '\\Bricks\\Breakpoints' ) && ! empty( \Bricks\Breakpoints::$breakpoints ) ) {
			$breakpoints = \Bricks\Breakpoints::$breakpoints;
		} else {
			$breakpoints = array(
				array( 'key' => 'tablet_portrait', 'width' => 991 ),
				array( 'key' => 'mobile_landscape', 'width' => 767 ),
				array( 'key' => 'mobile_portrait', 'width' => 478 ),
			);
		}

		foreach ( $breakpoints as $breakpoint ) {
			if ( ! empty( $breakpoint['base'] ) ) {
				continue;
			}

			if ( isset( $breakpoint['width'] ) && (int) $breakpoint['width'] === $width ) {
				return $breakpoint['key'];
			}
		}

		return '';
	}

	/**
	 * Suffix Bricks appends to a setting key for a pseudo-state, or null when
	 * the state has no native equivalent and has to stay custom CSS.
	 */
	private function bricks_state_suffix( $state ) {
		if ( '' === $state ) {
			return '';
		}

		return in_array( $state, array( 'hover', 'active', 'focus' ), true ) ? ':' . $state : null;
	}

	/**
	 * Map one rule's declarations onto a Bricks settings fragment.
	 * Declarations with no native equivalent are collected in $leftover.
	 */
	private function map_bricks_declarations( $declarations, &$leftover ) {
		$settings    = array();
		$border_plan = $this->plan_bricks_border( $declarations );

		if ( $border_plan ) {
			$settings['_border'] = $border_plan['border'];
		}

		foreach ( $declarations as $declaration ) {
			list( $prop, $value ) = $declaration;

			if ( $border_plan && in_array( $prop, $border_plan['consumed'], true ) ) {
				continue;
			}

			if ( ! $this->map_bricks_declaration( $prop, $value, $settings ) ) {
				$leftover[] = $declaration;
			}
		}

		return $settings;
	}

	/**
	 * Merge a settings fragment into the class settings under a key suffix
	 * (':hover', ':tablet_portrait', ':tablet_portrait:hover', ...).
	 */
	private function merge_bricks_settings( &$settings, $fragment, $suffix = '' ) {
		foreach ( $fragment as $key => $value ) {
			$target = $key . $suffix;

			if ( isset( $settings[ $target ] ) && is_array( $settings[ $target ] ) && is_array( $value ) ) {
				$settings[ $target ] = array_merge( $settings[ $target ], $value );
				continue;
			}

			$settings[ $target ] = $value;
		}
	}

	/**
	 * Decide whether a class's border width/style/color can be expressed with
	 * Bricks' native border control without changing what renders.
	 *
	 * Bricks stores a width per side but a single style and a single color for
	 * the whole element, and only draws a border on sides that carry a width.
	 * So we map only when width, style and color cover exactly the same sides
	 * and style and color each have one value. Per-side styles, mixed colors,
	 * or a width without a color would render differently through the native
	 * control, so those keep going to custom CSS where they render literally.
	 *
	 * Returns array( 'border' => ..., 'consumed' => ... ), or null to skip.
	 */
	private function plan_bricks_border( $declarations ) {
		$sides    = array( 'top', 'right', 'bottom', 'left' );
		$parts    = array( 'width' => array(), 'style' => array(), 'color' => array() );
		$consumed = array();

		foreach ( $declarations as $declaration ) {
			list( $prop, $value ) = $declaration;
			$value = trim( $value );

			if ( in_array( $prop, array( 'border-width', 'border-style', 'border-color' ), true ) ) {
				$part = substr( $prop, 7 );

				if ( 'width' === $part ) {
					$expanded = $this->expand_bricks_box_shorthand( $value, $sides );

					if ( null === $expanded ) {
						return null;
					}

					$parts['width'] = $expanded;
				} else {
					foreach ( $sides as $side ) {
						$parts[ $part ][ $side ] = $value;
					}
				}
			} elseif ( preg_match( '/^border-(top|right|bottom|left)-(width|style|color)$/', $prop, $matches ) ) {
				$parts[ $matches[2] ][ $matches[1] ] = $value;
			} else {
				continue;
			}

			$consumed[] = $prop;
		}

		if ( empty( $consumed ) ) {
			return null;
		}

		$covered = array_keys( $parts['width'] );
		sort( $covered );

		// No width at all: a rule that only recolours or restyles an existing
		// border (a :hover state, typically). Bricks emits a bare border-color /
		// border-style for that, but only as one value for the whole element, so
		// this needs the shorthand form that covers all four sides.
		if ( empty( $covered ) ) {
			$border = array();

			foreach ( array( 'style', 'color' ) as $part ) {
				if ( empty( $parts[ $part ] ) ) {
					continue;
				}

				if ( 4 !== count( $parts[ $part ] ) || 1 !== count( array_unique( $parts[ $part ] ) ) ) {
					return null;
				}

				$border[ $part ] = ( 'color' === $part )
					? array( 'raw' => reset( $parts[ $part ] ) )
					: reset( $parts[ $part ] );
			}

			return empty( $border ) ? null : array( 'border' => $border, 'consumed' => $consumed );
		}

		foreach ( array( 'style', 'color' ) as $part ) {
			$keys = array_keys( $parts[ $part ] );
			sort( $keys );

			if ( $keys !== $covered || 1 !== count( array_unique( $parts[ $part ] ) ) ) {
				return null;
			}
		}

		return array(
			'border' => array(
				'width' => $parts['width'],
				'style' => reset( $parts['style'] ),
				'color' => array( 'raw' => reset( $parts['color'] ) ),
			),
			'consumed' => $consumed,
		);
	}

	/**
	 * Map one class (all states) onto Bricks settings + custom CSS fallback.
	 */
	private function build_bricks_class_settings( $name, $class_data ) {
		$settings     = array();
		$custom_rules = array();

		// Base rule and its pseudo-states. Bricks accepts a state suffix on any
		// setting key ('_background:hover'), so hover/active/focus map natively
		// too; anything else keeps going to custom CSS.
		foreach ( $class_data['states'] as $state => $declarations ) {
			$suffix   = $this->bricks_state_suffix( $state );
			$leftover = array();

			if ( null === $suffix ) {
				$leftover = $declarations;
			} else {
				$fragment = $this->map_bricks_declarations( $declarations, $leftover );
				$this->merge_bricks_settings( $settings, $fragment, $suffix );
			}

			$custom_rule = $this->build_bricks_custom_rule( $name, $state, $leftover );

			if ( $custom_rule ) {
				$custom_rules[] = $custom_rule;
			}
		}

		// Breakpoint rules. Bricks takes the same suffix trick for breakpoints,
		// so a media query that matches one of its breakpoints becomes a real
		// per-device setting instead of a block of custom CSS.
		foreach ( (array) ( $class_data['media'] ?? array() ) as $breakpoint => $states ) {
			foreach ( (array) $states as $state => $declarations ) {
				$state_suffix = $this->bricks_state_suffix( $state );
				$leftover     = array();

				if ( null === $state_suffix ) {
					$leftover = $declarations;
				} else {
					$fragment = $this->map_bricks_declarations( $declarations, $leftover );
					$this->merge_bricks_settings( $settings, $fragment, ':' . $breakpoint . $state_suffix );
				}

				$custom_rule = $this->build_bricks_custom_rule( $name, $state, $leftover );

				if ( $custom_rule ) {
					$custom_rules[] = '@media (max-width: ' . $this->bricks_breakpoint_width( $breakpoint ) . 'px) {' . "\n" . $custom_rule . "\n}";
				}
			}
		}

		if ( ! empty( $class_data['raw'] ) ) {
			$custom_rules = array_merge( $custom_rules, $class_data['raw'] );
		}

		if ( ! empty( $custom_rules ) ) {
			$settings['_cssCustom'] = implode( "\n", $custom_rules );
		}

		return $settings;
	}

	/**
	 * Try to map a single declaration to native Bricks class settings.
	 * Returns true when mapped; false means: use custom CSS instead.
	 *
	 * Only shapes verified against Bricks 2.3.4 are mapped natively.
	 * font-family deliberately stays custom CSS (see section docs). Border
	 * width/style/color are handled up front by plan_bricks_border(); only
	 * border-radius is mapped here.
	 */
	private function map_bricks_declaration( $prop, $value, &$settings ) {
		// Typography.
		if ( in_array( $prop, $this->get_bricks_native_typography_props(), true ) ) {
			if ( ! isset( $settings['_typography'] ) ) {
				$settings['_typography'] = array();
			}

			if ( 'font-weight' === $prop && is_numeric( $value ) ) {
				$value = (int) $value;
			}

			$settings['_typography'][ $prop ] = $value;
			return true;
		}

		if ( 'color' === $prop ) {
			if ( ! isset( $settings['_typography'] ) ) {
				$settings['_typography'] = array();
			}
			$settings['_typography']['color'] = array( 'raw' => $value );
			return true;
		}

		// Background color (plain color values only, no gradients/images).
		if ( 'background-color' === $prop || ( 'background' === $prop && ! preg_match( '/gradient|url\(/i', $value ) && false === strpos( $value, ' ' ) ) ) {
			$settings['_background'] = isset( $settings['_background'] ) && is_array( $settings['_background'] ) ? $settings['_background'] : array();
			$settings['_background']['color'] = array( 'raw' => $value );
			return true;
		}

		// Padding and margin, shorthand and longhand.
		foreach ( array( 'padding' => '_padding', 'margin' => '_margin' ) as $box_prop => $setting_key ) {
			if ( $prop === $box_prop ) {
				$sides = $this->expand_bricks_box_shorthand( $value );

				if ( null === $sides ) {
					return false; // Unparseable shorthand: custom CSS.
				}

				$settings[ $setting_key ] = isset( $settings[ $setting_key ] ) && is_array( $settings[ $setting_key ] ) ? array_merge( $settings[ $setting_key ], $sides ) : $sides;
				return true;
			}

			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( $prop === $box_prop . '-' . $side ) {
					if ( ! isset( $settings[ $setting_key ] ) || ! is_array( $settings[ $setting_key ] ) ) {
						$settings[ $setting_key ] = array();
					}
					$settings[ $setting_key ][ $side ] = $value;
					return true;
				}
			}
		}

		// Border radius (uniform or per-corner shorthand with 1-4 values).
		if ( 'border-radius' === $prop ) {
			$corners = $this->expand_bricks_box_shorthand( $value, array( 'top', 'right', 'bottom', 'left' ) );

			if ( null === $corners ) {
				return false;
			}

			if ( ! isset( $settings['_border'] ) || ! is_array( $settings['_border'] ) ) {
				$settings['_border'] = array();
			}

			$settings['_border']['radius'] = $corners;
			return true;
		}

		// Gap. Bricks keeps row and column gap as separate controls, so the
		// shorthand is split here (1 value = both, 2 values = row then column).
		if ( 'row-gap' === $prop ) {
			$settings['_rowGap'] = $value;
			return true;
		}

		if ( 'column-gap' === $prop ) {
			$settings['_columnGap'] = $value;
			return true;
		}

		if ( 'gap' === $prop ) {
			$tokens = $this->split_css_value_tokens( $value );

			if ( 1 === count( $tokens ) ) {
				$settings['_rowGap']    = $tokens[0];
				$settings['_columnGap'] = $tokens[0];
				return true;
			}

			if ( 2 === count( $tokens ) ) {
				$settings['_rowGap']    = $tokens[0];
				$settings['_columnGap'] = $tokens[1];
				return true;
			}

			return false;
		}

		if ( 'box-shadow' === $prop ) {
			$shadow = $this->parse_bricks_box_shadow( $value );

			if ( null === $shadow ) {
				return false;
			}

			$settings['_boxShadow'] = $shadow;
			return true;
		}

		if ( 'transition' === $prop ) {
			$settings['_cssTransition'] = $value;
			return true;
		}

		if ( 'flex-wrap' === $prop ) {
			$settings['_flexWrap'] = $value;
			return true;
		}

		if ( 'grid-template-columns' === $prop ) {
			$settings['_gridTemplateColumns'] = $value;
			return true;
		}

		// Flex layout basics.
		if ( 'display' === $prop && in_array( $value, array( 'flex', 'block', 'inline-block', 'grid', 'none' ), true ) ) {
			$settings['_display'] = $value;
			return true;
		}

		if ( 'flex-direction' === $prop ) {
			$settings['_direction'] = $value;
			return true;
		}

		if ( 'align-items' === $prop ) {
			$settings['_alignItems'] = $value;
			return true;
		}

		if ( 'justify-content' === $prop ) {
			$settings['_justifyContent'] = $value;
			return true;
		}

		// Everything else (font-family, gap, box-shadow, transition, ...) -> custom CSS.
		return false;
	}

	/**
	 * Expand a 1-4 value CSS box shorthand into top/right/bottom/left.
	 * Returns null when the value can't be split safely (e.g. functions with spaces).
	 */
	private function expand_bricks_box_shorthand( $value, $sides = array( 'top', 'right', 'bottom', 'left' ) ) {
		$tokens = $this->split_css_value_tokens( $value );

		if ( empty( $tokens ) || count( $tokens ) > 4 ) {
			return null;
		}

		$count = count( $tokens );

		if ( 1 === $count ) {
			$map = array( $tokens[0], $tokens[0], $tokens[0], $tokens[0] );
		} elseif ( 2 === $count ) {
			$map = array( $tokens[0], $tokens[1], $tokens[0], $tokens[1] );
		} elseif ( 3 === $count ) {
			$map = array( $tokens[0], $tokens[1], $tokens[2], $tokens[1] );
		} else {
			$map = $tokens;
		}

		return array(
			$sides[0] => $map[0],
			$sides[1] => $map[1],
			$sides[2] => $map[2],
			$sides[3] => $map[3],
		);
	}

	/**
	 * Import classes into bricks_global_classes.
	 */
	private function import_classes_bricks( $classes, $mode = 'merge' ) {
		$result = array(
			'imported' => 0,
			'updated'  => 0,
			'counts'   => array( 'imported' => 0, 'updated' => 0 ),
			'names'    => array( 'imported' => array(), 'updated' => array() ),
			'warnings' => array(),
		);

		if ( empty( $classes ) ) {
			return $result;
		}

		$category_id = $this->ensure_bricks_category( 'bricks_global_classes_categories' );
		$existing    = get_option( 'bricks_global_classes', array() );

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		// Bricks stores class *ids* on every element, so a replace that mints new
		// ids would silently strip the styling from every page already built with
		// these classes. Remember the ids we are about to drop and hand them back
		// to the class of the same name below.
		$reused_ids = array();

		if ( 'replace' === $mode ) {
			foreach ( $existing as $class ) {
				if ( ( $class['category'] ?? '' ) === $category_id && isset( $class['name'], $class['id'] ) ) {
					$reused_ids[ strtolower( $class['name'] ) ] = $class['id'];
				}
			}

			$existing = array_values( array_filter( $existing, function( $class ) use ( $category_id ) {
				return ( $class['category'] ?? '' ) !== $category_id;
			} ) );
		}

		$name_index = array();
		foreach ( $existing as $index => $class ) {
			if ( isset( $class['name'] ) ) {
				$name_index[ strtolower( $class['name'] ) ] = $index;
			}
		}

		$ids = wp_list_pluck( $existing, 'id' );

		foreach ( $classes as $name => $class_data ) {
			$settings = $this->build_bricks_class_settings( $name, $class_data );
			$key      = strtolower( $name );

			if ( isset( $name_index[ $key ] ) ) {
				$existing[ $name_index[ $key ] ]['settings'] = $settings;
				$result['updated']++;
				$result['counts']['updated']++;
				$result['names']['updated'][] = $name;
			} else {
				if ( isset( $reused_ids[ $key ] ) && ! in_array( $reused_ids[ $key ], $ids, true ) ) {
					$id = $reused_ids[ $key ];
				} else {
					$id = $this->generate_bricks_id( $ids );
				}

				$ids[] = $id;

				$existing[] = array(
					'id'       => $id,
					'name'     => $name,
					'settings' => $settings,
					'category' => $category_id,
				);

				$name_index[ $key ] = count( $existing ) - 1;
				$result['imported']++;
				$result['counts']['imported']++;
				$result['names']['imported'][] = $name;
			}
		}

		update_option( 'bricks_global_classes', array_values( $existing ) );

		// Let open builder tabs (with class sync enabled) detect the change.
		update_option( 'bricks_global_classes_timestamp', time() );

		return $result;
	}

	/**
	 * Preview a Bricks import without writing: same shape as the live result.
	 */
	private function preview_bricks_import( $parsed, $mode = 'merge' ) {
		$existing_variables = get_option( 'bricks_global_variables', array() );
		$existing_classes   = get_option( 'bricks_global_classes', array() );

		$variable_names = array();
		foreach ( (array) $existing_variables as $variable ) {
			if ( isset( $variable['name'] ) ) {
				$variable_names[] = strtolower( $variable['name'] );
			}
		}

		$class_names = array();
		foreach ( (array) $existing_classes as $class ) {
			if ( isset( $class['name'] ) ) {
				$class_names[] = strtolower( $class['name'] );
			}
		}

		$counts = array(
			'colors'  => array( 'imported' => 0, 'updated' => 0 ),
			'fonts'   => array( 'imported' => 0, 'updated' => 0 ),
			'sizes'   => array( 'imported' => 0, 'updated' => 0 ),
			'classes' => array( 'imported' => 0, 'updated' => 0 ),
		);
		$names = array(
			'colors'  => array( 'imported' => array(), 'updated' => array() ),
			'fonts'   => array( 'imported' => array(), 'updated' => array() ),
			'sizes'   => array( 'imported' => array(), 'updated' => array() ),
			'classes' => array( 'imported' => array(), 'updated' => array() ),
		);

		foreach ( $parsed['variables'] as $variable ) {
			$bucket = $this->detect_css_variable_type( $variable['name'], $variable['value'] );
			$bucket = in_array( $bucket, array( 'colors', 'fonts', 'sizes' ), true ) ? $bucket : 'sizes';
			$slot   = in_array( strtolower( $variable['name'] ), $variable_names, true ) ? 'updated' : 'imported';

			$counts[ $bucket ][ $slot ]++;
			$names[ $bucket ][ $slot ][] = $variable['name'];
		}

		foreach ( array_keys( $parsed['classes'] ) as $class_name ) {
			$slot = in_array( strtolower( $class_name ), $class_names, true ) ? 'updated' : 'imported';
			$counts['classes'][ $slot ]++;
			$names['classes'][ $slot ][] = $class_name;
		}

		$imported = 0;
		$updated  = 0;

		foreach ( $counts as $type_counts ) {
			$imported += $type_counts['imported'];
			$updated  += $type_counts['updated'];
		}

		return array(
			'builder'  => 'bricks',
			'mode'     => $mode,
			'counts'   => $counts,
			'names'    => $names,
			'imported' => $imported,
			'updated'  => $updated,
			'total'    => $imported + $updated,
			'warnings' => $parsed['warnings'],
		);
	}

	/**
	 * Snapshot the Bricks classes/variables options before an import.
	 * Stored in a plain option, independent of any Elementor kit.
	 */
	private function create_bricks_backup( $label = 'Manual backup', $mode = 'manual' ) {
		$backup = array(
			'id'                  => 'wskb-' . substr( md5( wp_generate_uuid4() ), 0, 12 ),
			'created_at'          => current_time( 'mysql' ),
			'timestamp'           => time(),
			'label'               => sanitize_text_field( $label ),
			'mode'                => sanitize_text_field( $mode ),
			'target'              => 'bricks',
			'variables'           => get_option( 'bricks_global_variables', array() ),
			'variable_categories' => get_option( 'bricks_global_variables_categories', array() ),
			'classes'             => get_option( 'bricks_global_classes', array() ),
			'class_categories'    => get_option( 'bricks_global_classes_categories', array() ),
			'color_palette'       => get_option( 'bricks_color_palette', array() ),
			'theme_styles'        => get_option( 'bricks_theme_styles', array() ),
		);

		$backup['variable_count'] = is_array( $backup['variables'] ) ? count( $backup['variables'] ) : 0;
		$backup['class_count']    = is_array( $backup['classes'] ) ? count( $backup['classes'] ) : 0;

		$backups = get_option( self::BRICKS_BACKUPS_OPTION, array() );

		if ( ! is_array( $backups ) ) {
			$backups = array();
		}

		array_unshift( $backups, $backup );
		$backups = array_slice( $backups, 0, self::MAX_BACKUPS );

		update_option( self::BRICKS_BACKUPS_OPTION, $backups, false );

		return $backup;
	}

	/**
	 * Read the stored Bricks backups list.
	 */
	private function get_bricks_backups() {
		$backups = get_option( self::BRICKS_BACKUPS_OPTION, array() );

		return is_array( $backups ) ? $backups : array();
	}

	/**
	 * All backups across builders, newest first, each with a 'target' key.
	 */
	private function get_all_backups() {
		$elementor = array();

		foreach ( $this->get_backups() as $backup ) {
			$backup['target'] = $backup['target'] ?? 'elementor';
			$elementor[]      = $backup;
		}

		$all = array_merge( $elementor, $this->get_bricks_backups() );

		usort( $all, function( $a, $b ) {
			return ( $b['timestamp'] ?? 0 ) <=> ( $a['timestamp'] ?? 0 );
		} );

		return $all;
	}

	/**
	 * Delete a Bricks backup by id.
	 */
	private function delete_bricks_backup( $backup_id ) {
		$backups  = $this->get_bricks_backups();
		$filtered = array_values( array_filter( $backups, function( $backup ) use ( $backup_id ) {
			return ( $backup['id'] ?? '' ) !== $backup_id;
		} ) );

		if ( count( $filtered ) === count( $backups ) ) {
			return new WP_Error( 'missing_backup', 'Backup not found.' );
		}

		update_option( self::BRICKS_BACKUPS_OPTION, $filtered, false );

		return true;
	}

	/**
	 * Whether a backup id belongs to the Bricks store.
	 */
	private function is_bricks_backup_id( $backup_id ) {
		return 0 === strpos( (string) $backup_id, 'wskb-' );
	}

	/**
	 * Readable CSS preview of a Bricks backup snapshot.
	 */
	private function build_bricks_backup_css_preview( $backup ) {
		$lines = array();

		$variables = is_array( $backup['variables'] ?? null ) ? $backup['variables'] : array();

		if ( ! empty( $variables ) ) {
			$lines[] = ':root {';
			foreach ( $variables as $variable ) {
				if ( isset( $variable['name'], $variable['value'] ) ) {
					$lines[] = '  --' . $variable['name'] . ': ' . $variable['value'] . ';';
				}
			}
			$lines[] = '}';
		}

		$classes = is_array( $backup['classes'] ?? null ) ? $backup['classes'] : array();

		foreach ( $classes as $class ) {
			if ( ! isset( $class['name'] ) ) {
				continue;
			}

			$settings = is_array( $class['settings'] ?? null ) ? $class['settings'] : array();
			$body     = array();

			foreach ( $settings as $group => $values ) {
				if ( '_cssCustom' === $group ) {
					continue;
				}

				if ( is_array( $values ) ) {
					foreach ( $values as $prop => $value ) {
						if ( is_array( $value ) ) {
							$value = $value['raw'] ?? wp_json_encode( $value );
						}
						$body[] = '  ' . ltrim( $group . '.' . $prop, '_' ) . ': ' . $value . ';';
					}
				} else {
					$body[] = '  ' . ltrim( $group, '_' ) . ': ' . $values . ';';
				}
			}

			$lines[] = '';
			$lines[] = '.' . $class['name'] . ' {';
			$lines   = array_merge( $lines, $body );
			$lines[] = '}';

			if ( ! empty( $settings['_cssCustom'] ) ) {
				$lines[] = '/* custom CSS */';
				$lines[] = $settings['_cssCustom'];
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Restore a Bricks backup by id. Returns the backup or a WP_Error.
	 */
	private function restore_bricks_backup( $backup_id ) {
		$backups = get_option( self::BRICKS_BACKUPS_OPTION, array() );
		$backup  = null;

		foreach ( (array) $backups as $candidate ) {
			if ( ( $candidate['id'] ?? '' ) === $backup_id ) {
				$backup = $candidate;
				break;
			}
		}

		if ( ! $backup ) {
			return new WP_Error( 'missing_backup', 'Backup not found.' );
		}

		update_option( 'bricks_global_variables', $backup['variables'] ?? array() );
		update_option( 'bricks_global_variables_categories', $backup['variable_categories'] ?? array() );
		update_option( 'bricks_global_classes', $backup['classes'] ?? array() );
		update_option( 'bricks_global_classes_categories', $backup['class_categories'] ?? array() );

		if ( isset( $backup['color_palette'] ) ) {
			update_option( 'bricks_color_palette', $backup['color_palette'] );
		}

		if ( isset( $backup['theme_styles'] ) ) {
			update_option( 'bricks_theme_styles', $backup['theme_styles'] );
		}

		update_option( 'bricks_global_classes_timestamp', time() );

		return $backup;
	}

	/**
	 * Append html { font-size: 100%; } to Bricks' global custom CSS (idempotent).
	 * Undoes Bricks' default 62.5% root font-size so 1rem = 16px.
	 */
	private function apply_bricks_rem_fix() {
		$marker   = '/* Website Stylekit: 16px rem base */';
		$settings = get_option( 'bricks_global_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$custom_css = isset( $settings['customCss'] ) ? (string) $settings['customCss'] : '';

		if ( false !== strpos( $custom_css, $marker ) ) {
			return;
		}

		$custom_css .= ( '' === trim( $custom_css ) ? '' : "\n\n" ) . $marker . "\nhtml {\n  font-size: 100%;\n}";

		$settings['customCss'] = $custom_css;

		update_option( 'bricks_global_settings', $settings );
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		?>
		<div class="wsk-wrap">

			<header class="wsk-header">
				<div class="wsk-header-inner">
					<div class="wsk-header__left">
						<img
							src="<?php echo esc_url( WSK_PLUGIN_URL . 'assets/img/wsk-icon.svg' ); ?>"
							alt="Website Stylekit"
							class="wsk-header__icon"
							width="28"
							height="28"
						>
						<h1 class="wsk-header__title">Website Stylekit</h1>
						<span class="wsk-header__version">v<?php echo esc_html( WSK_VERSION ); ?></span>
					</div>
					<nav class="wsk-header__nav">
						<a href="#import" class="wsk-header__link wsk-header__link--active" data-tab="import">Import</a>
						<a href="#export" class="wsk-header__link" data-tab="export">Export</a>
						<a href="#backups" class="wsk-header__link" data-tab="backups">Backups</a>
						<a href="#about" class="wsk-header__link" data-tab="about">About</a>
						<a href="#changelog" class="wsk-header__link" data-tab="changelog">Changelog</a>
						<?php /* Support tab hidden for now — content preserved, enable by uncommenting the line below */ ?>
						<?php // echo '<a href="#support" class="wsk-header__link" data-tab="support">Support</a>'; ?>
					</nav>
				</div>
			</header>

			<div class="wsk-content">
				<div class="wsk-tab-panel active" data-tab="import">
					<?php $this->render_import_tab(); ?>
				</div>
				<div class="wsk-tab-panel" data-tab="export">
					<?php $this->render_export_tab(); ?>
				</div>
				<div class="wsk-tab-panel" data-tab="backups">
					<?php $this->render_backups_tab(); ?>
				</div>
				<div class="wsk-tab-panel" data-tab="about">
					<?php $this->render_about_tab(); ?>
				</div>
				<div class="wsk-tab-panel" data-tab="changelog">
					<?php $this->render_changelog_tab(); ?>
				</div>
				<div class="wsk-tab-panel" data-tab="support">
					<?php $this->render_support_tab(); ?>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Render the Export tab.
	 */
	private function render_export_tab() {
		?>
		<div class="wsk-card">
			<h2>Export your stylekit</h2>
			<p class="wsk-description">Export your Elementor variables and global classes as portable CSS.</p>
			<input type="hidden" name="wsk_filter" value="all">

			<h3 class="wsk-section-label">Output format</h3>
			<div class="wsk-format-toggle">
				<label class="wsk-radio-label">
					<input type="radio" name="wsk_format" value="framework" checked>
					<span>CSS Framework</span>
					<span class="wsk-hint">Variables &amp; Classes</span>
				</label>
				<label class="wsk-radio-label">
					<input type="radio" name="wsk_format" value="css">
					<span>CSS Variables</span>
					<span class="wsk-hint">Variables only</span>
				</label>
			</div>

			<details id="wsk-advanced-export" class="wsk-advanced-export">
				<summary>Advanced JSON exports</summary>
				<div class="wsk-format-toggle">
					<label class="wsk-radio-label">
						<input type="radio" name="wsk_format" value="clean">
						<span>Clean JSON</span>
						<span class="wsk-hint">Simple label, value and order data</span>
					</label>
					<label class="wsk-radio-label">
						<input type="radio" name="wsk_format" value="raw">
						<span>Raw Elementor JSON</span>
						<span class="wsk-hint">Elementor's original stored format</span>
					</label>
				</div>
			</details>

			<div id="wsk-export-scope" class="wsk-export-scope">
				<h3 class="wsk-section-label">Variable type</h3>
				<div class="wsk-format-toggle">
					<label class="wsk-radio-label">
						<input type="radio" name="wsk_filter_advanced" value="all" checked>
						<span>All variables</span>
					</label>
					<label class="wsk-radio-label">
						<input type="radio" name="wsk_filter_advanced" value="colors">
						<span>Colors only</span>
					</label>
					<label class="wsk-radio-label">
						<input type="radio" name="wsk_filter_advanced" value="sizes">
						<span>Sizes only</span>
					</label>
					<label class="wsk-radio-label">
						<input type="radio" name="wsk_filter_advanced" value="fonts">
						<span>Fonts only</span>
					</label>
				</div>
			</div>

			<div class="wsk-actions">
				<button id="wsk-export-btn" class="button button-primary">Export</button>
				<button id="wsk-copy-btn" class="button" style="display:none;">Copy to Clipboard</button>
				<button id="wsk-download-btn" class="button" style="display:none;">Download CSS</button>
			</div>

			<div id="wsk-export-result" style="display:none;">
				<h3>Result</h3>
				<textarea id="wsk-export-output" readonly rows="20"></textarea>
			</div>

			<div id="wsk-export-error" class="wsk-error" style="display:none;"></div>
		</div>
		<?php
	}

	/**
	 * Render the Import tab.
	 */
	private function render_import_tab() {
		?>
		<div class="wsk-card">
			<h2>Import your stylekit</h2>
			<p class="wsk-description">Paste your Website Stylekit CSS below. This importer writes to Elementor V4 variables and Atomic Editor global classes, or to Bricks global variables and classes.</p>

			<textarea id="wsk-import-input" rows="16" placeholder=':root {
  --heading-font: "Roboto";
  --body-font: "Roboto";
  --primary-brand-1: #E9D816;
  --primary-dark-1: #1A1A1A;
  --title-size-1: clamp(3rem, 1.7566rem + 5.5263vw, 5.625rem);
  --size-32: clamp(1.5rem, 1.2632rem + 1.0526vw, 2rem);
}

.title-1 {
  font-size: var(--title-size-1);
  color: var(--primary-dark-1);
}'></textarea>

			<h3 class="wsk-section-label">Import target</h3>
			<?php
			$bricks_active    = $this->is_bricks_active();
			$elementor_active = (bool) $this->get_kit_id();
			$default_target   = ( $bricks_active && ! $elementor_active ) ? 'bricks' : 'elementor';
			?>
			<div class="wsk-format-toggle wsk-option-grid">
				<label class="wsk-radio-label<?php echo $elementor_active ? '' : ' wsk-radio-label--disabled'; ?>">
					<input type="radio" name="wsk_import_target" value="elementor" <?php checked( 'elementor', $default_target ); ?> <?php disabled( ! $elementor_active ); ?>>
					<span>Elementor</span>
					<span class="wsk-hint"><?php echo $elementor_active ? 'V4 Atomic Editor: variables and classes' : 'No active Elementor kit found'; ?></span>
				</label>
				<label class="wsk-radio-label<?php echo $bricks_active ? '' : ' wsk-radio-label--disabled'; ?>">
					<input type="radio" name="wsk_import_target" value="bricks" <?php checked( 'bricks', $default_target ); ?> <?php disabled( ! $bricks_active ); ?>>
					<span>Bricks</span>
					<span class="wsk-hint"><?php echo $bricks_active ? 'Global variables and classes (Style Manager)' : 'Bricks is not the active theme'; ?></span>
				</label>
			</div>

			<div id="wsk-bricks-options" class="wsk-import-options" <?php echo 'bricks' === $default_target ? '' : 'style="display:none;"'; ?>>
				<label class="wsk-check-label">
					<input type="checkbox" id="wsk-bricks-rem-fix" checked>
					<span>Use standard sizing (1rem = 16px)</span>
					<span class="wsk-hint">Your stylekit is designed on the web standard where 1rem equals 16px. Bricks deliberately uses its own compact scale where 1rem equals 10px. Keep this on for new projects: sizes render exactly as designed. Turn it off on an existing site that was built with Bricks&rsquo; own scale &mdash; the importer then converts your stylekit&rsquo;s sizes automatically so they still look right.</span>
				</label>
				<label class="wsk-check-label">
					<input type="checkbox" id="wsk-bricks-palette" checked>
					<span>Use your colors in the color picker</span>
					<span class="wsk-hint">Replaces Bricks&rsquo; default color palette with your own brand colors, so every color picker shows your palette as clickable swatches. The swatches stay linked to your color variables: change a variable later and the swatches update with it.</span>
				</label>
				<label class="wsk-check-label">
					<input type="checkbox" id="wsk-bricks-fonts" checked>
					<span>Use your fonts as the site default</span>
					<span class="wsk-hint">Sets your heading and body font as the site-wide default in a &ldquo;Website Stylekit&rdquo; theme style. Bricks then loads the fonts on every page, and elements without classes get your brand fonts too. Your own theme styles always take priority over this default.</span>
				</label>
			</div>

			<h3 class="wsk-section-label">How should we handle existing styles?</h3>
			<div class="wsk-import-options wsk-option-grid">
				<label class="wsk-radio-label">
					<input type="radio" name="wsk_import_mode" value="merge" checked>
					<span>Add new and update existing</span>
					<span class="wsk-hint">Updates matching names and adds the rest. Safest option.</span>
				</label>
				<label class="wsk-radio-label">
					<input type="radio" name="wsk_import_mode" value="replace">
					<span>Replace everything</span>
					<span class="wsk-hint">Clears the imported style types first, then adds the new CSS.</span>
				</label>
			</div>

			<div id="wsk-import-preview" class="wsk-import-preview" style="display:none;"></div>

			<div class="wsk-actions">
				<button id="wsk-preview-import-btn" class="button">Preview Import</button>
				<button id="wsk-import-btn" class="button button-primary" style="display:none;">Apply Import</button>
			</div>

			<div id="wsk-import-success" class="wsk-import-result" style="display:none;"></div>
			<div id="wsk-import-error" class="wsk-error" style="display:none;"></div>
		</div>

		<?php if ( $elementor_active ) : ?>
		<div class="wsk-card">
			<h2>Tidy up your variables</h2>
			<p class="wsk-description">If your Elementor variables are out of order or all mixed up, this puts them back in a clean order. Colors first, then fonts, then sizes. (Elementor only &mdash; Bricks keeps its own order per category in the Style Manager.)</p>

			<div class="wsk-actions">
				<button id="wsk-reorder-btn" class="button">Reorder Variables</button>
			</div>

			<div id="wsk-reorder-success" class="wsk-success" style="display:none;"></div>
			<div id="wsk-reorder-error" class="wsk-error" style="display:none;"></div>
		</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the Backups tab.
	 */
	private function render_backups_tab() {
		$backups          = $this->get_all_backups();
		$bricks_active    = $this->is_bricks_active();
		$elementor_active = (bool) $this->get_kit_id();
		?>
		<div class="wsk-card">
			<h2>Backups</h2>
			<p class="wsk-description">Website Stylekit saves a backup before every import. CSS is useful for moving styles through the normal Import tab. A backup file is different: it stores the exact builder data and can only be restored here. Elementor and Bricks backups are stored separately and can only be restored to their own builder.</p>

			<div class="wsk-backup-toolbar">
				<div>
					<strong>Automatic import backups</strong>
					<span class="wsk-hint">Keeping the latest <?php echo esc_html( self::MAX_BACKUPS ); ?> backups per builder.</span>
				</div>
				<div class="wsk-backup-toolbar__actions">
					<?php if ( $elementor_active ) : ?>
						<button class="button wsk-create-backup-btn" data-target="elementor">Backup Elementor</button>
					<?php endif; ?>
					<?php if ( $bricks_active ) : ?>
						<button class="button wsk-create-backup-btn" data-target="bricks">Backup Bricks</button>
					<?php endif; ?>
					<label class="button wsk-icon-button" for="wsk-upload-backup-input" title="Upload backup file" aria-label="Upload backup file">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
					</label>
					<input id="wsk-upload-backup-input" type="file" accept=".json,application/json" style="display:none;">
				</div>
			</div>

			<div id="wsk-backup-success" class="wsk-success" style="display:none;"></div>
			<div id="wsk-backup-error" class="wsk-error" style="display:none;"></div>

			<?php if ( empty( $backups ) ) : ?>
				<div class="wsk-empty-state">
					<strong>No backups yet</strong>
					<p>Apply an import or create a manual backup, and it will show up here.</p>
				</div>
			<?php else : ?>
				<div class="wsk-backup-list">
					<?php foreach ( $backups as $backup ) : ?>
						<?php
						$backup_id = $backup['id'] ?? '';
						$created_at = ! empty( $backup['created_at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $backup['created_at'] ) ) : '';
						$mode = $backup['mode'] ?? 'backup';
						$target = ( 'bricks' === ( $backup['target'] ?? 'elementor' ) ) ? 'bricks' : 'elementor';
						?>
						<div class="wsk-backup-item" data-backup-id="<?php echo esc_attr( $backup_id ); ?>">
							<div class="wsk-backup-item__main">
								<span class="wsk-backup-item__title">
									<strong><?php echo esc_html( $backup['label'] ?? 'Backup' ); ?></strong>
									<span class="wsk-badge--builder wsk-badge--builder-<?php echo esc_attr( $target ); ?>"><?php echo esc_html( 'bricks' === $target ? 'Bricks' : 'Elementor' ); ?></span>
								</span>
								<span><?php echo esc_html( $created_at ); ?> · <?php echo esc_html( ucfirst( $mode ) ); ?></span>
							</div>
							<div class="wsk-backup-item__meta">
								<span><?php echo esc_html( (int) ( $backup['variable_count'] ?? 0 ) ); ?> variables</span>
								<span><?php echo esc_html( (int) ( $backup['class_count'] ?? 0 ) ); ?> classes</span>
							</div>
							<div class="wsk-backup-item__actions">
								<button class="button button-primary wsk-restore-backup-btn" data-backup-id="<?php echo esc_attr( $backup_id ); ?>">Restore</button>
								<button class="button wsk-view-backup-btn" data-backup-id="<?php echo esc_attr( $backup_id ); ?>">View</button>
								<button class="button wsk-download-backup-css-btn" data-backup-id="<?php echo esc_attr( $backup_id ); ?>">Download CSS</button>
								<button class="button wsk-download-backup-btn" data-backup-id="<?php echo esc_attr( $backup_id ); ?>">Download Backup</button>
								<button class="button wsk-delete-backup-btn" data-backup-id="<?php echo esc_attr( $backup_id ); ?>">Delete</button>
							</div>
							<div class="wsk-backup-viewer" style="display:none;">
								<textarea class="wsk-backup-viewer-output" readonly rows="18"></textarea>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the About tab.
	 */
	private function render_about_tab() {
		?>
		<div class="wsk-about__body">
			<img
				src="<?php echo esc_url( WSK_PLUGIN_URL . 'assets/img/rino-profile.jpg' ); ?>"
				alt="Rino de Boer"
				class="wsk-about__photo"
				width="80"
				height="80"
			>

			<p>Hey, my name is Rino. I'm a Dutch web designer and I mainly work with Elementor.</p>

			<p>At some point I started noticing how much time I spent on setup. Not the creative part. The administrative part. Every new project: open a blank site, build the same colors, configure the same spacing, set the same typography. The same decisions, the same clicks, every single time.</p>

			<p>So I built <a href="https://websitestylekit.com" target="_blank" rel="noopener noreferrer">websitestylekit.com</a>. A place where you put together your design system once — your colors, your sizes, your fonts — and keep it ready to go.</p>

			<p>But a design system only means something when it lives inside your tools. That's what this plugin is for. You build your kit on websitestylekit.com, and this plugin gets it into your page builder.</p>

			<p>Right now it works with Elementor, because that's what I use. But websitestylekit.com doesn't belong to any one page builder. Divi, Bricks, whatever comes next — the kit stays the same. The importer just changes.</p>

			<p>I hope it saves you some of that setup time. That's really all it's supposed to do.</p>

			<img
				src="<?php echo esc_url( WSK_PLUGIN_URL . 'assets/img/rino-signature.svg' ); ?>"
				alt="Rino"
				class="wsk-about__signature"
				width="60"
				height="32"
				aria-hidden="true"
			>
		</div>
		<?php
	}

	/**
	 * Render the Changelog tab.
	 */
	private function render_changelog_tab() {
		?>
		<div class="wsk-changelog__body">

			<div class="wsk-changelog__release">
				<div class="wsk-changelog__release-header">
					<span class="wsk-changelog__version">v2.5</span>
					<span class="wsk-changelog__date"><?php esc_html_e( 'August 2026', 'websitestylekit' ); ?></span>
				</div>
				<p class="wsk-changelog__intro"><?php esc_html_e( 'First Bricks support: import your stylekit CSS straight into Bricks global variables and classes.', 'websitestylekit' ); ?></p>
				<ul class="wsk-changelog__list">
					<li><?php esc_html_e( 'Bricks import target — the importer now detects Bricks and writes variables and classes directly to the Bricks Style Manager data.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Reliable updates — matching variable and class names get their values updated, where Bricks\' own importer silently skips duplicates.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Smart mapping — declarations become native Bricks controls where safe, and fall back to per-class custom CSS for everything else (font-family, gap, shadows, transitions).', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Root font-size fix — optional one-click fix for Bricks\' 10px rem default, so rem-based scales render at their intended size.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Bricks backups — a snapshot of your Bricks classes and variables is saved before every import.', 'websitestylekit' ); ?></li>
				</ul>
			</div>

			<div class="wsk-changelog__release">
				<div class="wsk-changelog__release-header">
					<span class="wsk-changelog__version">v2.0</span>
					<span class="wsk-changelog__date"><?php esc_html_e( 'May 2026', 'websitestylekit' ); ?></span>
				</div>
				<p class="wsk-changelog__intro"><?php esc_html_e( 'A bigger CSS-first workflow for moving complete stylekits between Elementor sites.', 'websitestylekit' ); ?></p>
				<ul class="wsk-changelog__list">
					<li><?php esc_html_e( 'CSS import — paste Website Stylekit CSS and the plugin detects supported colors, font families, sizes, and classes automatically.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Import preview — see what will be added, updated, or skipped before applying changes.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Class import — import Elementor global classes from CSS framework exports and update matching class names.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Clearer import results — summaries now show colors, font families, size variables, classes, and warnings separately.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'CSS exports — export CSS variables only, or a full CSS framework with variables and Elementor global classes.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Cleaner export output — CSS is formatted with readable spacing and class exports no longer show Elementor scope prefixes.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Simplified import modes — removed the copy mode and kept merge or replace as the main choices.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Backups — automatically save backups before imports, with manual backup, upload, readable CSS preview, restore, CSS download, backup download, and delete actions.', 'websitestylekit' ); ?></li>
				</ul>
			</div>

			<div class="wsk-changelog__release">
				<div class="wsk-changelog__release-header">
					<span class="wsk-changelog__version">v1.0</span>
					<span class="wsk-changelog__date"><?php esc_html_e( 'April 2026', 'websitestylekit' ); ?></span>
				</div>
				<p class="wsk-changelog__intro"><?php esc_html_e( 'First release. Built to connect Elementor V4 design tokens with websitestylekit.com.', 'websitestylekit' ); ?></p>
				<ul class="wsk-changelog__list">
					<li><?php esc_html_e( 'Import colors — paste JSON from websitestylekit.com directly into Elementor V4 global color variables.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Import font sizes — paste CSS clamp values and they are stored as Elementor size variables.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Two import modes: smart merge or replace existing imported style types.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Export variables — export colors, sizes, and fonts as JSON or raw Elementor format.', 'websitestylekit' ); ?></li>
					<li><?php esc_html_e( 'Reorder utility — groups variables by type and assigns clean sequential order numbers.', 'websitestylekit' ); ?></li>
				</ul>
			</div>

		</div>
		<?php
	}

	/**
	 * Render the Support tab.
	 */
	private function render_support_tab() {
		?>
		<div class="wsk-support__body">
			<h2><?php esc_html_e( 'Found a bug?', 'websitestylekit' ); ?></h2>
			<p><?php esc_html_e( 'Website Stylekit is a free plugin. There is no official support, but if you run into a bug I would like to know about it so I can fix it.', 'websitestylekit' ); ?></p>
			<p><?php esc_html_e( 'The best place to report it is the WordPress support forum. Just describe what happened and I will take a look when I can.', 'websitestylekit' ); ?></p>
			<a href="https://wordpress.org/support/plugin/websitestylekit/" target="_blank" rel="noopener noreferrer" class="wsk-support__button">
				<?php esc_html_e( 'Go to support forum', 'websitestylekit' ); ?>
			</a>
		</div>
		<?php
	}
}

// Initialize.
Website_Stylekit::get_instance();
