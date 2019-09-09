<?php
/*
Plugin Name: Restrict Widgets
Description: All in one solution for widget management in WordPress. Allows you to hide or display widgets on specified pages and restrict access for users.
Version: 1.3.1
Author: dFactory
Author URI: http://www.dfactory.eu/
Plugin URI: http://www.dfactory.eu/plugins/restrict-widgets/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: restrict-widgets
Domain Path: /languages

Restrict Widgets
Copyright (C) 2013-2017, Digital Factory - info@digitalfactory.pl

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

define( 'RESTRICT_WIDGETS_URL', plugins_url( '', __FILE__ ) );
define( 'RESTRICT_WIDGETS_REL_PATH', dirname( plugin_basename( __FILE__ ) ) . '/' );

final class Restrict_Widgets {

	const
		VERSION = '1.3.1', // plugin version
		ID = 'restrict-widgets';

	private static $instance;
	public $defaults = array(
		'groups'		=> false,
		'sidebar'		=> false,
		'deactivation'	=> false,
		'available'		=> array(),
		'selection'		=> array(),
		'sidebars'		=> array()
	);
	public $options;
	private $widget_options = array();
	private $pages = array();
	private $custom_post_types = array();
	private $custom_post_types_archives = array();
	private $categories = array();
	private $taxonomies = array();
	private $devices = array();
	private $bbpress = array();
	private $others = array();
	private $users = array();
	private $languages = array();
	private $hidden_widget = '';
	private $checked_widget = '';
	private $widgets = array();
	private $bbpress_active = false;
	private $polylang_active = false;
	private $wpml_active = false;

	/**
	 * Disable object clone.
	 */
	private function __clone() {}

	/**
	 * Disable unserializing of the class.
	 */
	private function __wakeup() {}

	/**
	 * Main Cookie_Notice_Pro instance,
	 * Insures that only one instance of Cookie_Notice_Pro exists in memory at one time.
	 * 
	 * @return object
	 */
	public static function instance() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'multisite_activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'multisite_deactivation' ) );

		// settings
		$this->options = array_merge( $this->defaults, get_option( 'rw_widgets_options', $this->defaults ) );

		// actions
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu_options' ) );
		add_action( 'wp_loaded', array( $this, 'polylang_widgets' ), 6 );
		add_action( 'wp_head', array( $this, 'restrict_sidebar_widgets' ), 10 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'widgets_init', array( $this, 'load_other_data' ), 10 );
		add_action( 'widgets_init', array( $this, 'init_restrict_sidebars' ), 11 );
		add_action( 'admin_init', array( $this, 'load_dynamic_data' ) );
		add_action( 'in_widget_form', array( $this, 'display_admin_widgets_options' ), 99, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'manage_widgets_menu' ) );

		// filters
		add_filter( 'widget_display_callback', array( $this, 'display_frontend_widgets' ), 10, 3 );
		add_filter( 'widget_update_callback', array( $this, 'update_admin_widgets_options' ), 10, 3 );
		add_filter( 'user_has_cap', array( $this, 'manage_widgets_cap' ), 10, 3 );
		add_filter( 'dynamic_sidebar_params', array( $this, 'restrict_sidebar_params' ), 10, 3 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_extend_links' ), 10, 2 );
		add_filter( 'plugin_action_links', array( $this, 'plugin_settings_link' ), 10, 2 );
	}

	/**
	 * Add options page.
	 */
	public function admin_menu_options() {
		add_options_page( __( 'Restrict Widgets', self::ID ), __( 'Restrict Widgets', self::ID ), 'manage_options', self::ID, array( $this, 'options_page' ) );
	}

	/**
	 * Options page callback.
	 */
	public function options_page() {
		echo '
		<div class="wrap">' . screen_icon() . '
			<h1>' . __( 'Restrict Widgets', self::ID ) . '</h1>
			<div class="restrict-widgets-settings">
				<div class="df-credits">
					<h3 class="hndle">' . __( 'Restrict Widgets', self::ID ) . ' ' . self::VERSION . '</h3>
					<div class="inside">
						<h4 class="inner">' . __( 'Need support?', self::ID ) . '</h4>
						<p class="inner">' . __( 'If you are having problems with this plugin, please talk about them in the', self::ID ) . ' <a href="https://www.dfactory.eu/support/?utm_source=' . self::ID . '-settings&utm_medium=link&utm_campaign=support" target="_blank" title="' . __( 'Support forum', self::ID ) . '">' . __( 'Support forum', self::ID ) . '</a></p>
						<hr />
						<h4 class="inner">' . __( 'Do you like this plugin?', self::ID ) . '</h4>
						<p class="inner"><a href="https://wordpress.org/support/view/plugin-reviews/' . self::ID . '" target="_blank" title="' . __( 'Rate it 5', self::ID ) . '">' . __( 'Rate it 5', self::ID ) . '</a> ' . __( 'on WordPress.org', self::ID ) . '<br />' .
		__( 'Blog about it & link to the', self::ID ) . ' <a href="http://www.dfactory.eu/plugins/restrict-widgets/?utm_source=' . self::ID . '-settings&utm_medium=link&utm_campaign=blog-about" target="_blank" title="' . __( 'plugin page', self::ID ) . '">' . __( 'plugin page', self::ID ) . '</a><br/>' .
		__( 'Check out our other', self::ID ) . ' <a href="http://www.dfactory.eu/plugins/?utm_source=' . self::ID . '-settings&utm_medium=link&utm_campaign=other-plugins" target="_blank" title="' . __( 'WordPress plugins', self::ID ) . '">' . __( 'WordPress plugins', self::ID ) . '</a>
						</p>
						<hr />
						<p class="df-link inner">' . __( 'Created by', self::ID ) . ' <a href="http://www.dfactory.eu/?utm_source=' . self::ID . '-settings&utm_medium=link&utm_campaign=created-by" target="_blank" title="dFactory - Quality plugins for WordPress"><img src="' . RESTRICT_WIDGETS_URL . '/images/logo-dfactory.png' . '" title="dFactory - Quality plugins for WordPress" alt="dFactory - Quality plugins for WordPress" /></a></p>
					</div>
				</div>
				<form action="options.php" method="post">';

		settings_fields( 'rw_widgets_options' );
		do_settings_sections( 'rw_widgets_options' );

		echo '
					<p class="submit">';

		submit_button( '', 'primary', 'save_rw_options', false );

		echo ' ';

		submit_button( __( 'Reset to defaults', self::ID ), 'secondary', 'reset_rw_options', false );

		echo '
					</p>
				</form>
			</div>
			<div class="clear"></div>
		</div>';
	}

	/**
	 * Register settings callback.
	 */
	public function register_settings() {
		register_setting( 'rw_widgets_options', 'rw_widgets_options', array( $this, 'validate_settings' ) );
		add_settings_section( 'rw_widgets_options_general', '', array( $this, 'restrict_widgets_section' ), 'rw_widgets_options' );
		add_settings_field( 'rw_restrict_roles', __( 'Restrict User Roles', self::ID ), array( $this, 'restrict_roles_field' ), 'rw_widgets_options', 'rw_widgets_options_general' );
		add_settings_field( 'rw_restrict_sidebars', __( 'Restrict Sidebars', self::ID ), array( $this, 'restrict_sidebars_field' ), 'rw_widgets_options', 'rw_widgets_options_general' );
		add_settings_field( 'rw_restrict_widgets', __( 'Restrict Widgets', self::ID ), array( $this, 'restrict_widgets_field' ), 'rw_widgets_options', 'rw_widgets_options_general' );
		add_settings_field( 'rw_restrict_options', __( 'Restrict Widget Options', self::ID ), array( $this, 'restrict_options_field' ), 'rw_widgets_options', 'rw_widgets_options_general' );
		add_settings_field( 'rw_option_groups', __( 'Option Groups', self::ID ), array( $this, 'option_groups_field' ), 'rw_widgets_options', 'rw_widgets_options_general' );
		add_settings_field( 'rw_modify_is_sidebar', __( 'is_sidebar_active()', self::ID ), array( $this, 'modify_is_sidebar_field' ), 'rw_widgets_options', 'rw_widgets_options_general' );
		add_settings_field( 'rw_delete_deactivation', __( 'Deactivation', self::ID ), array( $this, 'delete_deactivation_field' ), 'rw_widgets_options', 'rw_widgets_options_general' );

	}
		
	/**
	 * Restrict widgets section.
	 */
	public function restrict_widgets_section() {
		echo '
		<p>' . __( 'Use this settings to manage access to widgets page and to restrict availability of widgets, sidebars and widget options to site administrators only.', self::ID ) . '</p>';
	}
	
	/**
	 * Restrict user roles option.
	 */
	public function restrict_roles_field() {
		global $wp_roles;

		echo '
		<div id="rw_restrict_roles">';

		foreach ( $wp_roles->roles as $role_name => $role_array ) {
			echo '
			<label class="cb-checkbox"><input id="rw_restrict_roles-' . $role_name . '" type="checkbox" name="options-widgets-roles[' . esc_attr( $role_name ) . ']" value="1" ' . checked( (in_array( 'manage_widgets', array_keys( $role_array['capabilities'] ), true ) ? true : false ), true, false ) . ' ' . disabled( ($role_name === 'administrator' ? true : false ), true, false ) . '/>' . $role_array['name'] . '</label>';
		}

		echo '
			<p class="description">' . esc_html__( 'Restrict access to widgets page to specific user roles.', self::ID ) . '</p>
		</div>';
	}
	
	/**
	 * Restrict sidebars option.
	 */
	public function restrict_sidebars_field() {
		global $wp_registered_sidebars;

		echo '
		<div id="rw_restrict_sidebars">
			<select name="options-widgets-sidebars[]" id="options-widgets-sidebars" multiple="multiple" class="select2">';

			foreach ( $wp_registered_sidebars as $sidebar ) {
				if ( $sidebar['id'] !== 'wp_inactive_widgets' ) {
					if ( ! isset( $this->options['sidebars'][$sidebar['id']] ) )
						$this->options['sidebars'][$sidebar['id']] = false;

					echo '<option value="' . $sidebar['id'] . '" ' . selected( $this->options['sidebars'][$sidebar['id']], true, false ) . '>' . $sidebar['name'] . '</option>';
				}
			}

			echo '
			</select>
			<p class="description">' . esc_html__( 'Select which sidebars will be restricted to admins only.', self::ID ) . '</p>
		</div>';
	}
	
	/**
	 * Restricu widgets option.
	 */
	public function restrict_widgets_field() {
		global $wp_registered_widgets;

		$widgets_unique = array();

		// we need to make a copy for sorting
		$widgets = $wp_registered_widgets;
		usort( $widgets, array( $this, 'sort_widgets_by_name' ) );

		// we need to make unique array to avoid duplicated instances of widgets later
		foreach ( $widgets as $widget ) {
			if ( isset( $widget['callback'][0] ) && is_object( $widget['callback'][0] ) )
				$widgets_unique[get_class( $widget['callback'][0] )] = $widget['name'];
			else
				$widgets_unique[$widget['id']] = $widget['name'];
		}
			
		echo '
		<div id="rw_restrict_widgets">
			<select name="options-available-widgets[]" id="options-available-widgets" multiple="multiple" class="select2">';

			foreach ( array_unique( $widgets_unique ) as $widget_class => $widget_name ) {
				$this->options['available'][$widget_class] = (bool) isset( $this->options['available'][$widget_class] );

				echo '<option value="' . $widget_class . '" ' . selected( $this->options['available'][$widget_class], true, false ) . '>' . $widget_name . '</option>';
			}

			echo '
			</select>
			<p class="description">' . esc_html__( 'Select which widgets will be restricted to admins only.', self::ID ) . '</p>
		</div>';
	}
	
	/**
	 * Restricu widgets option.
	 */
	public function restrict_options_field() {
		echo '
		<div id="rw_restrict_options">
			<select name="options-widgets-selection[]" id="options-widgets-selection" multiple="multiple" class="select2">';

			foreach ( $this->widget_options as $group_name => $value ) {
				echo $this->get_selection_group( $group_name, 'option', '', '', $this->options );
			}

			echo '
			</select>
			<p class="description">' . esc_html__( 'Select which widget options will be restricted to admins only.', self::ID ) . '</p>
		</div>';
	}
	
	/**
	 * Option groups option.
	 */
	public function option_groups_field() {
		echo '
		<div id="rw_option_groups">
			<label for="options-widgets-groups" class="cb-checkbox"><input type="checkbox" name="options-widgets-groups" id="options-widgets-groups" value="1" ' . checked( $this->options['groups'], true, false ) . ' />' . __( 'Display widget options in groups', self::ID ) . '</label>';
		
		echo '
		</div>';
	}
	
	/**
	 * Modify is_sidebar_active() option.
	 */
	public function modify_is_sidebar_field() {
		echo '
		<div id="rw_modify_is_sidebar">
			<label for="options-widgets-sidebar" class="cb-checkbox"><input type="checkbox" name="options-widgets-sidebar" id="options-widgets-sidebar" value="1" ' . checked( $this->options['sidebar'], true, false ) . ' />' . __( 'Modify is_sidebar_active() function', self::ID ) . '</label>';
		
		echo '
			<p class="description">' . __( 'By default is_active_sidebar() function returns true even if no widget is displayed in a sidebar. Check this if you want is_active_sidebar() to recognize Restrict Widgets display settings.', self::ID ) . '</p>
		</div>';
	}
	
	/**
	 * Delete on deactivation option.
	 */
	public function delete_deactivation_field() {
		echo '
		<div id="rw_delete_deactivation">
			<label for="options-widgets-deactivation" class="cb-checkbox"><input type="checkbox" name="options-widgets-deactivation" id="options-widgets-deactivation" value="1" ' . checked( $this->options['deactivation'], true, false ) . ' />' . __( 'Remove all plugin data on deactivation', self::ID ) . '</label>';
		
		echo '
		</div>';
	}

	/**
	 * Save restrict widgets options.
	 * 
	 * @global array $wp_roles
	 * @param array $input
	 * @return array
	 */
	public function validate_settings( $input ) {
		if ( ! check_admin_referer( 'rw_widgets_options-options') )
			return $input;

		if ( ! current_user_can( 'manage_options' ) )
			return $input;

		if ( isset( $_POST['save_rw_options'] ) ) {
			global $wp_roles;

			// display groups?
			$input['groups'] = isset( $_POST['options-widgets-groups'] );

			// modify is_active_sidebar?
			$input['sidebar'] = isset( $_POST['options-widgets-sidebar'] );

			// remove plugin data?
			$input['deactivation'] = isset( $_POST['options-widgets-deactivation'] );

			// any available widgets?
			if ( isset( $_POST['options-available-widgets'] ) ) {
				foreach ( $_POST['options-available-widgets'] as $widget_class ) {
					$input['available'][$widget_class] = true;
				}
			} else
				$input['available'] = array();

			// any specific elements?
			if ( isset( $_POST['options-widgets-selection'] ) ) {
				$selected = $_POST['options-widgets-selection'];

				// only for custom post types (archives)
				foreach ( $selected as $element ) {
					$tmp = explode( '_', $element, 2 );

					if ( in_array( $tmp[0], array( 'cpt', 'cpta' ), true ) )
						$input['selection'][$tmp[0] === 'cpt' ? 'custom_post_types' : 'custom_post_types_archives'][$tmp[0] . '_' . sanitize_key( $tmp[1] )] = true;
				}

				foreach ( $this->pages as $page ) {
					if ( in_array( 'pageid_' . $page->ID, $selected, true ) )
						$input['selection']['pages']['pageid_' . $page->ID] = true;
				}

				foreach ( $this->custom_post_types as $cpt ) {
					if ( in_array( 'cpt_' . $cpt->name, $selected, true ) )
						$input['selection']['custom_post_types']['cpt_' . $cpt->name] = true;
				}

				foreach ( $this->categories as $category ) {
					if ( in_array( 'category_' . $category->cat_ID, $selected, true ) )
						$input['selection']['categories']['category_' . $category->cat_ID] = true;
				}

				foreach ( $this->taxonomies as $taxonomy ) {
					if ( in_array( 'taxonomy_' . $taxonomy->name, $selected, true ) )
						$input['selection']['taxonomies']['taxonomy_' . $taxonomy->name] = true;
				}

				foreach ( $this->others as $key => $value ) {
					if ( in_array( 'others_' . $key, $selected, true ) )
						$input['selection']['others']['others_' . $key] = true;
				}

				foreach ( $this->users as $key => $value ) {
					if ( in_array( 'users_' . $key, $selected, true ) )
						$input['selection']['users']['users_' . $key] = true;
				}

				foreach ( $this->devices as $key => $value ) {
					if ( in_array( 'devices_' . $key, $selected, true ) )
						$input['selection']['devices']['devices_' . $key] = true;
				}

				if ( $this->bbpress_active ) {
					foreach ( $this->bbpress as $key => $value ) {
						if ( in_array( 'bbpress_' . $key, $selected, true ) )
							$input['selection']['bbpress']['bbpress_' . $key] = true;
					}
				}

				if ( ! empty ( $this->languages ) && ( $this->polylang_active || $this->wpml_active ) ) {
					foreach ( $this->languages as $key => $value ) {
						if ( in_array( 'language_' . $key, $selected, true ) )
							$input['selection']['languages']['language_' . $key] = true;
					}
				}
			} else
				$input['selection'] = array();

			// any sidebars?
			if ( isset( $_POST['options-widgets-sidebars'] ) ) {
				foreach ( $_POST['options-widgets-sidebars'] as $sidebar ) {
					$input['sidebars'][$sidebar] = true;
				}
			} else
				$input['sidebars'] = array();

			// any user roles?
			$roles = isset( $_POST['options-widgets-roles'] ) ? array_keys( $_POST['options-widgets-roles'] ) : array();

			foreach ( $wp_roles->roles as $role_name => $role_array ) {
				if ( $role_name !== 'administrator' ) {
					$role = get_role( $role_name );

					if ( in_array( $role_name, $roles ) )
						$role->add_cap( 'manage_widgets' );
					else
						$role->remove_cap( 'manage_widgets' );
				}
			}
		} elseif ( isset( $_POST['reset_rw_options'] ) ) {
			global $wp_roles;

			$input = $this->defaults;

			// remove roles
			foreach ( $wp_roles->roles as $role_name => $role_array ) {
				if ( $role_name !== 'administrator' ) {
					$role = get_role( $role_name );
					$role->remove_cap( 'manage_widgets' );
				}
			}

			add_settings_error( 'rw_widgets_options', 'settings_reset', __( 'Settings restored to defaults.', self::ID ), 'updated' );
		}
		
		return $input;
	}

	/**
	 * Multisite activation.
	 */
	public function multisite_activation( $networkwide ) {
		if ( is_multisite() && $networkwide ) {
			global $wpdb;

			$activated_blogs = array();
			$current_blog_id = get_current_blog_id();
			$blogs_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT blog_id FROM ' . $wpdb->blogs, '' ) );

			foreach ( $blogs_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				$this->activate_single();
				$activated_blogs[] = (int) $blog_id;
			}

			switch_to_blog( $current_blog_id );
			update_site_option( 'restrict_widgets_activated_blogs', $activated_blogs, array() );
		} else
			$this->activate_single();
	}

	/**
	 * Activation.
	 */
	public function activate_single() {
		$role = get_role( 'administrator' );
		$role->add_cap( 'manage_widgets' );

		// default settings
		add_option( 'rw_widgets_options', $this->defaults, '', 'no' );
	}

	/**
	 * Multisite deactivation.
	 */
	public function multisite_deactivation( $networkwide ) {
		if ( is_multisite() && $networkwide ) {
			global $wpdb;

			$current_blog_id = get_current_blog_id();
			$blogs_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT blog_id FROM ' . $wpdb->blogs, '' ) );

			if ( ($activated_blogs = get_site_option( 'restrict_widgets_activated_blogs', false, false )) === false )
				$activated_blogs = array();

			foreach ( $blogs_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				$this->deactivate_single();

				if ( in_array( (int) $blog_id, $activated_blogs, true ) )
					unset( $activated_blogs[array_search( $blog_id, $activated_blogs )] );
			}

			switch_to_blog( $current_blog_id );
			update_site_option( 'restrict_widgets_activated_blogs', $activated_blogs );
		} else
			$this->deactivate_single();
	}

	/**
	 * Plugin deactivation function.
	 */
	public function deactivate_single() {
		if ( $this->options['deactivation'] === true ) {
			global $wp_roles, $wp_registered_widgets;

			// remove roles
			foreach ( $wp_roles->roles as $role_name => $tmp ) {
				$role = get_role( $role_name );
				$role->remove_cap( 'manage_widgets' );
			}

			// clear all widgets
			foreach ( $wp_registered_widgets as $widget ) {
				if ( isset( $widget['params'][0]['number'] ) && $widget['params'][0]['number'] !== -1 ) {
					$option = get_option( $widget['callback'][0]->option_name );

					unset( $option[$widget['params'][0]['number']]['rw_opt'] );

					update_option( $widget['callback'][0]->option_name, $option );
				}
			}

			delete_option( 'rw_widgets_options' );
		}
	}

	/**
	 * Fix for is_active_sidebar() function (all hidden widgets on sidebar = false).
	 */
	public function restrict_sidebar_widgets() {
		if ( ! is_admin() ) {
			if ( $this->options['sidebar'] ) {
				global $wp_registered_widgets, $_wp_sidebars_widgets;

				$widgets_c = $instance = array();

				foreach ( $wp_registered_widgets as $widget ) {
					if ( isset( $widget['callback'][0]->option_name ) && $widget['callback'][0]->option_name !== '' && empty( $widgets_classes[$widget['callback'][0]->option_name] ) )
						$widgets_c[$widget['callback'][0]->option_name] = get_option( $widget['callback'][0]->option_name );
				}

				foreach ( $widgets_c as $widget_base_id => $widgets ) {
					if ( is_array( $widgets ) ) {
						foreach ( $widgets as $widget_id => $widget ) {
							if ( is_int( $widget_id ) )
								$instance[$widget_base_id . '-' . $widget_id] = $widget;
						}
					}
				}

				if ( ! empty( $instance ) ) {
					foreach ( $instance as $widget_id => $widget ) {
						$this->widgets[substr( $widget_id, 7 )] = $this->restrict_widget( $widget, false, array( 'widget_id' => substr( $widget_id, 7 ) ) );
					}
				}

				if ( ! empty( $this->widgets ) ) {
					$widgets_c = $_wp_sidebars_widgets;

					if ( ! empty( $widgets_c ) ) {
						foreach ( $widgets_c as $sidebar => $s_widgets ) {
							if ( ! empty( $s_widgets ) && is_array( $s_widgets ) ) {
								foreach ( $s_widgets as $widget ) {
									if ( $this->widgets[$widget] === false && ($widget_key = array_search( $widget, $_wp_sidebars_widgets[$sidebar] )) !== false )
										unset( $_wp_sidebars_widgets[$sidebar][$widget_key] );
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Fix for Polylang - remove language switcher from widgets.
	 */
	public function polylang_widgets() {
		if ( $this->polylang_active ) {
			global $polylang;

			if ( has_action( 'in_widget_form', array( $polylang, 'in_widget_form' ) ) )
				remove_action( 'in_widget_form', array( $polylang, 'in_widget_form' ) );
		}
	}

	/**
	 * Load textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( self::ID, false, RESTRICT_WIDGETS_REL_PATH . 'languages/' );

		if ( class_exists( 'bbPress' ) )
			$this->bbpress_active = true;

		if ( class_exists( 'Polylang' ) )
			$this->polylang_active = true;

		if ( class_exists( 'SitePress' ) )
			$this->wpml_active = true;
	}

	/**
	 * Add links to Support Forum.
	 */
	public function plugin_extend_links( $links, $file ) {
		if ( ! current_user_can( 'install_plugins' ) )
			return $links;

		$plugin = plugin_basename( __FILE__ );

		if ( $file == $plugin ) {
			return array_merge(
				$links, array( sprintf( '<a href="http://www.dfactory.eu/support/forum/restrict-widgets/" target="_blank">%s</a>', __( 'Support', self::ID ) ) )
			);
		}

		return $links;
	}

	/**
	 * Add links to Settings page.
	 */
	function plugin_settings_link( $links, $file ) {
		if ( ! is_admin() || ! current_user_can( 'edit_theme_options' ) )
			return $links;

		static $plugin;

		$plugin = plugin_basename( __FILE__ );

		if ( $file == $plugin ) {
			$settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'widgets.php' ), __( 'Widgets', self::ID ) );
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	/**
	 * Hide widgets for users without admin privileges.
	 */
	public function restrict_sidebar_params( $params ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			global $wp_registered_widgets;

			if (
				// standard based widget class
				(isset( $wp_registered_widgets[$params[0]['widget_id']]['callback'][0] ) && is_object( $wp_registered_widgets[$params[0]['widget_id']]['callback'][0] ) && in_array( get_class( $wp_registered_widgets[$params[0]['widget_id']]['callback'][0] ), array_keys( $this->options['available'] ) )) ||
				// non-standard based widget
				(isset( $wp_registered_widgets[$params[0]['widget_id']]['id'] ) && in_array( $wp_registered_widgets[$params[0]['widget_id']]['id'], array_keys( $this->options['available'] ) ))
			)
				$params[0]['_hide'] = 1;
		}

		return $params;
	}

	/**
	 * Load dynamic data.
	 */
	public function load_dynamic_data() {
		$this->taxonomies = get_taxonomies(
			array(
				'public' => true
			),
			'objects',
			'and'
		);

		if ( $this->polylang_active && isset( $this->taxonomies['language'] ) )
			unset( $this->taxonomies['language'] );

		$this->categories = get_categories(
			array(
				'hide_empty' => false
			)
		);

		$this->custom_post_types = get_post_types(
			array(
				'public' => true
			),
			'objects',
			'and'
		);

		$this->custom_post_types_archives = get_post_types(
			array(
				'public'		 => true,
				'has_archive'	 => true
			),
			'objects',
			'and'
		);
	}

	/**
	 * Load other data (dynamic data here too like get_pages() due to some WP restrictions) and languages.
	 */
	public function load_other_data() {
		$this->widget_options = array(
			'pages'						 => __( 'Pages', self::ID ),
			'custom_post_types'			 => __( 'Custom Post Types', self::ID ),
			'custom_post_types_archives' => __( 'Custom Post Type Archives', self::ID ),
			'categories'				 => __( 'Categories', self::ID ),
			'taxonomies'				 => __( 'Taxonomies', self::ID ),
			'others'					 => __( 'Others', self::ID )
		);

		// bbPress support
		if ( $this->bbpress_active === true ) {
			$this->bbpress = array(
				'search'		 => __( 'Search', self::ID ),
				'single_user'	 => __( 'Single User', self::ID ),
				'topic_tag'		 => __( 'Topic Tag', self::ID )
			);

			$this->widget_options['bbpress'] = __( 'bbPress', self::ID );
		}

		$this->widget_options['devices'] = __( 'Devices', self::ID );
		$this->widget_options['users'] = __( 'Users', self::ID );
		$this->widget_options['languages'] = __( 'Languages', self::ID );

		$this->others = array(
			'front_page'	 => __( 'Front Page', self::ID ),
			'blog_page'		 => __( 'Blog Page', self::ID ),
			'single_post'	 => __( 'Single Posts', self::ID ),
			'sticky_post'	 => __( 'Sticky Posts', self::ID ),
			'author_archive' => __( 'Author Archive', self::ID ),
			'date_archive'	 => __( 'Date Archive', self::ID ),
			'404_page'		 => __( '404 Page', self::ID ),
			'search_page'	 => __( 'Search Page', self::ID )
		);

		$this->users = array(
			'logged_in'	 => __( 'Logged in users', self::ID ),
			'logged_out' => __( 'Logged out users', self::ID )
		);

		$this->devices = array(
			'mobile'	 => __( 'Mobile', self::ID ),
			'desktop'	 => __( 'Desktop, Laptop, etc.', self::ID )
		);

		$this->pages = get_pages(
			array(
				'sort_order'	 => 'ASC',
				'sort_column'	 => 'post_title',
				'number'		 => '',
				'post_type'		 => 'page',
				'post_status'	 => 'publish'
			)
		);

		// Polylang support
		if ( $this->polylang_active ) {
			$languages = get_terms( 'language', array( 'hide_empty' => false ) );

			// we need to make WMPL style table
			foreach ( $languages as $language ) {
				$this->languages[$language->slug] = array( 'native_name' => $language->name );
			}
		}
		// WMPL support
		elseif ( $this->wpml_active )
			$this->languages = icl_get_languages( 'skip_missing=0&orderby=native_name&order=asc' );
		else
			$this->languages = array();
	}

	/**
	 * Remove selected sidebars for users without edit_theme_options capability.
	 */
	public function init_restrict_sidebars() {
		if ( ! current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' ) ) {
			foreach ( array_keys( $this->options['sidebars'] ) as $sidebar_id ) {
				unregister_sidebar( $sidebar_id );
			}
		}
	}

	/**
	 * Sort widgets by name.
	 */
	private function sort_widgets_by_name( $element_a, $element_b ) {
		return strnatcasecmp( $element_a['name'], $element_b['name'] );
	}

	/**
	 * Load scripts and styles.
	 */
	public function admin_enqueue_scripts( $page ) {
		if ( $page === 'widgets.php' ) {
			$js_widgets = $js_class = $js_nonclass = array();
			$orphan_sidebar = 0;

			// only for users without admin privileges
			if ( ! current_user_can( 'manage_options' ) ) {
				global $wp_registered_widgets;

				$restrict = array_keys( $this->options['available'] );
				$widgets = array();
				$orphan_sidebar = 1;

				foreach ( wp_get_sidebars_widgets() as $sidebar ) {
					foreach ( $sidebar as $widget ) {
						$widgets[] = $widget;
					}
				}

				// which sidebars to hide
				foreach ( $widgets as $widget ) {
					if (
					// standard based widget class
						(isset( $wp_registered_widgets[$widget]['callback'][0] ) && is_object( $wp_registered_widgets[$widget]['callback'][0] ) && in_array( get_class( $wp_registered_widgets[$widget]['callback'][0] ), $restrict )) ||
						// non-standard based widget
						(isset( $wp_registered_widgets[$widget]['id'] ) && in_array( $wp_registered_widgets[$widget]['id'], $restrict ))
					)
						$js_widgets[] = $widget;
				}

				// which widgets to hide
				foreach ( $wp_registered_widgets as $widget ) {
					// standard based widget class
					if ( isset( $widget['callback'][0] ) && is_object( $widget['callback'][0] ) && in_array( get_class( $widget['callback'][0] ), $restrict ) )
						$js_class[] = $widget['callback'][0]->id_base;
					// non-standard based widget
					elseif ( in_array( $widget['id'], $restrict ) )
						$js_nonclass[] = $widget['id'];
				}
			}

			wp_register_script( 'restrict-widgets-select2', RESTRICT_WIDGETS_URL . '/assets/select2.min.js', array( 'jquery' ) );

			wp_enqueue_script( self::ID, RESTRICT_WIDGETS_URL . '/js/widgets.js', array( 'jquery', 'restrict-widgets-select2' ) );

			wp_localize_script(
				self::ID,
				'rwArgs',
				array(
					'placeholder_text'			 => esc_attr__( 'Select options', self::ID ),
					'restrict_available_widgets' => esc_attr__( 'Select widgets', self::ID ),
					'restrict_widgets_selection' => esc_attr__( 'Select widgets options', self::ID ),
					'restrict_sidebars'			 => esc_attr__( 'Select sidebars', self::ID ),
					'restrict_roles'			 => esc_attr__( 'Select roles', self::ID ),
					'restrict_languages'		 => esc_attr__( 'Select languages', self::ID ),
					'restrict_widgets'			 => $js_widgets,
					'restrict_class'			 => array_unique( $js_class ),
					'restrict_nonclass'			 => array_unique( $js_nonclass ),
					'restrict_orphan_sidebar'	 => $orphan_sidebar
				)
			);

			wp_enqueue_style(
				'restrict-widgets-select2', RESTRICT_WIDGETS_URL . '/assets/select2.min.css'
			);

			wp_enqueue_style(
				'restrict-widgets-admin', RESTRICT_WIDGETS_URL . '/css/admin.css'
			);
		} elseif ( $page === 'settings_page_restrict-widgets' ) {
			wp_register_script( 'restrict-widgets-select2', RESTRICT_WIDGETS_URL . '/assets/select2.min.js', array( 'jquery' ) );
			wp_enqueue_script( 'restrict-widgets-admin', RESTRICT_WIDGETS_URL . '/js/admin.js', array( 'jquery', 'restrict-widgets-select2' ) );

			wp_localize_script(
				'restrict-widgets-admin',
				'rwArgs',
				array(
					'resetToDefaults' => __( 'Are you sure you want to reset these settings to defaults?', self::ID )
				)
			);

			wp_enqueue_style( 'restrict-widgets-select2', RESTRICT_WIDGETS_URL . '/assets/select2.min.css' );
			wp_enqueue_style( 'restrict-widgets-admin', RESTRICT_WIDGETS_URL . '/css/admin.css' );
		}
	}

	/**
	 * Display groups of data (pages, custom post types, categories, taxonomiex, ...) for options and widget display.
	 */
	private function get_selection_group( $group_name, $type, $widget = '', $instance = '', $option = '' ) {
		$html = '';

		switch ( $group_name ) {
			case 'pages': {
					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['pages'] . '">';

					foreach ( $this->pages as $page ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['pages']['pageid_' . $page->ID] ) === false )
										$option['selection']['pages']['pageid_' . $page->ID] = false;

									$html .= '<option value="pageid_' . $page->ID . '" ' . selected( $option['selection']['pages']['pageid_' . $page->ID], true, false ) . '>' . $page->post_title . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['pages']['pageid_' . $page->ID] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['pageid_' . $page->ID] ) === false )
											$instance['rw_opt']['pageid_' . $page->ID] = 0;

										$html .= '<option value="pageid_' . $page->ID . '" ' . selected( $instance['rw_opt']['pageid_' . $page->ID], true, false ) . '>' . apply_filters( 'rw_option_display_name', $page->post_title, 'page' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
			case 'custom_post_types': {
					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['custom_post_types'] . '">';

					foreach ( $this->custom_post_types as $cpt ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['custom_post_types']['cpt_' . $cpt->name] ) === false )
										$option['selection']['custom_post_types']['cpt_' . $cpt->name] = false;

									$html .= '<option value="cpt_' . $cpt->name . '" ' . selected( $option['selection']['custom_post_types']['cpt_' . $cpt->name], true, false ) . '>' . sprintf( __( 'Single %s', self::ID ), $cpt->label ) . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['custom_post_types']['cpt_' . $cpt->name] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['cpt_' . $cpt->name] ) === false )
											$instance['rw_opt']['cpt_' . $cpt->name] = 0;

										$html .= '<option value="cpt_' . $cpt->name . '" ' . selected( $instance['rw_opt']['cpt_' . $cpt->name], true, false ) . '>' . apply_filters( 'rw_option_display_name', sprintf( __( 'Single %s', self::ID ), $cpt->label ), 'custom_post_type' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
			case 'custom_post_types_archives': {
					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['custom_post_types_archives'] . '">';

					foreach ( $this->custom_post_types_archives as $cpta ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['custom_post_types_archives']['cpta_' . $cpta->name] ) === false )
										$option['selection']['custom_post_types_archives']['cpta_' . $cpta->name] = false;

									$html .= '<option value="cpta_' . $cpta->name . '" ' . selected( $option['selection']['custom_post_types_archives']['cpta_' . $cpta->name], true, false ) . '>' . sprintf( __( '%s Archive', self::ID ), $cpta->label ) . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['custom_post_types_archives']['cpta_' . $cpta->name] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['cpta_' . $cpta->name] ) === false )
											$instance['rw_opt']['cpta_' . $cpta->name] = 0;

										$html .= '<option value="cpta_' . $cpta->name . '" ' . selected( $instance['rw_opt']['cpta_' . $cpta->name], true, false ) . '>' . apply_filters( 'rw_option_display_name', sprintf( __( '%s Archive', self::ID ), $cpta->label ), 'custom_post_type_archive' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
			case 'categories': {
					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['categories'] . '">';

					foreach ( $this->categories as $category ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['categories']['category_' . $category->cat_ID] ) === false )
										$option['selection']['categories']['category_' . $category->cat_ID] = false;

									$html .= '<option value="category_' . $category->cat_ID . '" ' . selected( $option['selection']['categories']['category_' . $category->cat_ID], true, false ) . '>' . $category->cat_name . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['categories']['category_' . $category->cat_ID] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['category_' . $category->cat_ID] ) === false )
											$instance['rw_opt']['category_' . $category->cat_ID] = 0;

										$html .= '<option value="category_' . $category->cat_ID . '" ' . selected( $instance['rw_opt']['category_' . $category->cat_ID], true, false ) . '>' . apply_filters( 'rw_option_display_name', $category->cat_name, 'category' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
			case 'taxonomies': {
					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['taxonomies'] . '">';

					foreach ( $this->taxonomies as $taxonomy ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['taxonomies']['taxonomy_' . $taxonomy->name] ) === false )
										$option['selection']['taxonomies']['taxonomy_' . $taxonomy->name] = false;

									$html .= '<option value="taxonomy_' . $taxonomy->name . '" ' . selected( $option['selection']['taxonomies']['taxonomy_' . $taxonomy->name], true, false ) . '>' . $taxonomy->label . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['taxonomies']['taxonomy_' . $taxonomy->name] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['taxonomy_' . $taxonomy->name] ) === false )
											$instance['rw_opt']['taxonomy_' . $taxonomy->name] = 0;

										$html .= '<option value="taxonomy_' . $taxonomy->name . '" ' . selected( $instance['rw_opt']['taxonomy_' . $taxonomy->name], true, false ) . '>' . apply_filters( 'rw_option_display_name', $taxonomy->label, 'taxonomy' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
			case 'others': {
					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['others'] . '">';

					foreach ( $this->others as $key => $value ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['others']['others_' . $key] ) === false )
										$option['selection']['others']['others_' . $key] = false;

									$html .= '<option value="others_' . $key . '" ' . selected( $option['selection']['others']['others_' . $key], true, false ) . '>' . $value . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['others']['others_' . $key] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['others_' . $key] ) === false )
											$instance['rw_opt']['others_' . $key] = 0;

										$html .= '<option value="others_' . $key . '" ' . selected( $instance['rw_opt']['others_' . $key], true, false ) . '>' . apply_filters( 'rw_option_display_name', $value, 'other' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
			case 'devices': {
					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['devices'] . '">';

					foreach ( $this->devices as $key => $value ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['devices']['devices_' . $key] ) === false )
										$option['selection']['devices']['devices_' . $key] = false;

									$html .= '<option value="devices_' . $key . '" ' . selected( $option['selection']['devices']['devices_' . $key], true, false ) . '>' . $value . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['devices']['devices_' . $key] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['devices_' . $key] ) === false )
											$instance['rw_opt']['devices_' . $key] = 0;

										$html .= '<option value="devices_' . $key . '" ' . selected( $instance['rw_opt']['devices_' . $key], true, false ) . '>' . apply_filters( 'rw_option_display_name', $value, 'device' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
			case 'bbpress': {
					if ( $this->bbpress_active === false )
						return $html;

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['bbpress'] . '">';

					foreach ( $this->bbpress as $key => $value ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['bbpress']['bbpress_' . $key] ) === false )
										$option['selection']['bbpress']['bbpress_' . $key] = false;

									$html .= '<option value="bbpress_' . $key . '" ' . selected( $option['selection']['bbpress']['bbpress_' . $key], true, false ) . '>' . $value . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['bbpress']['bbpress_' . $key] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['bbpress_' . $key] ) === false )
											$instance['rw_opt']['bbpress_' . $key] = 0;

										$html .= '<option value="bbpress_' . $key . '" ' . selected( $instance['rw_opt']['bbpress_' . $key], true, false ) . '>' . apply_filters( 'rw_option_display_name', $value, 'bbpress' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
			case 'users': {
					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['users'] . '">';

					foreach ( $this->users as $key => $value ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['users']['users_' . $key] ) === false )
										$option['selection']['users']['users_' . $key] = false;

									$html .= '<option value="users_' . $key . '" ' . selected( $option['selection']['users']['users_' . $key], true, false ) . '>' . $value . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['users']['users_' . $key] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['users_' . $key] ) === false )
											$instance['rw_opt']['users_' . $key] = 0;

										$html .= '<option value="users_' . $key . '" ' . selected( $instance['rw_opt']['users_' . $key], true, false ) . '>' . apply_filters( 'rw_option_display_name', $value, 'user' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
			case 'languages': {
					if ( empty( $this->languages ) )
						return $html;

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '<optgroup label="' . $this->widget_options['languages'] . '">';

					foreach ( $this->languages as $key => $language ) {
						switch ( $type ) {
							case 'option': {
									if ( isset( $option['selection']['languages']['language_' . $key] ) === false )
										$option['selection']['languages']['language_' . $key] = false;

									$html .= '<option value="language_' . $key . '" ' . selected( $option['selection']['languages']['language_' . $key], true, false ) . '>' . $language['native_name'] . '</option>';

									break;
								}
							case 'widget': {
									if ( ! isset( $this->options['selection']['languages']['language_' . $key] ) || current_user_can( 'manage_options' ) ) {
										if ( isset( $instance['rw_opt']['language_' . $key] ) === false )
											$instance['rw_opt']['language_' . $key] = 0;

										$html .= '<option value="language_' . $key . '" ' . selected( $instance['rw_opt']['language_' . $key], true, false ) . '>' . apply_filters( 'rw_option_display_name', $language['native_name'], 'language' ) . '</option>';
									}

									break;
								}
						}
					}

					if ( ($this->options['groups'] === true && $type === 'widget') || current_user_can( 'manage_options' ) )
						$html .= '</optgroup>';

					return $html;
				}
		}
	}

	/**
	 * Display widget display options.
	 */
	public function display_admin_widgets_options( $widget, $empty, $instance ) {
		if ( isset( $instance['rw_opt']['widget_select'] ) === false )
			$instance['rw_opt']['widget_select'] = false;

		echo '
		<p class="restrict-widgets-hide-div restrict-widgets">
			<label>' . __( 'Display / Hide Widget', self::ID ) . '</label>
			<select name="' . $widget->get_field_name( 'widget_select' ) . '" class="restrict-widgets-hide select2">
				<option value="yes" ' . selected( $instance['rw_opt']['widget_select'], true, false ) . '>' . __( 'Display widget on selected', self::ID ) . '</option>
				<option value="no" ' . selected( $instance['rw_opt']['widget_select'], false, false ) . '>' . __( 'Hide widget on selected', self::ID ) . '</option>
			</select>
		</p>
		<p class="restrict-widgets-select-div restrict-widgets">
			<select class="restrict-widgets-select select2" multiple="multiple" size="10" name="' . $widget->get_field_name( 'widget_multiselect' ) . '[]">';

		foreach ( $this->widget_options as $option => $text ) {
			echo $this->get_selection_group( $option, 'widget', $widget, $instance );
		}

		echo '
			</select>
		</p>';
	}

	/**
	 * Save widget display data.
	 */
	public function update_admin_widgets_options( $instance, $new_instance ) {
		if ( is_array( $new_instance['widget_multiselect'] ) ) {
			$selected = $new_instance['widget_multiselect'];

			// pages
			foreach ( $this->pages as $page ) {
				if ( in_array( 'pageid_' . $page->ID, $selected ) )
					$instance['rw_opt']['pageid_' . $page->ID] = true;
				else
					unset( $instance['rw_opt']['pageid_' . $page->ID] );
			}

			// custom post types
			foreach ( $this->custom_post_types as $cpt ) {
				if ( in_array( 'cpt_' . $cpt->name, $selected ) )
					$instance['rw_opt']['cpt_' . $cpt->name] = true;
				else
					unset( $instance['rw_opt']['cpt_' . $cpt->name] );
			}

			// custom post types archives
			foreach ( $this->custom_post_types_archives as $cpta ) {
				if ( in_array( 'cpta_' . $cpta->name, $selected ) )
					$instance['rw_opt']['cpta_' . $cpta->name] = true;
				else
					unset( $instance['rw_opt']['cpta_' . $cpta->name] );
			}

			// categories
			foreach ( $this->categories as $category ) {
				if ( in_array( 'category_' . $category->cat_ID, $selected ) )
					$instance['rw_opt']['category_' . $category->cat_ID] = true;
				else
					unset( $instance['rw_opt']['category_' . $category->cat_ID] );
			}

			// taxonomies
			foreach ( $this->taxonomies as $taxonomy ) {
				if ( in_array( 'taxonomy_' . $taxonomy->name, $selected ) )
					$instance['rw_opt']['taxonomy_' . $taxonomy->name] = true;
				else
					unset( $instance['rw_opt']['taxonomy_' . $taxonomy->name] );
			}

			// others
			foreach ( $this->others as $key => $value ) {
				if ( in_array( 'others_' . $key, $selected ) )
					$instance['rw_opt']['others_' . $key] = true;
				else
					unset( $instance['rw_opt']['others_' . $key] );
			}

			// devices
			foreach ( $this->devices as $key => $value ) {
				if ( in_array( 'devices_' . $key, $selected ) )
					$instance['rw_opt']['devices_' . $key] = true;
				else
					unset( $instance['rw_opt']['devices_' . $key] );
			}

			// bbpress
			if ( $this->bbpress_active === true ) {
				foreach ( $this->bbpress as $key => $value ) {
					if ( in_array( 'bbpress_' . $key, $selected ) )
						$instance['rw_opt']['bbpress_' . $key] = true;
					else
						unset( $instance['rw_opt']['bbpress_' . $key] );
				}
			}

			// users
			foreach ( $this->users as $key => $value ) {
				if ( in_array( 'users_' . $key, $selected ) )
					$instance['rw_opt']['users_' . $key] = true;
				else
					unset( $instance['rw_opt']['users_' . $key] );
			}

			// languages
			if ( ! empty( $this->languages ) ) {
				foreach ( $this->languages as $key => $value ) {
					if ( in_array( 'language_' . $key, $selected ) )
						$instance['rw_opt']['language_' . $key] = true;
					else
						unset( $instance['rw_opt']['language_' . $key] );
				}
			}
		}
		// clear plugin-instance
		else
			unset( $instance['rw_opt'] );

		// widget_multiselect
		$instance['rw_opt']['widget_select'] = ($new_instance['widget_select'] === 'yes' ? true : false);

		return $instance;
	}

	/**
	 * Check if widget is empty.
	 * 
	 * @param string $widget
	 * @param string $type
	 * @return boolean
	 */
	private function is_widget_empty( $widget, $type ) {
		if ( ! empty( $widget ) ) {
			foreach ( $widget as $option => $tmp ) {
				$action = explode( '_', $option, 2 );

				if ( $type === 'main' )
					$array = array( 'category', 'taxonomy', 'cpt', 'cpta', 'pageid', 'others', 'bbpress' );
				else
					$array = array( $type );

				if ( in_array( $action[0], $array ) )
					return false;
			}
		}

		return true;
	}

	/**
	 * Manage front-end display of widgets.
	 */
	public function display_frontend_widgets( $instance, $class, $args ) {
		return $this->restrict_widget( $instance, true, $args );
	}

	/**
	 * Display or hide specific widget.
	 */
	private function restrict_widget( $instance, $filter = true, $args = array() ) {
		// no restrict options?
		if ( empty( $instance['rw_opt'] ) || ! is_array( $instance['rw_opt'] ) )
			return $instance;

		// get widget options
		$options = $instance['rw_opt'];

		// get object id if available
		$post_id = get_queried_object_id();

		// get widget type (display or hide)
		$display_type = isset( $options['widget_select'] ) ? $options['widget_select'] : false;

		// no restrict options?
		if ( count( $options ) === 1 && isset( $options['widget_select'] ) )
			$access = ! $display_type;
		else {
			$access = true;

			// check groups of options
			foreach( array( 'language', 'users', 'devices', 'main' ) as $group ) {
				$valid_option = true;

				// check single group options
				if ( ! $this->is_widget_empty( $options, $group ) ) {
					switch( $group ) {
						case 'language':
							if ( $this->polylang_active || $this->wpml_active ) {
								// fix for WPML
								if ( function_exists( 'icl_object_id' ) ) {
									global $sitepress;

									if ( isset( $sitepress ) )
										$post_id = icl_object_id( $post_id, 'page', true, $sitepress->get_default_language() );
									else
										$post_id = icl_object_id( $post_id, 'page', false );
								}

								$valid_option = defined( 'ICL_LANGUAGE_CODE' ) && isset( $options['language_' . ICL_LANGUAGE_CODE] ) ? true : false;
							}
							break;

						case 'users':
							if ( is_user_logged_in() )
								$valid_option = isset( $options['users_logged_in'] );
							else
								$valid_option = isset( $options['users_logged_out'] );
							break;

						case 'devices':
							if ( wp_is_mobile() )
								$valid_option = isset( $options['devices_mobile'] );
							else
								$valid_option = isset( $options['devices_desktop'] );
							break;

						case 'main':
							if ( is_front_page() ) {
								$valid_option = isset( $options['others_front_page'] );

								if ( is_home() && ! $valid_option )
									$valid_option = isset( $options['others_blog_page'] );
							}
							elseif ( is_home() )
								$valid_option = isset( $options['others_blog_page'] );
							elseif ( is_singular() ) {
								if ( is_page() ) {
									if ( isset( $options['cpt_' . get_post_type( $post_id )] ) )
										$valid_option = true;
									else
										$valid_option = isset( $options['pageid_' . $post_id] );
								} else {
									$valid_option = isset( $options['cpt_' . get_post_type( $post_id )] );

									if ( is_single() && ! $valid_option )
										$valid_option = isset( $options['others_single_post'] );
								}
							}
							elseif ( is_post_type_archive() )
								$valid_option = isset( $options['cpta_' . get_query_var( 'post_type' )] );
							elseif ( is_category() )
								$valid_option = isset( $options['taxonomy_category'] ) ? true : isset( $options['category_' . get_query_var( 'cat' )] );
							elseif ( is_tag() ) {
								$object = get_queried_object();

								if ( $object && isset( $object->taxonomy ) && $object->taxonomy === 'post_tag' )
									$tag = 'wp_log_type';
								else
									$tag = '';

								$valid_option = isset( $options['taxonomy_post_tag'] ) ? true : isset( $options['taxonomy_' . $tag] );
							}
							elseif ( is_tax() ) {
								$object = get_queried_object();

								if ( $object && ! empty( $object->taxonomy ) )
									$taxonomy = $object->taxonomy;
								else
									$taxonomy = '';

								$valid_option = isset( $options['taxonomy_' . $taxonomy] );
							}
							elseif ( is_404() )
								$valid_option = isset( $options['others_404_page'] );
							elseif ( is_sticky() )
								$valid_option = isset( $options['others_sticky_post'] );
							elseif ( is_search() )
								$valid_option = isset( $options['others_search_page'] );
							elseif ( is_author() )
								$valid_option = isset( $options['others_author_archive'] );
							elseif ( is_date() )
								$valid_option = isset( $options['others_date_archive'] );
							elseif ( function_exists( 'bbp_is_search' ) && bbp_is_search() )
								$valid_option = isset( $options['bbpress_search'] );
							elseif ( function_exists( 'bbp_is_single_user' ) && bbp_is_single_user() )
								$valid_option = isset( $options['bbpress_single_user'] );
							elseif ( function_exists( 'bbp_is_topic_tag' ) && bbp_is_topic_tag() )
								$valid_option = isset( $options['bbpress_topic_tag'] );
							break;
					}
				}

				// option failed?
				if ( ! $valid_option ) {
					$access = ! $display_type;

					// finish foreach
					break;
				} else
					$access = $display_type;
			}
		}

		// filter true or false
		if ( $filter )
			$access = apply_filters_ref_array( 'rw_display_widget', array( $access, $instance ) );

		// display: return $instance, hide: return false
		return ( $access ? $instance : false );
	}

	/**
	 * Display Appearance menu and link to widgets.php if user can manage_widgets.
	 */
	public function manage_widgets_menu() {
		global $menu, $submenu;

		// if user can manage widgets but can't edit_theme_options, add widgets menu (appearance)
		if ( current_user_can( 'manage_widgets' ) && ! current_user_can( 'edit_theme_options' ) ) {
			foreach ( $menu as $menu_key => $menu_values ) {
				if ( isset( $menu_values[5] ) && $menu_values[5] === 'menu-appearance' ) {
					// if appearance menu not exists
					if ( empty( $submenu[$menu_values[2]] ) ) {
						$menu[$menu_key][1] = 'manage_widgets';
						$menu[$menu_key][2] = 'widgets.php';
					} else {
					// if appearance menu exists
						foreach ( $submenu[$menu_values[2]] as $submenu_key => $submenu_values ) {
							$submenu[$menu_values[2]][7] = array( __( 'Widgets' ), 'manage_widgets', 'widgets.php' );
						}
					}
				}
			}
		}
		// if user can't manage widgets but can edit_theme_options, remove widgets from menu
		elseif ( ! current_user_can( 'manage_widgets' ) && current_user_can( 'edit_theme_options' ) ) {
			foreach ( $menu as $menu_key => $menu_values ) {
				if ( isset( $menu_values[5] ) && $menu_values[5] === 'menu-appearance' ) {
					foreach ( $submenu[$menu_values[2]] as $submenu_key => $submenu_values ) {
						if ( isset( $submenu_values[2] ) && $submenu_values[2] == 'widgets.php' ) {
							$submenu[$menu_values[2]][$submenu_key][1] = 'manage_widgets';
						}
					}
				}
			}
		}
	}

	/**
	 * Fix for Appearance menu items on widgets.php if user can manage_widgets but can't edit theme options.
	 */
	public function manage_widgets_menu_fix() {
		global $menu, $submenu;

		foreach ( $menu as $menu_key => $menu_values ) {
			if ( isset( $menu_values[5] ) && $menu_values[5] === 'menu-appearance' ) {
				//if appearance menu not exists
				if ( empty( $submenu[$menu_values[2]] ) ) {
					$menu[$menu_key][1] = 'manage_widgets';
					$menu[$menu_key][2] = 'widgets.php';
				} else {
				//if appearance menu exists
					$submenu[$menu_values[2]][7] = array( __( 'Widgets' ), 'manage_widgets', 'widgets.php' );

					//remove menus with edit_theme_options capability
					foreach ( $submenu[$menu_values[2]] as $submenu_key => $submenu_values ) {
						if ( $submenu_values[1] == 'edit_theme_options' )
							unset( $submenu[$menu_values[2]][$submenu_key] );
					}

					//fix if appearance menu item is only 1, remove submenu
					if ( count( $submenu[$menu_values[2]] ) < 2 ) {
						unset( $submenu[$menu_values[2]][7] );
						$menu[$menu_key][1] = 'manage_widgets';
						$menu[$menu_key][2] = 'widgets.php';
					}
				}
			}
		}
	}

	/**
	 * Manage widgets capability.
	 */
	public function manage_widgets_cap( $allcaps, $cap, $args ) {
		global $pagenow;

		if ( $pagenow === 'widgets.php' || (defined( 'DOING_AJAX' ) && DOING_AJAX) ) {
			//break if we're not asking to edit widgets
			if ( ('edit_theme_options' != $args[0]) || empty( $allcaps['manage_widgets'] ) )
				return $allcaps;
			else {
				//if user can't edit_theme_options but can manage_widgets
				if ( empty( $allcaps['edit_theme_options'] ) ) {
					//menu fix
					add_action( 'admin_menu', array( $this, 'manage_widgets_menu_fix' ), 999 );

					//add cap to edit widgets
					$allcaps['edit_theme_options'] = true;
				}
			}
		}

		return $allcaps;
	}
}

/**
 * Initialize Restrict Widgets.
 * 
 * @return object
 */
function Restrict_Widgets() {
	static $instance;

	// first call to instance() initializes the plugin
	if ( $instance === null || ! ( $instance instanceof Restrict_Widgets ) )
		$instance = Restrict_Widgets::instance();

	return $instance;
}

Restrict_Widgets();