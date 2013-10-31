<?php
/*
Plugin Name: Caldera Pages
Plugin URI: https://github.com/Desertsnowman/Caldera-Pages/
Description: Create & code custom page templates.
Version: 1.0
Author: David Cramer
Author URI: http://cramer.com/
Author Email: david@digilab.co.za
License:

  Copyright 2013 David Cramer (david@digilab.co.za)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

if ( ! defined( 'CALDERA_PAGES' ) ) {
	define( 'CALDERA_PAGES', '1.0' );
} // end if

/**
 *
 *
 * @package Caldera Pages
 * @version 0.1
 * @since  0.1
 */
class Caldera_Pages {

	/*--------------------------------------------*
	 * Attributes
	 *--------------------------------------------*/

	/** A reference to an instance of this class. **/
	private static $instance;

	/**
	 * @var      array
	 */
	protected $templates = array();

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/

	/**
	 * Returns an instance of this class. This is the Singleton design pattern.
	 *
	 * @return OBJECT  A reference to an instance of this class.
	 */
	public static function getInstance() {

		if ( null == self::$instance ) {
			self::$instance = new Caldera_Pages();
		} // end if

		return self::$instance;

	} // end getInstance

	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 *
	 * @version  1.0
	 * @since   1.0
	 */
	private function __construct() {

		add_action( 'init', array( $this, 'plugin_textdomain' ) );

		// Add a filter to the page attributes metabox to inject our template into the page template cache.
		add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'register_project_templates' ) );

		// Add a filter to the save post in order to inject out template into the page cache
		add_filter( 'wp_insert_post_data', array( $this, 'register_project_templates' ) );

		// Add a filter to the template include in order to determine if the page has our template assigned and return it's path
		add_filter( 'template_include', array( $this, 'view_project_template' ) );

		// Add admin page
		add_action( 'admin_menu', array( $this, 'admin_page' ) );

		// Add your templates to this array.
		$templates = glob( plugin_dir_path( __FILE__ ) .'templates/*.php' );
		if ( !empty( $templates ) ) {
			foreach ( $templates as $template ) {
				preg_match( '|Template Name:(.*)$|mi', file_get_contents( $template ), $header );
				if ( !empty( $header[1] ) ) {
					$title = _cleanup_header_comment( $header[1] );
				}else {
					$title = basename( $template );
				}
				$this->templates[basename( $template )] = $title;
			}
		}

		// Ajax admin
		if ( is_admin() ) {
			add_action( 'wp_ajax_load_template', array( $this, 'ajax_load_template' ) );
			add_action( 'wp_ajax_create_template', array( $this, 'ajax_create_template' ) );
			add_action( 'wp_ajax_save_template', array( $this, 'ajax_save_template' ) );
		}


	} // end constructor

	/*--------------------------------------------*
	 * Localization
	 *--------------------------------------------*/

	/**
	 * Loads the plugin text domain for translation
	 *
	 * @version  1.0
	 * @since   1.0
	 */
	public function plugin_textdomain() {
		load_plugin_textdomain( 'calderapages', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
	} // end plugin_textdomain

	/**
	 * Creates the admin pages
	 *
	 * @version  1.0
	 * @since   1.0
	 */
	public function admin_page() {
		$template_admin = add_submenu_page( 'edit.php?post_type=page', 'Caldera Pages', 'Templates', 'manage_options', 'caldera-pages', array( $this, 'render_admin_page' ) );
		add_action( 'admin_print_scripts-' . $template_admin, array( $this, 'admin_script_styles' ) );
	}

	/**
	 * Styles and Scripts for admin
	 *
	 * @version  1.0
	 * @since   1.0
	 */
	public function admin_script_styles() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'codemirror', plugin_dir_url( __FILE__ ) . 'js/codemirror-compressed.js', array( 'jquery' ), false, true );
		wp_enqueue_script( 'baldrickjs', plugin_dir_url( __FILE__ ) . 'js/jquery.baldrick.js', array( 'jquery' ), false, true );
		wp_enqueue_style( 'codemirror', plugin_dir_url( __FILE__ ) . 'css/core.css' );
	}

	/**
	 * Render the admin page
	 *
	 * @version  1.0
	 * @since   1.0
	 */
	public function render_admin_page() {
	?>
		<div id="editor-form">
			<div class="nav-bar">
				<button class="button add-new" type="button">Add New</button>
				<h2>Caldera Pages</h2>
				<form method="POST" data-action="create_template" class="hidden new-template-select">
					<label>Base Template</label>
					<?php
		$theme_templates = get_page_templates();
		echo '<select name="template-base" class="widefat new-template-field">';
		echo '<option value="">Create Blank Template</option>';
		foreach ( $theme_templates as $template => $path ) {
			echo '<option value="'.$path.'">'.$template.'</option>';
		}
		echo '</select>';

		?>
			<p>
				<label>Template Name</label>
				<input type="text" class="widefat new-template-field new-template-title" name="template-title" required>
			</p>
			<input type="hidden" value="create_template" name="action">
			<button class="button-primary widefat create-template" type="submit">Create</button>
		</form>
		<div id="templates-list-wrap">
			<ul class="template-list">
			<?php

			$templates = glob( plugin_dir_path( __FILE__ ) .'templates/*.php' );
			if ( !empty( $templates ) ) {
				foreach ( $templates as $template ) {
					preg_match( '|Template Name:(.*)$|mi', file_get_contents( $template ), $header );
					if ( !empty( $header[1] ) ) {
						$title = _cleanup_header_comment( $header[1] );
						if ( empty( $title ) ) {
							$title = basename( strtok( $template, '.' ) );
						}
					}else {
						$title = basename( $template );
					}
					echo '<li class="edit-template" data-action="load_template" data-template="'.basename( strtok( $template, '.' ) ).'"><button class="button save-button">Save</button><a href="#edit">'.$title.'</a></li>';
				}
			}
			?>
							</ul>
						</div>
					</div>
					<div class="editor-code" id="code-editor"></div>
				</div>

				<script type="text/javascript">
				var editor;

				function ini_editor(el){
					editor = CodeMirror.fromTextArea(el, {
						lineNumbers: true,
						matchBrackets: true,
						mode: "application/x-httpd-php",
						indentUnit: 4,
						indentWithTabs: true,
						enterMode: "keep",
						tabMode: "shift",
						autofocus: true
					});
					editor.refresh();
					editor.on('change', function(cm){
						jQuery('li.current').addClass('changed');
						cm.save();
					});
					editor.on('blur', function(cm){
						cm.save();
					});

				}

				function check_loaded(el){
					var template = jQuery('#edit_'+jQuery(el).data('template'));
					if(template.length){
						editor.save();
						jQuery('.CodeMirror').remove();
						jQuery('.template-list .current').removeClass('current');
						jQuery(el).addClass('current');
						ini_editor(template[0]);
						return false;
					}
				}
				function check_delete(obj){
					if(obj.value.length <= 0){
						return confirm('saving an empty template will delete it, are you sure you want to delete this template?');
					}
				}

				function load_template(obj){
					var template = jQuery('<textarea>').val(obj.data);

					template.prop('id', 'edit_'+obj.params.trigger.data('template'))
					.prop('name', 'template_code')
					.attr('data-on', true)
					.attr('data-action','save_template')
					.attr('data-refer',obj.params.trigger.data('template'));

					template.appendTo('#code-editor');
					if(typeof editor === 'object'){
						editor.save();
						jQuery('.CodeMirror').remove();
					}
					ini_editor(template[0]);
				}
				function build_edit_triggers(){
					jQuery('.edit-template').baldrick({
						request		:	ajaxurl,
						method		:	'POST',
						activeClass	:	'current',
						callback	:	'load_template',
						before		:	'check_loaded',
						complete	:	function(obj){
							jQuery('#edit_'+obj.params.trigger.data('template')).baldrick({
								request		:	ajaxurl,
								method		:	'POST',
								loadClass	:	'changed',
								loadElement	:	obj.params.trigger[0],
								target		:	obj.params.trigger.find('a'),
								before		:	check_delete,
								callback	: function(ob){
									if(ob.data.length <= 0){
										ob.params.loadElement.remove();
									}
								}
							});
						}
					});
				}

				jQuery(function($){
					$('.new-template-select').baldrick({
						request		:	ajaxurl,
						method		:	'POST',
						target		:	'#templates-list-wrap',
						complete	: function(){
							$('.new-template-field').val('');
							$('.new-template-select').slideToggle();
							build_edit_triggers();
						}
					});
					$('.add-new').on('click', function(){
						$('.new-template-field').val('');
						$('.new-template-select').slideToggle();
					});
					$('body').on('click', '.save-button', function(e){
						var template = jQuery('#edit_'+jQuery(this).parent().data('template'));
						template.trigger('click');
					})
					build_edit_triggers();
					$('body').on('keypress', '.CodeMirror', function(e){
						if (!(e.which == 115 && e.metaKey) && !(e.which == 19)) return true;
						e.preventDefault();
						var active = $('li.edit-template.current');
						$('#edit_'+active.data('template')).trigger('click');
					})
				})
				</script>
				<?php
	}

	/*--------------------------------------------*
	 * Template Registration & Usage Hooks
	 *--------------------------------------------*/

	/**
	 * Adds our template to the pages cache in order to trick wordpress
	 * in thinking its a real file.
	 *
	 * @verison 1.0
	 * @since 1.0
	 */
	public function register_project_templates( $atts ) {

		// create the key used for the themes cache
		$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

		// retrive the cache list
		$templates = wp_cache_get( $cache_key, 'themes' );

		// remove the old cache
		wp_cache_delete( $cache_key , 'themes' );

		// add our template to the templates list.
		$templates = array_merge( $templates, $this->templates );

		// add the modified cache to allow wordpress to pick it up for listing availble templates
		wp_cache_add( $cache_key, $templates, 'themes', 1800 );

		return $atts;

	} // end register_project_templates

	/**
	 * Checks if the template is assigned to the page
	 *
	 * @version 1.0
	 * since 1.0
	 */
	public function view_project_template( $template ) {

		global $post;

		if ( !isset( $this->templates[get_post_meta( $post->ID, '_wp_page_template', true )] ) )
			return $template;

		$file = plugin_dir_path( __FILE__ ) . 'templates/' . get_post_meta( $post->ID, '_wp_page_template', true );

		// Just to be safe, we check if the file exist first
		if ( file_exists( $file ) )
			return $file;

		return $template;

	} // end view_project_template

	/**
	 * Saves the template
	 *
	 * @version 1.0
	 * since 1.0
	 */
	public function ajax_save_template() {
		global $wp_filesystem;
		$url = wp_nonce_url( 'edit.php?post_type=page&page=caldera-pages', 'caldera-pages-template' );
		$creds = request_filesystem_credentials( $url, "direct", false, false, null );
		// now we have some credentials, try to get the wp_filesystem running
		if ( ! WP_Filesystem( $creds ) ) {
			request_filesystem_credentials( $url, "", false, false, null );
			return true;
		}
		$data = stripslashes_deep( $_POST );
		if ( empty( $data['template_code'] ) ) {
			$wp_filesystem->delete( plugin_dir_path( __FILE__ ) .'templates/'.$data['refer'].'.php' );
			exit;
		}else {
			$wp_filesystem->put_contents( plugin_dir_path( __FILE__ ) .'templates/'.$data['refer'].'.php', $data['template_code'], FS_CHMOD_FILE );
		}

		preg_match( '|Template Name:(.*)$|mi', $data['template_code'], $header );
		if ( !empty( $header[1] ) ) {
			$title = _cleanup_header_comment( $header[1] );
			if ( empty( $title ) ) {
				$title = basename( $data['refer'] );
			}
		}else {
			$title = basename( $data['refer'] );
		}
		echo $title;
		exit;
	} // end ajax_save_template

	/**
	 * Writes a new template
	 *
	 * @version 1.0
	 * since 1.0
	 */
	public function ajax_create_template() {
		global $wp_filesystem;
		$url = wp_nonce_url( 'edit.php?post_type=page&page=caldera-pages', 'caldera-pages-template' );
		$creds = request_filesystem_credentials( $url, "direct", false, false, null );
		// now we have some credentials, try to get the wp_filesystem running
		if ( ! WP_Filesystem( $creds ) ) {
			// our credentials were no good, ask the user for them again
			request_filesystem_credentials( $url, "", false, false, null );
			return true;
		}

		if ( empty( $_POST['template-title'] ) ) {
			$title = 'Untitled Template';
			$filename = strtolower( uniqid() ).'.php';
		}else {
			$title = $_POST['template-title'];
			$filename = sanitize_file_name( $_POST['template-title'] ).'.php';
		}

		if ( !empty( $_POST['template-base'] ) ) {
			$base = $wp_filesystem->get_contents( get_theme_root() . '/' . get_stylesheet() . '/' . $_POST['template-base'] );
			preg_match( '|Template Name:(.*)$|mi', $base, $header );
			if ( !empty( $header[1] ) ) {
				$base = str_replace( _cleanup_header_comment( $header[1] ), $title, $base ) ;
			}

		}else {
			$base = "<?php\r\n/**\r\n * Template Name: ".$title."\r\n *\r\n * Description: \r\n * \r\n * @package Caldera Pages\r\n * @since 	".CALDERA_PAGES."\r\n * @version	1.0\r\n */\r\n?>";
		}

		$wp_filesystem->put_contents( plugin_dir_path( __FILE__ ) .'templates/'.$filename, $base, FS_CHMOD_FILE );

		echo "<ul class=\"template-list\">\r\n";

		$templates = glob( plugin_dir_path( __FILE__ ) .'templates/*.php' );
		if ( !empty( $templates ) ) {
			foreach ( $templates as $template ) {
				preg_match( '|Template Name:(.*)$|mi', file_get_contents( $template ), $header );
				if ( !empty( $header[1] ) ) {
					$title = _cleanup_header_comment( $header[1] );
				}else {
					$title = basename( $template );
				}

				echo '<li class="edit-template" data-action="load_template" data-template="'.basename( strtok( $template, '.' ) ).'"><button class="button save-button">Save</button><a href="#template">'.$title.'</a></li>';
			}
		}
		echo "</ul>\r\n";

		exit;
	} // end ajax_create_template

	/**
	 * Loads a template
	 *
	 * @version 1.0
	 * since 1.0
	 */
	public function ajax_load_template() {
		global $wp_filesystem;

		$url = wp_nonce_url( 'edit.php?post_type=page&page=caldera-pages', 'caldera-pages-template' );
		$creds = request_filesystem_credentials( $url, "direct", false, false, null );
		// now we have some credentials, try to get the wp_filesystem running
		if ( ! WP_Filesystem( $creds ) ) {
			// our credentials were no good, ask the user for them again
			request_filesystem_credentials( $url, "", false, false, null );
			return true;
		}
		if ( file_exists( plugin_dir_path( __FILE__ ) .'templates/'.basename( $_POST['template'] ).'.php' ) ) {
			echo $wp_filesystem->get_contents( plugin_dir_path( __FILE__ ) .'templates/'.basename( $_POST['template'] ).'.php' );
		}else {
			echo 'file not found';
		}
		exit();
	} // end ajax_load_template

} // end class

$GLOBALS['calderapages'] = Caldera_Pages::getInstance();
