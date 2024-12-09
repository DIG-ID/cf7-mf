<?php

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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param   string    $plugin_name       The name of the plugin.
	 * @param   string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Debug system configuration
		//error_log('CF7-MF: Plugin Initialization');
		//error_log('Upload Max Filesize: ' . ini_get('upload_max_filesize'));
		//error_log('Post Max Size: ' . ini_get('post_max_size'));
		//error_log('Memory Limit: ' . ini_get('memory_limit'));
		
		// Check upload directory
		//$this->check_upload_dir();

		// 1. Form Setup
		add_action('wpcf7_init', array($this, 'cf7_mf_add_form_tag_file'), 10, 0);
		add_filter('wpcf7_form_enctype', array($this, 'cf7_mf_form_enctype_filter'), 10, 1);

		// 2. Validation Setup - Fixed argument count
		add_filter('wpcf7_validate_multifile', array($this, 'cf7_mf_validation_filter'), 10, 2);
		add_filter('wpcf7_validate_multifile*', array($this, 'cf7_mf_validation_filter'), 10, 2);

		// 3. Email Handling
		add_filter('wpcf7_mail_tag_replaced_file', array($this, 'cf7_mf_file_mail_tag'), 10, 4);
		add_filter('wpcf7_mail_tag_replaced_file*', array($this, 'cf7_mf_file_mail_tag'), 10, 4);
		add_filter('wpcf7_mail_components', array($this, 'cf7_mf_mail_components'), 10, 3);

		// 4. Messages and Cleanup
		add_filter('wpcf7_messages', array($this, 'cf7_mf_messages'), 10, 1);
		add_action('wpcf7_mail_sent', array($this, 'cf7_mf_cleanup_temp_files'));

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
	 * Check upload directory permissions
	 */
	public function check_upload_dir() {
		$upload_dir = wp_upload_dir();
		error_log('CF7-MF: Checking upload directory: ' . $upload_dir['path']);
		if (!is_writable($upload_dir['path'])) {
			error_log('CF7-MF: ERROR - Upload directory is not writable: ' . $upload_dir['path']);
		}
	}

	/**
	 * Validate uploaded files
	 */
	public function cf7_mf_validation_filter($result, $tag) {
		$name = $tag->name;
		error_log('CF7-MF: Starting validation for field: ' . $name);

		if (empty($_FILES[$name])) {
			error_log('CF7-MF: No files found for field: ' . $name);
			if ($tag->is_required()) {
				$result->invalidate($tag, wpcf7_get_message('invalid_required'));
			}
			return $result;
		}

		$files = $_FILES[$name];
		$file_count = is_array($files['name']) ? count(array_filter($files['name'])) : 1;
		error_log('CF7-MF: Number of files uploaded: ' . $file_count);

		// Validate file count
		$min_files = $tag->get_option('min', 'signed_int', true);
		$max_files = $tag->get_option('max', 'signed_int', true);

		if ($min_files && $file_count < intval($min_files)) {
			error_log("CF7-MF: Too few files. Required: $min_files, Got: $file_count");
			$message = str_replace('__min_file_limit__', intval($min_files), 
					  wpcf7_get_message('cf7_mb_min_file'));
			$result->invalidate($tag, $message);
			return $result;
		}

		if ($max_files && $file_count > intval($max_files)) {
			error_log("CF7-MF: Too many files. Maximum: $max_files, Got: $file_count");
			$message = str_replace('__max_file_limit__', intval($max_files), 
					  wpcf7_get_message('cf7_mb_max_file'));
			$result->invalidate($tag, $message);
			return $result;
		}

		return $result;
	}

	/**
	 * Process mail components
	 */
	public function cf7_mf_mail_components($components, $form, $mail) {
		error_log('CF7-MF: Processing mail components');
		
		try {
			$submission = WPCF7_Submission::get_instance();
			if (!$submission) {
				error_log('CF7-MF: No submission instance found');
				return $components;
			}

			$uploaded_files = $submission->uploaded_files();
			if (empty($uploaded_files)) {
				error_log('CF7-MF: No uploaded files found');
				return $components;
			}

			$attachments = isset($components['attachments']) 
						  ? $components['attachments'] : array();

			foreach ($uploaded_files as $name => $paths) {
				foreach ((array) $paths as $path) {
					if (!empty($path) && file_exists($path)) {
						error_log("CF7-MF: Adding attachment: $path");
						$attachments[] = $path;
					}
				}
			}

			if (!empty($attachments)) {
				$components['attachments'] = array_unique($attachments);
			}

		} catch (Exception $e) {
			error_log('CF7-MF: Error in mail components: ' . $e->getMessage());
		}
		
		return $components;
	}

	/**
	 * Clean up temporary files
	 */
	public function cf7_mf_cleanup_temp_files($contact_form) {
		error_log('CF7-MF: Starting cleanup');
		
		$submission = WPCF7_Submission::get_instance();
		if (!$submission) {
			error_log('CF7-MF: No submission instance for cleanup');
			return;
		}

		$uploaded_files = $submission->uploaded_files();
		if (empty($uploaded_files)) {
			error_log('CF7-MF: No files to cleanup');
			return;
		}

		foreach ($uploaded_files as $name => $paths) {
			foreach ((array) $paths as $path) {
				if (file_exists($path)) {
					if (@unlink($path)) {
						error_log("CF7-MF: Deleted file: $path");
					} else {
						error_log("CF7-MF: Failed to delete: $path");
					}
				}
			}
		}
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

		$tag = new WPCF7_FormTag( $tag );

		if ( empty( $tag->name ) ) {
			return '';
		}

		$validation_error = wpcf7_get_validation_error( $tag->name );
		$class            = wpcf7_form_controls_class( $tag->type );

		if ( $validation_error ) :
			$class .= ' wpcf7-not-valid';
		endif;

		$atts = array();

		$atts['size']        = $tag->get_size_option( '40' );
		$atts['class']       = $tag->get_class_option( $class );
		$atts['id']          = $tag->get_id_option();
		$atts['capture']     = $tag->get_option( 'capture', '(user|environment)', true );
		$atts['tabindex']    = $tag->get_option( 'tabindex', 'signed_int', true );

		$atts['accept'] = wpcf7_acceptable_filetypes(
			$tag->get_option( 'filetypes' ), 'attr'
		);

		$atts['file-limit']  = $tag->get_option( 'file-limit', 'int', true ) ?: 1024 * 1024;
		$atts['total-limit'] = $tag->get_option( 'total-limit', 'int', true ) ?: 10 * 1024 * 1024;

		$atts['min']         = $tag->get_option( 'min', 'signed_num', true );
		$atts['max']         = $tag->get_option( 'max', 'signed_num', true );

		$atts['width']       = $tag->get_option( 'w', 'int', true ) ?: 720;
		$atts['height']      = $tag->get_option( 'h', 'int', true ) ?: 480;

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

		if ( ! wpcf7_is_number( $atts['min'] ) ) {
			$atts['min'] = '0';
		}

		if ( ! wpcf7_is_number( $atts['max'] ) ) {
			$atts['max'] = '10';
		}

		$atts['type']     = 'file';
		$atts['name']     = $tag->name . '[]';
		$atts['multiple'] = 'multiple';

		$html = '';
		$html .= '<div class="cf7-mf-container">';
		$html .= '<div class="cf7-mf-content-wrapper">';

		$html .= '<div class="cf7-mf-drag-drop-zone">';
		$html .= '<span class="cf7-mf-add-icon"></span>';
		$html .= '<span class="cf7-mf-add-text">' . esc_html__( 'Drop files here or click to add.', 'cf7-mf' ) . '</span>';
		$html .= '</div>';

		// Preview area.<figure><span class="delete-icon"></span><img src="https://picsum.photos/720/480" alt=""></figure>
		$html .= '<div class="cf7-mf-preview" ></div>';
		$html .= '</div>';

		// Hidden input for submitting files.
		$html .= sprintf(
			'<span class="wpcf7-form-control-wrap" data-name="%1$s"><input %2$s />%3$s</span>',
			esc_attr( $tag->name ),
			wpcf7_format_atts( $atts ),
			$validation_error
		);

		$html .= '<div class=".cf7-mf-feedback-message"></div>
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
	 * Undocumented function
	 *
	 * @param [type] $enctype
	 * @return void
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

	/**
	 * Handle the mail components with the processed files
	 *
	 * @param [type] $components
	 * @param [type] $form
	 * @param [type] $mail
	 * @return void
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
