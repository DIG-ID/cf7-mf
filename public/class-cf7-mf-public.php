<?php

/**
 * The public-facing functionality of the plugin.
 *
 * Registers the [multifile] / [multifile*] form-tag, passes its options to the
 * browser (where images are resized before upload), validates the uploaded
 * files on the server and attaches them to the CF7 mail.
 *
 * @package    Cf7_Mf
 * @subpackage Cf7_Mf/public
 * @author     dig.id <hello@dig.id>
 */
class Cf7_Mf_Public {

	/**
	 * Default longest side (px) when the tag has no w:/h: option.
	 */
	const DEFAULT_MAX_SIDE = 1600;

	/**
	 * Default JPEG quality (0-100) when the tag has no quality: option.
	 */
	const DEFAULT_QUALITY = 85;

	/**
	 * Default maximum number of files when the tag has no max: option.
	 */
	const DEFAULT_MAX_FILES = 10;

	/**
	 * Default maximum size per file (bytes, after resizing) when the tag has no file-limit: option.
	 */
	const DEFAULT_FILE_LIMIT = 2097152; // 2 MB.

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param   string    $plugin_name       The name of the plugin.
	 * @param   string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// 1. Form Setup
		add_action( 'wpcf7_init', array( $this, 'cf7_mf_add_form_tag_file' ), 10, 0 );
		add_filter( 'wpcf7_form_enctype', array( $this, 'cf7_mf_form_enctype_filter' ), 10, 1 );

		// 2. Validation (CF7 passes the moved upload paths as 3rd argument).
		add_filter( 'wpcf7_validate_multifile', array( $this, 'cf7_mf_validation_filter' ), 10, 3 );
		add_filter( 'wpcf7_validate_multifile*', array( $this, 'cf7_mf_validation_filter' ), 10, 3 );
		// CF7 skips the filters above when the field is missing from $_FILES entirely.
		add_filter( 'wpcf7_validate', array( $this, 'cf7_mf_validate_missing_fields' ), 10, 2 );

		// 3. Email Handling
		add_filter( 'wpcf7_mail_tag_replaced_file', array( $this, 'cf7_mf_file_mail_tag' ), 10, 4 );
		add_filter( 'wpcf7_mail_tag_replaced_file*', array( $this, 'cf7_mf_file_mail_tag' ), 10, 4 );
		add_filter( 'wpcf7_mail_components', array( $this, 'cf7_mf_mail_components' ), 10, 3 );

		// 4. Messages and Cleanup
		add_filter( 'wpcf7_messages', array( $this, 'cf7_mf_messages' ), 10, 1 );
		add_action( 'wpcf7_mail_sent', array( $this, 'cf7_mf_cleanup_temp_files' ) );

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cf7-mf-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cf7-mf-public.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Writes a debug line to the PHP error log, only when WP_DEBUG is on.
	 *
	 * @param string $message Message.
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CF7-MF: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Reads a byte-size option such as `file-limit:2097152`, `file-limit:800kb`
	 * or `file-limit:2mb`.
	 *
	 * @param WPCF7_FormTag $tag     Form-tag.
	 * @param string        $name    Option name.
	 * @param int           $default Value when the option is missing.
	 * @return int Bytes (0 = no limit).
	 */
	private function get_bytes_option( $tag, $name, $default = 0 ) {
		$matches = $tag->get_first_match_option(
			'/^' . preg_quote( $name, '/' ) . ':([1-9][0-9]*)([kKmM]?[bB])?$/'
		);

		if ( ! $matches ) {
			return (int) $default;
		}

		$size = (int) $matches[1];

		if ( ! empty( $matches[2] ) ) {
			$unit = strtolower( $matches[2] );

			if ( 'kb' === $unit ) {
				$size *= KB_IN_BYTES;
			} elseif ( 'mb' === $unit ) {
				$size *= MB_IN_BYTES;
			}
		}

		return $size;
	}

	/**
	 * Resolves the effective settings of a multifile tag.
	 *
	 * Used by the tag handler (to pass them to the browser) and by the server
	 * validation, so both sides always apply the same values.
	 *
	 * @param WPCF7_FormTag $tag Form-tag.
	 * @return array
	 */
	private function get_settings( $tag ) {
		$width  = (int) $tag->get_option( 'w', 'int', true );
		$height = (int) $tag->get_option( 'h', 'int', true );

		// w:/h: = longest side; if only one is given it applies to both.
		if ( ! $width && ! $height ) {
			$width  = self::DEFAULT_MAX_SIDE;
			$height = self::DEFAULT_MAX_SIDE;
		} elseif ( ! $width ) {
			$width = $height;
		} elseif ( ! $height ) {
			$height = $width;
		}

		$quality = $tag->get_option( 'quality', 'int', true );
		$quality = ( '' === $quality || false === $quality || null === $quality )
			? self::DEFAULT_QUALITY
			: max( 1, min( 100, (int) $quality ) );

		$min = $tag->get_option( 'min', 'int', true );
		$max = $tag->get_option( 'max', 'int', true );

		return array(
			'max_width'   => $width,
			'max_height'  => $height,
			'quality'     => $quality,
			'min_files'   => wpcf7_is_number( $min ) ? (int) $min : 0,
			'max_files'   => wpcf7_is_number( $max ) && (int) $max > 0 ? (int) $max : self::DEFAULT_MAX_FILES,
			'file_limit'  => $this->get_bytes_option( $tag, 'file-limit', self::DEFAULT_FILE_LIMIT ),
			'total_limit' => $this->get_bytes_option( $tag, 'total-limit', 0 ),
		);
	}

	/**
	 * Formats bytes for messages (e.g. "2 MB", "800 KB").
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	private function format_bytes( $bytes ) {
		if ( $bytes >= MB_IN_BYTES ) {
			return rtrim( rtrim( number_format( $bytes / MB_IN_BYTES, 1, '.', '' ), '0' ), '.' ) . ' MB';
		}

		return (int) ceil( $bytes / KB_IN_BYTES ) . ' KB';
	}

	/**
	 * Validate uploaded files.
	 *
	 * @param WPCF7_Validation $result Validation result.
	 * @param WPCF7_FormTag    $tag    Form-tag.
	 * @param array            $args   { @type string[] $uploaded_files Paths of the uploaded files. }
	 * @return WPCF7_Validation
	 */
	public function cf7_mf_validation_filter( $result, $tag, $args = array() ) {
		$name     = $tag->name;
		$settings = $this->get_settings( $tag );

		// Sizes of the files that actually arrived.
		$sizes = array();

		if ( isset( $args['uploaded_files'] ) ) {
			foreach ( (array) $args['uploaded_files'] as $path ) {
				if ( $path && file_exists( $path ) ) {
					$sizes[ wp_basename( $path ) ] = (int) filesize( $path );
				}
			}
		} elseif ( ! empty( $_FILES[ $name ]['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$names  = wpcf7_array_flatten( $_FILES[ $name ]['name'] ); // phpcs:ignore
			$fsizes = wpcf7_array_flatten( $_FILES[ $name ]['size'] ); // phpcs:ignore
			foreach ( $names as $key => $file_name ) {
				if ( '' !== $file_name ) {
					$sizes[ $file_name ] = (int) $fsizes[ $key ];
				}
			}
		}

		$file_count = count( $sizes );
		$this->log( sprintf( 'validate %s: %d file(s), %d bytes', $name, $file_count, array_sum( $sizes ) ) );

		if ( 0 === $file_count && $tag->is_required() && $settings['min_files'] < 1 ) {
			$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
			return $result;
		}

		if ( $settings['min_files'] && $file_count < $settings['min_files'] ) {
			if ( 0 === $file_count && ! $tag->is_required() ) {
				return $result; // Optional field left empty.
			}

			$result->invalidate(
				$tag,
				str_replace( '__min_file_limit__', $settings['min_files'], wpcf7_get_message( 'cf7_mb_min_file' ) )
			);
			return $result;
		}

		if ( $file_count > $settings['max_files'] ) {
			$result->invalidate(
				$tag,
				str_replace( '__max_file_limit__', $settings['max_files'], wpcf7_get_message( 'cf7_mb_max_file' ) )
			);
			return $result;
		}

		if ( $settings['file_limit'] ) {
			foreach ( $sizes as $file_name => $size ) {
				if ( $size > $settings['file_limit'] ) {
					$result->invalidate(
						$tag,
						str_replace(
							array( '__file_name__', '__file_limit__' ),
							array( $file_name, $this->format_bytes( $settings['file_limit'] ) ),
							wpcf7_get_message( 'cf7_mf_file_too_large' )
						)
					);
					return $result;
				}
			}
		}

		if ( $settings['total_limit'] && array_sum( $sizes ) > $settings['total_limit'] ) {
			$result->invalidate(
				$tag,
				str_replace(
					'__total_limit__',
					$this->format_bytes( $settings['total_limit'] ),
					wpcf7_get_message( 'cf7_mf_total_too_large' )
				)
			);
			return $result;
		}

		return $result;
	}

	/**
	 * Invalidates required [multifile*] fields that are missing from the request.
	 *
	 * A browser always sends the (possibly empty) file field, but a crafted
	 * request may omit it, and CF7 then never calls wpcf7_validate_multifile*.
	 *
	 * @param WPCF7_Validation $result Validation result.
	 * @param WPCF7_FormTag[]  $tags   Non-file tags (unused).
	 * @return WPCF7_Validation
	 */
	public function cf7_mf_validate_missing_fields( $result, $tags ) {
		$contact_form = wpcf7_get_current_contact_form();

		if ( ! $contact_form ) {
			return $result;
		}

		foreach ( $contact_form->scan_form_tags( array( 'type' => array( 'multifile*' ) ) ) as $tag ) {
			if ( empty( $_FILES[ $tag->name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
			}
		}

		return $result;
	}

	/**
	 * Adds the files of [multifile] fields to the mail being generated.
	 *
	 * Like CF7 core (WPCF7_Mail::attachments()), a field's files are attached
	 * only when its mail-tag (e.g. [file-fahrzeugausweis]) is in the
	 * "File attachments" box of *this* mail. So Mail (2) without attachment
	 * tags gets no files.
	 *
	 * @param array             $components Mail components.
	 * @param WPCF7_ContactForm $form       Contact form.
	 * @param WPCF7_Mail        $mail       Mail being generated (Mail or Mail 2).
	 * @return array
	 */
	public function cf7_mf_mail_components( $components, $form, $mail ) {
		try {
			$submission = WPCF7_Submission::get_instance();
			if ( ! $submission || ! $form || ! ( $mail instanceof WPCF7_Mail ) ) {
				return $components;
			}

			$uploaded_files = $submission->uploaded_files();
			if ( empty( $uploaded_files ) ) {
				return $components;
			}

			$template = (string) $mail->get( 'attachments' );

			$attachments = isset( $components['attachments'] )
						  ? (array) $components['attachments'] : array();

			$multifile_tags = $form->scan_form_tags( array( 'type' => array( 'multifile', 'multifile*' ) ) );

			foreach ( $multifile_tags as $tag ) {
				$name = $tag->name;

				if ( empty( $uploaded_files[ $name ] ) || false === strpos( $template, "[{$name}]" ) ) {
					continue;
				}

				foreach ( (array) $uploaded_files[ $name ] as $path ) {
					if ( ! empty( $path ) && file_exists( $path ) ) {
						$attachments[] = $path;
					}
				}
			}

			if ( ! empty( $attachments ) ) {
				$components['attachments'] = array_values( array_unique( $attachments ) );
				$this->log( sprintf( '%s: %d attachment(s)', $mail->name(), count( $components['attachments'] ) ) );
			}
		} catch ( Exception $e ) {
			$this->log( 'Error in mail components: ' . $e->getMessage() );
		}

		return $components;
	}

	/**
	 * Clean up temporary files
	 */
	public function cf7_mf_cleanup_temp_files( $contact_form ) {
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$uploaded_files = $submission->uploaded_files();
		if ( empty( $uploaded_files ) ) {
			return;
		}

		foreach ( $uploaded_files as $name => $paths ) {
			foreach ( (array) $paths as $path ) {
				if ( file_exists( $path ) && ! @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$this->log( "Failed to delete: $path" );
				}
			}
		}
	}


	/**
	 * Registers the [multifile] and [multifile*] form-tags.
	 *
	 * @return void
	 */
	public function cf7_mf_add_form_tag_file() {

		wpcf7_add_form_tag(
			array( 'multifile', 'multifile*' ),
			array( $this, 'cf7_mf_file_form_tag_handler' ),
			array(
				'name-attr'      => true,
				'file-uploading' => true,
			),
		);

	}

	/**
	 * Renders the [multifile] form-tag.
	 *
	 * All settings are written as data-* attributes on .cf7-mf-container,
	 * which is where the JS reads them.
	 *
	 * @param WPCF7_FormTag $tag
	 * @return string
	 */
	public function cf7_mf_file_form_tag_handler( $tag ) {

		$tag = new WPCF7_FormTag( $tag );

		if ( empty( $tag->name ) ) {
			return '';
		}

		$validation_error = wpcf7_get_validation_error( $tag->name );
		$class            = wpcf7_form_controls_class( $tag->type );

		if ( $validation_error ) :
			$class .= ' wpcf7-not-valid';
		endif;

		$settings = $this->get_settings( $tag );
		$accept   = wpcf7_acceptable_filetypes( $tag->get_option( 'filetypes' ), 'attr' );

		$atts = array();

		$atts['class']    = $tag->get_class_option( $class );
		$atts['id']       = $tag->get_id_option();
		$atts['capture']  = $tag->get_option( 'capture', '(user|environment)', true );
		$atts['tabindex'] = $tag->get_option( 'tabindex', 'signed_int', true );
		$atts['accept']   = $accept;

		if ( $tag->is_required() ) {
			$atts['aria-required'] = 'true';
		}

		if ( $validation_error ) {
			$atts['aria-invalid']     = 'true';
			$atts['aria-describedby'] = wpcf7_get_validation_error_reference(
				$tag->name
			);
		} else {
			$atts['aria-invalid'] = 'false';
		}

		$atts['type']     = 'file';
		$atts['name']     = $tag->name . '[]';
		$atts['multiple'] = 'multiple';

		$container_atts = array(
			'class'                => 'cf7-mf-container',
			'data-max-width'       => $settings['max_width'],
			'data-max-height'      => $settings['max_height'],
			'data-quality'         => $settings['quality'],
			'data-min-files'       => $settings['min_files'],
			'data-max-files'       => $settings['max_files'],
			'data-file-limit'      => $settings['file_limit'],
			'data-total-limit'     => $settings['total_limit'],
			'data-accept'          => $accept,
			// PHP silently drops files beyond max_file_uploads (per request, whole form).
			'data-max-uploads'     => (int) ini_get( 'max_file_uploads' ),
			'data-msg-max-files'   => str_replace( '__max_file_limit__', $settings['max_files'], wpcf7_get_message( 'cf7_mb_max_file' ) ),
			'data-msg-file-large'  => str_replace( '__file_limit__', $this->format_bytes( $settings['file_limit'] ), wpcf7_get_message( 'cf7_mf_file_too_large' ) ),
			'data-msg-total-large' => $settings['total_limit']
				? str_replace( '__total_limit__', $this->format_bytes( $settings['total_limit'] ), wpcf7_get_message( 'cf7_mf_total_too_large' ) )
				: null,
			'data-msg-type'        => wpcf7_get_message( 'upload_file_type_invalid' ),
			'data-msg-process'     => wpcf7_get_message( 'cf7_mf_processing_failed' ),
			'data-msg-max-uploads' => wpcf7_get_message( 'cf7_mf_max_uploads' ),
		);

		$html = '';
		$html .= '<div ' . wpcf7_format_atts( $container_atts ) . '>';
		$html .= '<div class="cf7-mf-content-wrapper">';

		$html .= '<div class="cf7-mf-drag-drop-zone">';
		$html .= '<span class="cf7-mf-add-icon"></span>';
		$html .= '<span class="cf7-mf-add-text">' . esc_html__( 'Drop files here or click to add.', 'cf7-mf' ) . '</span>';
		$html .= '</div>';

		$html .= '<div class="cf7-mf-preview" ></div>';
		$html .= '</div>';

		// Hidden input for submitting files.
		$html .= sprintf(
			'<span class="wpcf7-form-control-wrap" data-name="%1$s"><input %2$s />%3$s</span>',
			esc_attr( $tag->name ),
			wpcf7_format_atts( $atts ),
			$validation_error
		);

		$html .= '<div class="cf7-mf-feedback-message" role="alert" aria-live="polite" style="display: none;"></div>
							<div class="cf7-mf-progress-bar-wrapper" style="display: none;">
								<div class="cf7-mf-progress-bar">
										<div class="cf7-mf-progress-bar-fill"></div>
								</div>
								<div class="cf7-mf-progress-text">0%</div>
							</div>';

		$html .= '</div>';

		return $html;

	}

	/**
	 * Sets multipart/form-data when the form has file-uploading tags.
	 *
	 * @param string $enctype
	 * @return string
	 */
	public function cf7_mf_form_enctype_filter( $enctype ) {

		$multipart = (bool) wpcf7_scan_form_tags( array(
			'feature' => 'file-uploading',
		) );

		if ( $multipart ) {
			$enctype = 'multipart/form-data';
		}

		return $enctype;

	}

	/**
	 * Add custom messages
	 *
	 * Placeholders: __min_file_limit__, __max_file_limit__, __file_name__,
	 * __file_limit__, __total_limit__, __max_uploads__.
	 *
	 * @param array $messages
	 * @return array
	 */
	public function cf7_mf_messages( $messages ) {

		return array_merge(
			$messages,
			array(
					'upload_failed' => array(
						'description' => __( 'Das Hochladen einer Datei schlägt aus irgendeinem Grund fehl.', 'cf7-mf' ),
						'default'     => __( 'Beim Hochladen der Datei auf den Server ist ein Fehler aufgetreten.', 'cf7-mf' ),
					),
					'upload_file_type_invalid' => array(
						'description' => __( 'Der hochgeladene Dateityp ist nicht erlaubt.', 'cf7-mf' ),
						'default'     => __( 'Sie dürfen Dateien dieses Typs nicht hochladen.', 'cf7-mf' ),
					),
					'upload_file_too_large' => array(
						'description' => __( 'Die hochgeladene Datei ist zu gross.', 'cf7-mf' ),
						'default'     => __( 'Die hochgeladene Datei ist zu gross.', 'cf7-mf' ),
					),
					'upload_failed_php_error' => array(
						'description' => __( 'Das Hochladen einer Datei schlägt aufgrund eines PHP-Fehlers fehl.', 'cf7-mf' ),
						'default'     => __( 'Beim Hochladen der Datei ist ein Fehler aufgetreten.', 'cf7-mf' ),
					),
					'cf7_mb_min_file' => array(
						'description' => __( 'Multifile: zu wenige Dateien (Platzhalter __min_file_limit__).', 'cf7-mf' ),
						'default'     => __( 'Sie müssen mindestens __min_file_limit__ Dateien hochladen.', 'cf7-mf' ),
					),
					'cf7_mb_max_file' => array(
						'description' => __( 'Multifile: zu viele Dateien (Platzhalter __max_file_limit__).', 'cf7-mf' ),
						'default'     => __( 'Sie können nicht mehr als __max_file_limit__ Dateien pro Anfrage hochladen.', 'cf7-mf' ),
					),
					'cf7_mf_file_too_large' => array(
						'description' => __( 'Multifile: eine Datei ist nach dem Verkleinern grösser als file-limit (Platzhalter __file_name__, __file_limit__).', 'cf7-mf' ),
						'default'     => __( 'Die Datei «__file_name__» ist zu gross (max. __file_limit__ pro Datei).', 'cf7-mf' ),
					),
					'cf7_mf_total_too_large' => array(
						'description' => __( 'Multifile: alle Dateien zusammen sind grösser als total-limit (Platzhalter __total_limit__).', 'cf7-mf' ),
						'default'     => __( 'Die Dateien sind zusammen zu gross (max. __total_limit__). Bitte entfernen Sie einige Bilder.', 'cf7-mf' ),
					),
					'cf7_mf_processing_failed' => array(
						'description' => __( 'Multifile: ein Bild kann im Browser nicht gelesen werden, z. B. HEIC (Platzhalter __file_name__).', 'cf7-mf' ),
						'default'     => __( 'Das Bild «__file_name__» konnte nicht verarbeitet werden. Bitte laden Sie es als JPG oder PNG hoch.', 'cf7-mf' ),
					),
					'cf7_mf_max_uploads' => array(
						'description' => __( 'Multifile: der Server nimmt pro Anfrage nicht mehr Dateien an (PHP max_file_uploads, Platzhalter __max_uploads__).', 'cf7-mf' ),
						'default'     => __( 'Pro Anfrage können insgesamt höchstens __max_uploads__ Dateien gesendet werden.', 'cf7-mf' ),
					),
			)
		);

	}

	/**
	 * Replaces the [field-name] mail-tag with the uploaded file names.
	 *
	 * @param string            $replaced
	 * @param mixed             $submitted
	 * @param bool              $html
	 * @param WPCF7_MailTag     $mail_tag
	 * @return string
	 */
	public function cf7_mf_file_mail_tag( $replaced, $submitted, $html, $mail_tag ) {

		$submission = WPCF7_Submission::get_instance();
		$uploaded_files = $submission->uploaded_files();
		$name = $mail_tag->field_name();

		// Check if there are uploaded files for this field name.
		if ( ! empty( $uploaded_files[$name] ) ) {
			$paths = (array) $uploaded_files[$name]; // Get the files for this field.
			$paths = array_map( 'wp_basename', $paths ); // Extract base names.

			// Replace the mail tag with the file paths.
			$replaced = wpcf7_flat_join( $paths, array(
				'separator' => wp_get_list_item_separator(),
			) );
		}

		return $replaced;

	}

}
