<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://dig.id
 * @since      1.0.0
 *
 * @package    Cf7_Mf
 * @subpackage Cf7_Mf/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Cf7_Mf
 * @subpackage Cf7_Mf/admin
 * @author     dig.id <hello@dig.id>
 */
class Cf7_Mf_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'wpcf7_admin_init', array( $this, 'cf7_mf_add_tag_generator_multifile' ), 50, 0 );
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $partial_name
	 * @param array $data
	 * @return void
	 */

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cf7-mf-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cf7-mf-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function cf7_mf_add_tag_generator_multifile() {

		$tag_generator = WPCF7_TagGenerator::get_instance();

		$tag_generator->add(
			'multifile',
			__( 'multifile', 'contact-form-7' ),
			array( $this, 'cf7_tag_generator_file' ),
			array( 'version' => '2' )
		);

	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $contact_form
	 * @param string $args
	 * @return void
	 */
	public function cf7_tag_generator_file( $contact_form, $options ) {

		$field_types = array(
			'file' => array(
				'display_name' => __( 'MultiFile uploading field', 'cf7-mf' ),
				'heading' => __( 'Multifile uploading field form-tag generator', 'cf7-mf' ),
				'description' => __( 'Generates a form-tag for a <a href="https://contactform7.com/file-uploading-and-attachment/"> multiple file uploading field</a>.', 'contact-form-7' ),
			),
		);

		$tgg = new WPCF7_TagGeneratorGenerator( $options['content'] );

		?>
		<header class="description-box">
			<h3><?php echo esc_html( $field_types['file']['heading'] ); ?></h3>

			<p><?php
				$description = wp_kses(
					$field_types['file']['description'],
					array(
						'a' => array( 'href' => true ),
						'strong' => array(),
					),
					array( 'http', 'https' )
				);

				echo $description;
			?></p>
		</header>
		<div class="control-box">
			<?php
				$tgg->print( 'field_type', array(
					'with_required' => true,
					'select_options' => array(
						'multifile' => $field_types['file']['display_name'],
					),
				) );

				$tgg->print( 'field_name' );
				$tgg->print( 'class_attr' );
				$tgg->print( 'id_attr' );
			?>

			<fieldset>
				<legend id="<?php echo esc_attr( $tgg->ref( 'filetypes-option-legend' ) ); ?>"><?php
					echo esc_html( __( 'Acceptable file types', 'cf7-mf' ) );
				?></legend>
				<label><?php
				echo sprintf(
					'<span %1$s>%2$s</span><br />',
					wpcf7_format_atts( array(
						'id' => $tgg->ref( 'filetypes-option-description' ),
					) ),
					esc_html( __( "Pipe-separated file types list. You can use file extensions and MIME types.", 'contact-form-7' ) )
				);

				echo sprintf(
					'<input %s />',
					wpcf7_format_atts(
						array(
							'type'             => 'text',
							'pattern'          => '[0-9a-z*\/\|]*',
							'value'            => 'audio/*|video/*|image/*',
							'aria-labelledby'  => $tgg->ref( 'filetypes-option-legend' ),
							'aria-describedby' => $tgg->ref( 'filetypes-option-description' ),
							'data-tag-part'    => 'option',
							'data-tag-option'  => 'filetypes:',
						),
					),
				);
				?></label>
			</fieldset>

			<?php
			$tgg->print(
				'min_max',
				array(
					'title'      => __( 'Min and Max Files', 'contact-form-7' ),
					'min_option' => 'min:',
					'max_option' => 'max:',
				)
			);
			?>

			<fieldset>
				<legend id="<?php echo esc_attr( $tgg->ref( 'limit-option-legend' ) ); ?>"><?php
					echo esc_html( __( 'File size limit', 'contact-form-7' ) );
				?></legend>
				<label><?php
				echo sprintf(
					'<span %1$s>%2$s</span><br />',
					wpcf7_format_atts( array(
						'id' => $tgg->ref( 'limit-option-description' ),
					) ),
					esc_html( __( "In bytes. You can use kb and mb suffixes.", 'contact-form-7' ) )
				);

				echo sprintf(
					'<input %s />',
					wpcf7_format_atts( array(
						'type' => 'text',
						'pattern' => '[1-9][0-9]*([kKmM]?[bB])?',
						'value' => '1mb',
						'aria-labelledby' => $tgg->ref( 'limit-option-legend' ),
						'aria-describedby' => $tgg->ref( 'limit-option-description' ),
						'data-tag-part' => 'option',
						'data-tag-option' => 'limit:',
					) )
				);
				?></label>
			</fieldset>
		</div>
		<footer class="insert-box">
			<?php
				$tgg->print( 'insert_box_content' );
				$tgg->print( 'mail_tag_tip' );
			?>
		</footer>
		<?php

	}

}
