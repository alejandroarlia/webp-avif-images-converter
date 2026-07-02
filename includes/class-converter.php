<?php
/**
 * GD conversion engine — converts PNG/JPEG images to WebP and AVIF.
 *
 * @package Webp_Avif_Images_Converter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Webp_Avif_Images_Converter_Converter
 *
 * Provides a static method to convert a single image file to WebP and/or AVIF
 * using the GD library. Alpha transparency is preserved for PNG sources.
 */
class Webp_Avif_Images_Converter_Converter {

	/**
	 * Convert a source image to WebP and/or AVIF.
	 *
	 * @param string $source_path Absolute path to the source image file.
	 * @param string $dest_dir    Absolute path to the destination directory.
	 * @param string $filename    Target filename (without format extension).
	 * @param array  $settings    Plugin settings array (input_formats, quality, output_formats).
	 *
	 * @return array|WP_Error On success: ['webp' => string|null, 'avif' => string|null].
	 *                        On failure: WP_Error with details.
	 */
	public static function convert( string $source_path, string $dest_dir, string $filename, array $settings ) {
		// 1. Validate source file.
		if ( ! file_exists( $source_path ) ) {
			$error = new WP_Error( 'wpac_source_missing', __( 'El archivo de origen no existe.', 'webp-avif-images-converter' ) );
			self::log( $error->get_error_message() . ': ' . $source_path );
			return $error;
		}

		$mime = wp_check_filetype( $source_path )['type'] ?? '';

		// 2. Create GD resource.
		$resource = self::create_gd_resource( $source_path, $mime );
		if ( is_wp_error( $resource ) ) {
			return $resource;
		}

		// 3. Preserve alpha channel for all images.
		imagepalettetotruecolor( $resource );
		imagealphablending( $resource, false );
		imagesavealpha( $resource, true );

		$quality   = absint( $settings['quality'] ?? 100 );
		$outputs   = $settings['output_formats'] ?? array( 'webp' );
		$result    = array(
			'webp' => null,
			'avif' => null,
		);

		try {
			// 4. WebP output.
			if ( in_array( 'webp', $outputs, true ) ) {
				$dest_webp = trailingslashit( $dest_dir ) . $filename . '.webp';
				if ( imagewebp( $resource, $dest_webp, $quality ) ) {
					$result['webp'] = $dest_webp;
				} else {
					self::log( 'imagewebp() falló para: ' . $source_path );
				}
			}

			// 5. AVIF output (conditional).
			if ( in_array( 'avif', $outputs, true ) ) {
				if ( function_exists( 'imageavif' ) && ( imagetypes() & IMG_AVIF ) ) {
					$dest_avif = trailingslashit( $dest_dir ) . $filename . '.avif';
					if ( imageavif( $resource, $dest_avif, $quality ) ) {
						$result['avif'] = $dest_avif;
					} else {
						self::log( 'imageavif() falló para: ' . $source_path );
					}
				} else {
					self::log( 'AVIF no disponible, se omite conversión para: ' . $source_path );
				}
			}
		} finally {
			// 6. Free GD resource.
			imagedestroy( $resource );
		}

		// 7. Return WP_Error if no output was generated.
		if ( null === $result['webp'] && null === $result['avif'] ) {
			return new WP_Error( 'wpac_convert_failed', __( 'No se generó ningún archivo de salida.', 'webp-avif-images-converter' ) );
		}

		return $result;
	}

	/**
	 * Create a GD resource from a file path based on its MIME type.
	 *
	 * @param string $path Absolute path to the image file.
	 * @param string $mime MIME type of the image.
	 *
	 * @return GdImage|WP_Error GD resource on success, WP_Error on failure.
	 */
	private static function create_gd_resource( string $path, string $mime ) {
		switch ( $mime ) {
			case 'image/png':
				$resource = @imagecreatefrompng( $path );
				break;
			case 'image/jpeg':
			case 'image/jpg':
				$resource = @imagecreatefromjpeg( $path );
				break;
			default:
				return new WP_Error(
					'wpac_unsupported_mime',
					sprintf(
						/* translators: %s: MIME type */
						__( 'Tipo de imagen no soportado: %s', 'webp-avif-images-converter' ),
						$mime
					)
				);
		}

		if ( false === $resource ) {
			return new WP_Error(
				'wpac_gd_create_failed',
				sprintf(
					/* translators: %s: file path */
					__( 'GD no pudo crear un recurso para: %s', 'webp-avif-images-converter' ),
					$path
				)
			);
		}

		return $resource;
	}

	/**
	 * Log a message when WP_DEBUG is enabled.
	 *
	 * @param string $message Error message to log.
	 */
	private static function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[WebP/AVIF Converter] ' . $message );
		}
	}
}
