<?php
/**
 * Plugin Name: WebP & AVIF Images Converter
 * Description: Convierte imágenes subidas (PNG/JPG) a formato WebP y AVIF usando GD.
 * Version:     1.0.0
 * Author:      Bronto.ar
 * Author URI:  https://bronto.ar
 * Text Domain: webp-avif-images-converter
 * Requires PHP: 8.1
 * Requires at least: 6.0
 *
 * @package Webp_Avif_Images_Converter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version.
 */
define( 'WPAC_VERSION', '1.0.0' );

/**
 * Absolute path to the plugin directory (with trailing slash).
 */
define( 'WPAC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory (with trailing slash).
 */
define( 'WPAC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Simple PSR-4-style autoloader for classes under includes/.
 *
 * Class files follow the pattern includes/class-{slug}.php
 * where the class name is Webp_Avif_Images_Converter_{StudlyCaps}.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'Webp_Avif_Images_Converter_';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$slug = str_replace( $prefix, '', $class );
		$file = WPAC_PLUGIN_DIR . 'includes/class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $slug ) ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'Webp_Avif_Images_Converter_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Webp_Avif_Images_Converter_Deactivator', 'deactivate' ) );

/**
 * Bootstrap on plugins_loaded (priority 10).
 */
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'webp-avif-images-converter', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( class_exists( 'Webp_Avif_Images_Converter_Media' ) ) {
			new Webp_Avif_Images_Converter_Media();
		}

		if ( class_exists( 'Webp_Avif_Images_Converter_Settings' ) ) {
			new Webp_Avif_Images_Converter_Settings();
		}
	},
	10
);

/**
 * Activation handler — GD capability checks, default settings.
 *
 * @package Webp_Avif_Images_Converter
 */
class Webp_Avif_Images_Converter_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		// Check AVIF availability.
		$avif_available = function_exists( 'imageavif' ) && ( imagetypes() & IMG_AVIF );
		update_option( 'webp_avif_images_converter_avif_available', $avif_available );

		// Register default settings only if not already present.
		if ( false === get_option( 'webp_avif_images_converter_settings' ) ) {
			update_option(
				'webp_avif_images_converter_settings',
				array(
					'input_formats'  => array( 'png', 'jpg' ),
					'quality'        => 100,
					'output_formats' => array( 'webp' ),
				)
			);
		}
	}
}

/**
 * Deactivation handler — no-op placeholder.
 *
 * @package Webp_Avif_Images_Converter
 */
class Webp_Avif_Images_Converter_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		// Reserved for future cleanup logic.
	}
}
