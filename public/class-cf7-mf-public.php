<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://dig.id
 * @since      1.0.0
 *
 * @package    Cf7_Mf
 * @subpackage Cf7_Mf/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Cf7_Mf
 * @subpackage Cf7_Mf/public
 * @author     dig.id <hello@dig.id>
 */
class Cf7_Mf_Public {

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
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $btn_tag_name;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name  = $plugin_name;
		$this->version      = $version;
		$this->btn_tag_name = 'cf7-mf-upload-button';

		add_action( 'wpcf7_init', array( $this, 'cf7_mf_add_form_tag_file' ) );
		add_filter( 'wpcf7_form_enctype', array( $this, 'cf7_mf_form_enctype_filter' ) );

		//add_filter( 'wpcf7_validate_multifile', array( $this, 'cf7_mf_validation_filter' ), 10, 3 );
		//add_filter( 'wpcf7_validate_multifile*', array( $this, 'cf7_mf_validation_filter' ), 10, 3 );

		add_action( 'wpcf7_swv_create_schema', array( $this, 'cf7_mf_swv_add_file_rules' ), 10, 2 );

		add_filter( 'wpcf7_mail_tag_replaced_file', array( $this, 'cf7_mf_file_mail_tag' ), 10, 4 );
		add_filter( 'wpcf7_mail_tag_replaced_file*', array( $this, 'cf7_mf_file_mail_tag' ), 10, 4 );

		add_filter( 'wpcf7_messages', array( $this, 'cf7_mf_messages' ), 10, 1 );

	}


	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cf7_Mf_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cf7_Mf_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cf7-mf-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cf7_Mf_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cf7_Mf_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cf7-mf-public.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Undocumented function
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
	 * Undocumented function
	 *
	 * @param [type] $tag
	 * @return void
	 */
	public function cf7_mf_file_form_tag_handler( $tag ) {

		if ( empty( $tag->name ) ) {
			return '';
		}
		$tag = new WPCF7_FormTag( $tag );

		$validation_error = wpcf7_get_validation_error( $tag->name );
		$class            = wpcf7_form_controls_class( $tag->type );

		if ( $validation_error ) :
			$class .= ' wpcf7-not-valid';
		endif;

		$atts = array();

		$atts['size']        = $tag->get_size_option( '40' );
		$atts['class']       = $tag->get_class_option( $class );
		$atts['id']          = $tag->get_id_option();
		$atts['file-limit']  = $tag->get_option( 'file-limit', 'int', true ) ?: 1024 * 1024;
		$atts['total-limit'] = $tag->get_option( 'total-limit', 'int', true ) ?: 10 * 1024 * 1024;
		$atts['min']         = $tag->get_option( 'min', 'signed_num', true );
		$atts['max']         = $tag->get_option( 'max', 'signed_num', true );

		$atts['width']       = $tag->get_option( 'w', 'int', true ) ?: 720;
		$atts['height']      = $tag->get_option( 'h', 'int', true ) ?: 480;

		$atts['tabindex']     = $tag->get_option( 'tabindex', 'signed_int', true );

		$atts['accept'] = wpcf7_acceptable_filetypes(
			$tag->get_option( 'filetypes' ), 'attr'
		);

		$values = isset( $tag->values[0] ) ? $tag->values[0] : '';
		if ( empty( $values ) ) :
			$values = __( 'Upload', 'cf7-mf' );
		endif;

		$upload_label = $atts['value'] = $values;


		if ( $tag->is_required() ) {
			$atts['aria-required'] = 'true';
		}

		if ( $validation_error ) {
			$atts['aria-invalid'] = 'true';
			$atts['aria-describedby'] = wpcf7_get_validation_error_reference(
				$tag->name
			);
		} else {
			$atts['aria-invalid'] = 'false';
		}

		if ( 'range' === $tag->basetype ) {
			if ( ! wpcf7_is_number( $atts['min'] ) ) {
				$atts['min'] = '0';
			}

			if ( ! wpcf7_is_number( $atts['max'] ) ) {
				$atts['max'] = '10';
			}

			if ( '' === $atts['value'] ) {
				if ( $atts['min'] < $atts['max'] ) {
					$atts['value'] = ( $atts['min'] + $atts['max'] ) / 2;
				} else {
					$atts['value'] = $atts['min'];
				}
			}
		}

		$atts['type']     = 'file';
		$atts['name']     = $tag->name . '[]';
		$atts['multiple'] = 'multiple';

		/*$html = sprintf(
			'<span class="wpcf7-form-control-wrap" data-name="%1$s"><input %2$s />%3$s</span>',
			esc_attr( $tag->name ),
			wpcf7_format_atts( $atts ),
			$validation_error
		);*/

		$button_name = $tag->name . '-' . $this->btn_tag_name;

		$html = '';
		//$html .= '<span class="wpcf7-form-control-wrap" data-name="' . $button_name . '"><button type="button" name="' . $button_name . '" class="button button-primary qbutton" id="cf7-mf-add-file" value="' . $upload_label . '"></span>';



		$html .= sprintf(
			'<span class="wpcf7-form-control-wrap" data-name="%1$s">',
			esc_attr( $tag->name ),
			$validation_error
		);

		$html .= '<div class="cf7-mf-content-wrapper">';
		// Preview area.<figure><span class="delete-icon"></span><img src="https://picsum.photos/720/480" alt=""></figure>
		$html .= '<div class="cf7-mf-preview"></div>';
		$html .= sprintf(
			'<div class="cf7-mf-drag-drop-zone" data-file-limit="%1$d" data-total-limit="%2$d" data-width="%3$d" data-height="%4$d">',
			esc_attr( $atts['file-limit'] ),
			esc_attr( $atts['total-limit'] ),
			esc_attr( $atts['width'] ),
			esc_attr( $atts['height'] )
		);
		$html .= '<span class="cf7-mf-add-icon"></span>';
		$html .= '<span class="cf7-mf-add-text">' . esc_html__( 'Drop files here or click to add.', 'cf7-mf' ) . '</span>';
		$html .= '</div>';

		$html .= '</div>';

		$html .= '</span">';

		// Hidden input for submitting files.
		$html .= sprintf(
			'<input %2$s />%3$s',
			esc_attr( $tag->name ),
			wpcf7_format_atts( $atts ),
			$validation_error
		);

		$html = '<div id="cf7-mf-container">' . $html . '<p class="cf7-mf-feedback-message"></p></div>';

		return $html;

	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $enctype
	 * @return void
	 */
	public function cf7_mf_form_enctype_filter( $enctype ) {

		/* Enctype filter */
		$multipart = (bool) wpcf7_scan_form_tags( array( 'type' => array( 'multifile', 'multifile*' ) ) );

		if ( $multipart ) :
			$enctype = 'multipart/form-data';
		endif;
		return $enctype;

	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $schema
	 * @param [type] $contact_form
	 * @return void
	 */
	public function cf7_mf_swv_add_file_rules( $schema, $contact_form ) {

		$tags = $contact_form->scan_form_tags(
			array(
				'basetype' => array( 'file' ),
			)
		);

		foreach ( $tags as $tag ) :
			// Check if the file field is required
			if ( $tag->is_required() ) {
				$schema->add_rule(
					wpcf7_swv_create_rule( 'requiredfile', array(
						'field' => $tag->name,
						'error' => wpcf7_get_message( 'invalid_required' ),
					) )
				);
			}

			// File type validation (file extensions)
			$schema->add_rule(
				wpcf7_swv_create_rule( 'file', array(
					'field' => $tag->name,
					'accept' => explode( ',', wpcf7_acceptable_filetypes(
						$tag->get_option( 'filetypes' ), 'attr'
					) ),
					'error' => wpcf7_get_message( 'upload_file_type_invalid' ),
				) )
			);

			// File size validation
			$schema->add_rule(
				wpcf7_swv_create_rule( 'maxfilesize', array(
					'field' => $tag->name,
					'threshold' => $tag->get_limit_option(),
					'error' => wpcf7_get_message( 'upload_file_too_large' ),
				) )
			);

			// Min and Max file count validation
			$min_files = $tag->get_option( 'min' );
			$max_files = $tag->get_option( 'max' );

			if ( ! empty( $min_files ) ) {
				$schema->add_rule(
					wpcf7_swv_create_rule( 'minfilecount', array(
						'field' => $tag->name,
						'min'    => $min_files[0],
						'error'  => wpcf7_get_message( 'min_file_count_validation_msg' ),
					) )
				);
			}

			if ( ! empty( $max_files ) ) {
				$schema->add_rule(
					wpcf7_swv_create_rule( 'maxfilecount', array(
						'field' => $tag->name,
						'max'    => $max_files[0],
						'error'  => wpcf7_get_message( 'max_file_count_validation_msg' ),
					) )
				);
			}
		endforeach;

	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function cf7_mf_file_mail_tag( $replaced, $submitted, $html, $mail_tag ) {

		$submission = WPCF7_Submission::get_instance();
		$uploaded_files = $submission->uploaded_files();
		$name = $mail_tag->field_name();

		if ( ! empty( $uploaded_files[$name] ) ) {
			$paths = (array) $uploaded_files[$name];
			$paths = array_map( 'wp_basename', $paths );

			$replaced = wpcf7_flat_join( $paths, array(
				'separator' => wp_get_list_item_separator(),
			) );
		}

		return $replaced;

	}




	/**
	 * Undocumented function
	 *
	 * @param [type] $result
	 * @param [type] $tag
	 * @param [type] $args
	 * @return void
	 */
	public function cf7_mf_validation_filter( $result, $tag, $args ) {
		$args = wp_parse_args($args, array());
		global $latest_contact_form_7;

		if ($latest_contact_form_7) {
			$tag = new WPCF7_FormTag($tag);
		} else {
			$tag = new WPCF7_Shortcode($tag);
		}

		$name = $tag->name;
		$id = $tag->get_id_option();
		$uniqid = uniqid();
		$original_files_array = isset($_FILES[$name]) ? $_FILES[$name] : null;
		
		// If no files were uploaded, return an empty array for validation
		if ($original_files_array === null) {
			$original_files_array['tmp_name'] = array();
		}

		if (isset($_FILES[$name]) && isset($_FILES[$name]['name'])) {
				$total = count($_FILES[$name]['name']);
		} else {
				$total = 0;
		}

		$files = array();
		$new_files = array();

		// Collect all uploaded files
		for ($i = 0; $i < $total; $i++) {
			if (empty($original_files_array['tmp_name'][$i])) {
				continue;
			}
			$files[] = array(
				'name'     => $original_files_array['name'][$i],
				'type'     => $original_files_array['type'][$i],
				'tmp_name' => $original_files_array['tmp_name'][$i],
				'error'    => $original_files_array['error'][$i],
				'size'     => $original_files_array['size'][$i]
			);
		}

		// Validate minimum and maximum file counts
		$file_count = count($files);
		$min_file_allow = $tag->get_option('min');
		if (!empty($min_file_allow) && $file_count < $min_file_allow[0]) {
			$message = wpcf7_get_message('min_file_count_validation_msg');
			$message = str_replace('__min_file_limit__', $min_file_allow[0], $message);
			$result->invalidate($tag, $message);
			return $result;
		}

		$max_file_allow = $tag->get_option('max');
		if (!empty($max_file_allow) && $file_count > $max_file_allow[0]) {
			$message = wpcf7_get_message('max_file_count_validation_msg');
			$message = str_replace('__max_file_limit__', $max_file_allow[0], $message);
			$result->invalidate($tag, $message);
			return $result;
		}

		// Loop through each file for validation
		foreach ($files as $file) {
			// Check for upload errors
			if ($file['error'] && UPLOAD_ERR_NO_FILE != $file['error']) {
				$result->invalidate($tag, wpcf7_get_message('upload_failed_php_error'));
				return $result;
			}

			// Validate allowed file types
			$allowed_file_types = array();
			if ($file_types_a = $tag->get_option('filetypes')) {
				foreach ($file_types_a as $file_types) {
					$file_types = explode('|', $file_types);
					foreach ($file_types as $file_type) {
						$allowed_file_types[] = trim($file_type, '.');
					}
				}
			}

			$allowed_file_types = array_unique($allowed_file_types);
			$file_type_pattern = implode('|', $allowed_file_types);

			// Default file types if none are set
			if ( empty( $file_type_pattern) ) {
				$file_type_pattern = 'jpg|jpeg|png|gif|pdf|doc|docx|ppt|pptx|odt|avi|ogg|m4a|mov|mp3|mp4|mpg|wav|wmv|txt';
			}

			$file_type_pattern = '/\.' . $file_type_pattern . '$/i';
			if (!preg_match($file_type_pattern, $file['name'])) {
				$result->invalidate($tag, wpcf7_get_message('upload_file_type_invalid'));
				return $result;
			}

			// Validate file size
			$allowed_size = apply_filters('cf7_mf_max_size', 10485760); // default 10 MB
			if ($file['size'] > $allowed_size) {
				$result->invalidate($tag, wpcf7_get_message('upload_file_too_large'));
				return $result;
			}

			// Process file uploads: move files to the upload directory
			wpcf7_init_uploads();
			$uploads_dir = wpcf7_upload_tmp_dir();
			$uploads_dir = wpcf7_maybe_add_random_dir($uploads_dir);
			$filename = sanitize_file_name($file['name']);
			$filename = wp_unique_filename($uploads_dir, $filename);
			$new_file = trailingslashit($uploads_dir) . $filename;

			// Move the file
			if (false === @move_uploaded_file($file['tmp_name'], $new_file)) {
				$result->invalidate($tag, wpcf7_get_message('upload_failed'));
				return $result;
			}

			$new_files[] = $new_file;
			@chmod($new_file, 0400); // Make file readable only by owner
		}

		// Ensure that at least one file is uploaded if the field is required
		if (count($files) == 0 && $tag->is_required()) {
			$result->invalidate($tag, wpcf7_get_message('invalid_required'));
			return $result;
		}

		// Add uploaded files to submission
		if ($submission = WPCF7_Submission::get_instance()) {
			foreach ($new_files as $new_file) {
				$submission->add_uploaded_file($name, $new_file);
			}
		}

		return $result;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function cf7_mf_messages( $messages ) {

		return array_merge(
			$messages,
			array(
				'upload_failed' => array(
					'description' => __( 'Uploading a file fails for any reason', 'cf7-mf' ),
					'default'     => __( 'There was an error uploading the file to the server.', 'cf7-mf' ),
				),
				'zipping_failed' => array(
					'description' => __( 'Zipping files fails for any reason', 'cf7-mf' ),
					'default'     => __( 'There was an error in zippng the files.', 'cf7-mf' ),
				),
				'upload_file_type_invalid' => array(
					'description' => __( 'Uploaded file is not allowed for file type', 'cf7-mf' ),
					'default'     => __( 'You are not allowed to upload files of this type.', 'cf7-mf' ),
				),
				'upload_file_too_large' => array(
					'description' => __( 'Uploaded file is too large', 'cf7-mf' ),
					'default'     => __( 'Uploaded file is too big.', 'cf7-mf' ),
				),
				'upload_failed_php_error' => array(
					'description' => __( 'Uploading a file fails for PHP error', 'cf7-mf' ),
					'default'     => __( 'There was an error uploading the file.', 'cf7-mf' ),
				),
				'cf7_mb_min_file' => array(
					'description' => __( 'You need to upload atleast __min_file_limit__ files.', 'cf7-mf' ),
					'default'     => __( 'You need to upload atleast __min_file_limit__ files.', 'cf7-mf' ),
				),
				'cf7_mb_max_file' => array(
					'description' => __( 'You can not upload more than __max_file_limit__ files per request', 'cf7-mf' ),
					'default'     => __( 'You can not upload more than __max_file_limit__ files per request.', 'cf7-mf' ),
				),
			)
		);
	}

}
