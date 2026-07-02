<?php
/**
 * Admin settings page — Tools → Convertidor de imágenes.
 *
 * @package Webp_Avif_Images_Converter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Webp_Avif_Images_Converter_Settings
 *
 * Registers a Tools menu page using the Settings API, renders Spanish-language
 * UI for input/output format selection and quality configuration, and enforces
 * nonce + capability security on all submissions.
 */
class Webp_Avif_Images_Converter_Settings {

	/**
	 * Allowed input format slugs.
	 *
	 * @var string[]
	 */
	private const ALLOWED_INPUT_FORMATS = array( 'png', 'jpg', 'gif' );

	/**
	 * Allowed output format slugs.
	 *
	 * @var string[]
	 */
	private const ALLOWED_OUTPUT_FORMATS = array( 'webp', 'avif' );

	/**
	 * Option group name for Settings API.
	 *
	 * @var string
	 */
	private const OPTION_GROUP = 'webp_avif_images_converter_options_group';

	/**
	 * Option name for plugin settings.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'webp_avif_images_converter_settings';

	/**
	 * Settings section ID.
	 *
	 * @var string
	 */
	private const SECTION_ID = 'webp_avif_main_section';

	/**
	 * Nonce action name.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'webp_avif_images_converter_settings';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	private const NONCE_NAME = '_wpnonce_webp_avif';

	/**
	 * Whether AVIF is available on this server.
	 *
	 * @var bool
	 */
	private bool $avif_available;

	/**
	 * Constructor — hook into WordPress admin.
	 */
	public function __construct() {
		$this->avif_available = (bool) get_option( 'webp_avif_images_converter_avif_available', false );

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'avif_admin_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPAC_PLUGIN_DIR . 'webp-avif-images-converter.php' ), array( $this, 'add_plugin_action_link' ) );
	}

	/**
	 * Register the Tools menu page.
	 */
	public function add_menu_page(): void {
		add_management_page(
			__( 'Convertidor de imágenes', 'webp-avif-images-converter' ),
			__( 'Convertidor de imágenes', 'webp-avif-images-converter' ),
			'manage_options',
			'webp-avif-images-converter',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings, sections, and fields via the Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			__( 'Configuración de conversión', 'webp-avif-images-converter' ),
			array( $this, 'render_section_intro' ),
			'webp-avif-images-converter'
		);

		add_settings_field(
			'wpac_input_formats',
			__( 'Formatos de entrada', 'webp-avif-images-converter' ),
			array( $this, 'render_input_formats_field' ),
			'webp-avif-images-converter',
			self::SECTION_ID
		);

		add_settings_field(
			'wpac_quality',
			__( 'Calidad', 'webp-avif-images-converter' ),
			array( $this, 'render_quality_field' ),
			'webp-avif-images-converter',
			self::SECTION_ID
		);

		add_settings_field(
			'wpac_output_formats',
			__( 'Formatos de salida', 'webp-avif-images-converter' ),
			array( $this, 'render_output_formats_field' ),
			'webp-avif-images-converter',
			self::SECTION_ID
		);
	}

	/**
	 * Sanitize and validate submitted settings.
	 *
	 * @param mixed $input Raw input from the Settings API.
	 *
	 * @return array Sanitized settings array.
	 */
	public function sanitize_settings( $input ): array {
		$existing = get_option( self::OPTION_NAME, array() );

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			add_settings_error( self::OPTION_NAME, 'wpac_nonce_failed', __( 'Error de seguridad. La configuración no se guardó.', 'webp-avif-images-converter' ), 'error' );
			return is_array( $existing ) ? $existing : $this->get_defaults();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error( self::OPTION_NAME, 'wpac_cap_failed', __( 'No tienes permisos para guardar la configuración.', 'webp-avif-images-converter' ), 'error' );
			return is_array( $existing ) ? $existing : $this->get_defaults();
		}

		$sanitized = array();

		// Input formats: whitelist against allowed values, normalize jpeg → jpg.
		$raw_input = isset( $input['input_formats'] ) && is_array( $input['input_formats'] ) ? $input['input_formats'] : array();
		$raw_input = array_map( 'sanitize_text_field', $raw_input );
		$raw_input = array_map(
			function ( $fmt ) {
				return 'jpeg' === $fmt ? 'jpg' : $fmt;
			},
			$raw_input
		);
		$sanitized['input_formats'] = array_values( array_intersect( $raw_input, self::ALLOWED_INPUT_FORMATS ) );

		if ( empty( $sanitized['input_formats'] ) ) {
			add_settings_error( self::OPTION_NAME, 'wpac_no_input', __( 'Debes seleccionar al menos un formato de entrada.', 'webp-avif-images-converter' ), 'error' );
			$sanitized['input_formats'] = array( 'png', 'jpg', 'gif' );
		}

		// Quality: clamp 1–100.
		$raw_quality = isset( $input['quality'] ) ? absint( $input['quality'] ) : 100;
		$sanitized['quality'] = min( 100, max( 1, $raw_quality ) );

		// Output formats: whitelist, strip avif if unavailable.
		$raw_output = isset( $input['output_formats'] ) && is_array( $input['output_formats'] ) ? $input['output_formats'] : array();
		$raw_output = array_map( 'sanitize_text_field', $raw_output );
		$sanitized['output_formats'] = array_values( array_intersect( $raw_output, self::ALLOWED_OUTPUT_FORMATS ) );

		if ( ! $this->avif_available ) {
			$sanitized['output_formats'] = array_values( array_diff( $sanitized['output_formats'], array( 'avif' ) ) );
		}

		return $sanitized;
	}

	/**
	 * Render the settings page HTML.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Convertidor de imágenes', 'webp-avif-images-converter' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME, false );
				do_settings_sections( 'webp-avif-images-converter' );
				submit_button( __( 'Guardar configuración', 'webp-avif-images-converter' ) );
				?>
			</form>

			<p class="description">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: Bronto.ar link */
						__( 'Desarrollado por <a href="%s" target="_blank" rel="noopener noreferrer">Bronto.ar</a>', 'webp-avif-images-converter' ),
						'https://bronto.ar'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render admin notice when AVIF is not available.
	 */
	public function avif_admin_notice(): void {
		if ( $this->avif_available ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && 'tools_page_webp-avif-images-converter' === $screen->id ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'AVIF no está disponible en este servidor. Solo se usará WebP.', 'webp-avif-images-converter' );
		echo '</p></div>';
	}

	/**
	 * Add "Ajustes" link to the plugin action links on the Plugins page.
	 *
	 * @param array $links Existing action links.
	 *
	 * @return array Modified action links.
	 */
	public function add_plugin_action_link( array $links ): array {
		$settings_url  = admin_url( 'tools.php?page=webp-avif-images-converter' );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Ajustes', 'webp-avif-images-converter' ) . '</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Render the section introduction text.
	 */
	public function render_section_intro(): void {
		echo '<p>' . esc_html__( 'Configura cómo el plugin convierte las imágenes subidas.', 'webp-avif-images-converter' ) . '</p>';
	}

	/**
	 * Render the input formats field — checkboxes for PNG, JPG, GIF.
	 */
	public function render_input_formats_field(): void {
		$settings      = get_option( self::OPTION_NAME, $this->get_defaults() );
		$input_formats = $settings['input_formats'] ?? array( 'png', 'jpg', 'gif' );

		$formats = array(
			'png' => __( 'PNG', 'webp-avif-images-converter' ),
			'jpg' => __( 'JPG / JPEG', 'webp-avif-images-converter' ),
			'gif' => __( 'GIF', 'webp-avif-images-converter' ),
		);
		?>
		<fieldset>
			<legend class="screen-reader-text">
				<?php esc_html_e( 'Formatos de entrada', 'webp-avif-images-converter' ); ?>
			</legend>
			<?php foreach ( $formats as $value => $label ) : ?>
				<label for="wpac-input-<?php echo esc_attr( $value ); ?>">
					<input
						type="checkbox"
						name="<?php echo esc_attr( self::OPTION_NAME ); ?>[input_formats][]"
						value="<?php echo esc_attr( $value ); ?>"
						id="wpac-input-<?php echo esc_attr( $value ); ?>"
						<?php checked( in_array( $value, $input_formats, true ) ); ?>
					/>
					<?php echo esc_html( $label ); ?>
				</label>
				<br />
			<?php endforeach; ?>
			<p class="description">
				<?php esc_html_e( 'Selecciona los formatos de imagen que deseas convertir. Por defecto se convierten PNG, JPG y GIF.', 'webp-avif-images-converter' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render the quality range slider field.
	 */
	public function render_quality_field(): void {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$quality  = absint( $settings['quality'] ?? 100 );
		?>
		<input
			type="range"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[quality]"
			id="wpac-quality"
			min="1"
			max="100"
			step="1"
			value="<?php echo esc_attr( $quality ); ?>"
			oninput="document.getElementById('wpac-quality-value').textContent = this.value"
		/>
		<span id="wpac-quality-value"><?php echo esc_html( (string) $quality ); ?></span>%
		<p class="description">
			<?php esc_html_e( 'Calidad de compresión para WebP y AVIF (1–100). Un valor más alto produce mejor calidad pero archivos más grandes.', 'webp-avif-images-converter' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the output formats field — checkboxes for WebP and AVIF.
	 */
	public function render_output_formats_field(): void {
		$settings       = get_option( self::OPTION_NAME, $this->get_defaults() );
		$output_formats = $settings['output_formats'] ?? array( 'webp' );
		?>
		<fieldset>
			<legend class="screen-reader-text">
				<?php esc_html_e( 'Formatos de salida', 'webp-avif-images-converter' ); ?>
			</legend>
			<label for="wpac-output-webp">
				<input
					type="checkbox"
					name="<?php echo esc_attr( self::OPTION_NAME ); ?>[output_formats][]"
					value="webp"
					id="wpac-output-webp"
					<?php checked( in_array( 'webp', $output_formats, true ) ); ?>
				/>
				<?php esc_html_e( 'WebP', 'webp-avif-images-converter' ); ?>
			</label>
			<br />
			<label for="wpac-output-avif">
				<input
					type="checkbox"
					name="<?php echo esc_attr( self::OPTION_NAME ); ?>[output_formats][]"
					value="avif"
					id="wpac-output-avif"
					<?php disabled( ! $this->avif_available ); ?>
					<?php checked( in_array( 'avif', $output_formats, true ) && $this->avif_available ); ?>
				/>
				<?php esc_html_e( 'AVIF', 'webp-avif-images-converter' ); ?>
				<?php if ( ! $this->avif_available ) : ?>
					<span class="description" style="color: #d63638;">
						— <?php esc_html_e( 'No disponible en este servidor.', 'webp-avif-images-converter' ); ?>
					</span>
				<?php endif; ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Formatos de salida generados al subir imágenes. Podés seleccionar ambos.', 'webp-avif-images-converter' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings array.
	 */
	private function get_defaults(): array {
		return array(
			'input_formats'  => array( 'png', 'jpg', 'gif' ),
			'quality'        => 100,
			'output_formats' => array( 'webp' ),
		);
	}
}
