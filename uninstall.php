<?php
/**
 * Uninstall handler — removes all plugin options from the database.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * @package Webp_Avif_Images_Converter
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
defined( 'ABSPATH' ) || exit;

delete_option( 'webp_avif_images_converter_settings' );
delete_option( 'webp_avif_images_converter_avif_available' );
