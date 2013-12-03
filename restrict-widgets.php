<?php
/*
Plugin Name: Restrict Widgets
Description: All in one solution for widget management in WordPress. Allows you to hide or display widgets on specified pages and restrict access for users.
Version: 1.2.0
Author: dFactory
Author URI: http://www.dfactory.eu/
Plugin URI: http://www.dfactory.eu/plugins/restrict-widgets/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: restrict-widgets
Domain Path: /languages

Restrict Widgets
Copyright (C) 2013, Digital Factory - info@digitalfactory.pl

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

if(!defined('ABSPATH')) exit; // Exit if accessed directly

define('RESTRICT_WIDGETS_URL', plugins_url('', __FILE__));
define('RESTRICT_WIDGETS_REL_PATH', dirname(plugin_basename(__FILE__)).'/');

$restrict_widgets = new Restrict_Widgets();

class Restrict_Widgets
{
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
	private $options = array();
	private $hidden_widget = '';
	private $checked_widget = '';
	private $widgets = array();
	private $bbpress_active = FALSE;
	private $polylang_active = FALSE;
	private $wpml_active = FALSE;


	public function __construct()
	{
		register_activation_hook(__FILE__, array(&$this, 'multisite_activation'));
		register_deactivation_hook(__FILE__, array(&$this, 'multisite_deactivation'));

		//actions
		add_action('wp_loaded', array(&$this, 'polylang_widgets'), 6);
		add_action('wp_head', array(&$this, 'restrict_sidebar_widgets'), 10);
		add_action('plugins_loaded', array(&$this, 'load_textdomain'));
		add_action('widgets_init', array(&$this, 'load_other_data'), 10);
		add_action('widgets_init', array(&$this, 'init_restrict_sidebars'), 11);
		add_action('admin_init', array(&$this, 'load_dynamic_data'));
		add_action('admin_init', array(&$this, 'save_restrict_options'));
		add_action('sidebar_admin_page', array(&$this, 'add_widgets_options_box'));
		add_action('in_widget_form', array(&$this, 'display_admin_widgets_options'), 99, 3);
		add_action('admin_enqueue_scripts', array(&$this, 'widgets_scripts_styles'));
		add_action('admin_menu', array(&$this, 'manage_widgets_menu'));

		//filters
		add_filter('widget_display_callback', array(&$this, 'display_frontend_widgets'), 10, 3);
		add_filter('widget_update_callback', array(&$this, 'update_admin_widgets_options'), 10, 3);
		add_filter('user_has_cap', array(&$this, 'manage_widgets_cap'), 10, 3);
		add_filter('dynamic_sidebar_params', array(&$this, 'restrict_sidebar_params'), 10, 3);	
		add_filter('plugin_row_meta', array(&$this, 'plugin_extend_links'), 10, 2);
		add_filter('plugin_action_links', array(&$this, 'plugin_settings_link'), 10, 2);
	}


	/**
	 * Multisite activation
	*/
	public function multisite_activation($networkwide)
	{
		if(is_multisite() && $networkwide)
		{
			global $wpdb;

			$activated_blogs = array();
			$current_blog_id = get_current_blog_id();
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->activate_single();
				$activated_blogs[] = (int)$blog_id;
			}

			switch_to_blog($current_blog_id);
			update_site_option('restrict_widgets_activated_blogs', $activated_blogs, array());
		}
		else
			$this->activate_single();
	}


	/**
	 * Activation
	*/
	public function activate_single()
	{
		$role = get_role('administrator');
		$role->add_cap('manage_widgets');

		//default settings
		add_option(
			'rw_widgets_options',
			array(
				'available' => array(),
				'selection' => array(),
				'sidebars' => array(),
				'groups' => TRUE,
				'deactivation' => FALSE,
			),
			'',
			'no'
		);
	}


	/**
	 * Multisite deactivation
	*/
	public function multisite_deactivation($networkwide)
	{
		if(is_multisite() && $networkwide)
		{
			global $wpdb;

			$current_blog_id = get_current_blog_id();
			$blogs_ids = $wpdb->get_col($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs, ''));

			if(($activated_blogs = get_site_option('restrict_widgets_activated_blogs', FALSE, FALSE)) === FALSE)
				$activated_blogs = array();

			foreach($blogs_ids as $blog_id)
			{
				switch_to_blog($blog_id);
				$this->deactivate_single();

				if(in_array((int)$blog_id, $activated_blogs, TRUE))
					unset($activated_blogs[array_search($blog_id, $activated_blogs)]);
			}

			switch_to_blog($current_blog_id);
			update_site_option('restrict_widgets_activated_blogs', $activated_blogs);
		}
		else
			$this->deactivate_single();
	}


	/**
	 * Deactivation
	*/
	public function deactivate_single()
	{
		$option = get_option('rw_widgets_options');

		if($option['deactivation'] === TRUE)
		{
			global $wp_roles, $wp_registered_widgets;

			//removes roles
			foreach($wp_roles->roles as $role_name => $empty)
			{
				$role = get_role($role_name);
				$role->remove_cap('manage_widgets');
			}

			//clears all widgets
			foreach($wp_registered_widgets as $widget)
			{
				if(isset($widget['params'][0]['number']) && $widget['params'][0]['number'] !== -1)
				{
					$option = get_option($widget['callback'][0]->option_name);
					unset($option[$widget['params'][0]['number']]['rw_opt']);
					update_option($widget['callback'][0]->option_name, $option);
				}
			}

			delete_option('rw_widgets_options');
		}
	}


	/**
	 * Fix for is_active_sidebar (all hidden widgets on sidebar = FALSE)
	*/
	public function restrict_sidebar_widgets()
	{
		if(!is_admin())
		{
			$options = get_option('rw_widgets_options');

			if(isset($options['sidebar']) && $options['sidebar'] === TRUE)
			{
				global $wp_registered_widgets, $_wp_sidebars_widgets;

				$widgets_c = $instance = array();

				foreach($wp_registered_widgets as $widget)
				{
					if(isset($widget['callback'][0]->option_name) && $widget['callback'][0]->option_name !== '' && empty($widgets_classes[$widget['callback'][0]->option_name]))
						$widgets_c[$widget['callback'][0]->option_name] = get_option($widget['callback'][0]->option_name);
				}

				foreach($widgets_c as $widget_base_id => $widgets)
				{
					if(is_array($widgets))
					{
						foreach($widgets as $widget_id => $widget)
						{
							if(is_int($widget_id))
								$instance[$widget_base_id.'-'.$widget_id] = $widget;
						}
					}
				}

				if(!empty($instance))
				{
					foreach($instance as $widget_id => $widget)
					{
						$this->widgets[substr($widget_id, 7)] = $this->restrict_widget($widget, FALSE, array('widget_id' => substr($widget_id, 7)));
					}
				}

				if(!empty($this->widgets))
				{
					$widgets_c = $_wp_sidebars_widgets;

					if(!empty($widgets_c))
					{
						foreach($widgets_c as $sidebar => $s_widgets)
						{
							if(!empty($s_widgets) && is_array($s_widgets))
							{
								foreach($s_widgets as $widget)
								{
									if($this->widgets[$widget] === FALSE && ($widget_key = array_search($widget, $_wp_sidebars_widgets[$sidebar])) !== FALSE)
										unset($_wp_sidebars_widgets[$sidebar][$widget_key]);
								}
							}
						}
					}
				}
			}
		}
	}


	/**
	 * Fix for Polylang - removes language switcher in widgets
	*/
	public function polylang_widgets()
	{
		if($this->polylang_active === TRUE)
		{
			global $polylang;

			if(has_action('in_widget_form', array($polylang, 'in_widget_form')))
				remove_action('in_widget_form', array($polylang, 'in_widget_form'));
		}
	}


	/**
	 * Loads textdomain
	*/
	public function load_textdomain()
	{
		load_plugin_textdomain('restrict-widgets', FALSE, RESTRICT_WIDGETS_REL_PATH.'languages/');

		if(class_exists('bbPress'))
			$this->bbpress_active = TRUE;

		if(class_exists('Polylang'))
			$this->polylang_active = TRUE;

		if(class_exists('SitePress'))
			$this->wpml_active = TRUE;
	}


	/**
	 * Add links to Support Forum
	*/
	public function plugin_extend_links($links, $file) 
	{
		if(!current_user_can('install_plugins'))
			return $links;

		$plugin = plugin_basename(__FILE__);

		if($file == $plugin) 
		{
			return array_merge(
				$links,
				array(sprintf('<a href="http://www.dfactory.eu/support/forum/restrict-widgets/" target="_blank">%s</a>', __('Support', 'restrict-widgets')))
			);
		}

		return $links;
	}


	/**
	 * Add links to Settings page
	*/
	function plugin_settings_link($links, $file) 
	{
		if(!is_admin() || !current_user_can('edit_theme_options'))
			return $links;
			
		static $plugin;
		
		$plugin = plugin_basename(__FILE__);
		
		if($file == $plugin) 
		{
			$settings_link = sprintf('<a href="%s">%s</a>', admin_url('widgets.php'), __('Widgets', 'restrict-widgets'));
			array_unshift($links, $settings_link);
		}
	
		return $links;
	}


	/**
	 * Hides widgets for users without admin privileges
	*/
	public function restrict_sidebar_params($params)
	{
		if(!current_user_can('manage_options'))
		{
			global $wp_registered_widgets;

			$option = get_option('rw_widgets_options');

			if(
				//standard based widget class
				(isset($wp_registered_widgets[$params[0]['widget_id']]['callback'][0]) && is_object($wp_registered_widgets[$params[0]['widget_id']]['callback'][0]) && in_array(get_class($wp_registered_widgets[$params[0]['widget_id']]['callback'][0]), array_keys($option['available']))) ||

				//non-standard based widget
				(isset($wp_registered_widgets[$params[0]['widget_id']]['id']) && in_array($wp_registered_widgets[$params[0]['widget_id']]['id'], array_keys($option['available'])))
			)
				$params[0]['_hide'] = 1;
		}

		return $params;
	}


	/**
	 * Loads dynamic data
	*/
	public function load_dynamic_data()
	{
		$this->taxonomies = get_taxonomies(
			array(
				'_builtin' => FALSE
			),
			'objects',
			'and'
		);

		$this->categories = get_categories(
			array(
				'hide_empty' => FALSE
			)
		);

		$this->custom_post_types = get_post_types(
			array(
				'public' => TRUE,
				'_builtin' => FALSE
			),
			'objects',
			'and'
		);

		$this->custom_post_types_archives = get_post_types(
			array(
				'public' => TRUE,
				'_builtin' => FALSE,
				'has_archive' => TRUE
			),
			'objects',
			'and'
		);
	}


	/**
	 * Loads other data (dynamic data here too like get_pages() due to some WP restrictions) and languages
	*/
	public function load_other_data()
	{
		$this->options = array(
			'pages' => __('Pages', 'restrict-widgets'),
			'custom_post_types' => __('Custom Post Types', 'restrict-widgets'),
			'custom_post_types_archives' => __('Custom Post Type Archives', 'restrict-widgets'),
			'categories' => __('Categories', 'restrict-widgets'),
			'taxonomies' => __('Taxonomies', 'restrict-widgets'),
			'others' => __('Others', 'restrict-widgets')
		);

		//bbPress support
		if($this->bbpress_active === TRUE)
		{
			$this->bbpress = array(
				'search' => __('Search', 'restrict-widgets'),
				'single_user' => __('Single User', 'restrict-widgets'),
				'topic_tag' => __('Topic Tag', 'restrict-widgets')
			);

			$this->options['bbpress'] = __('bbPress', 'restrict-widgets');
		}

		$this->options['devices'] = __('Devices', 'restrict-widgets');
		$this->options['users'] = __('Users', 'restrict-widgets');
		$this->options['languages'] = __('Languages', 'restrict-widgets');

		$this->others = array(
			'front_page' => __('Front Page', 'restrict-widgets'), 
			'blog_page' => __('Blog Page', 'restrict-widgets'),
			'single_post' => __('Single Posts', 'restrict-widgets'),
			'sticky_post' => __('Sticky Posts', 'restrict-widgets'),
			'author_archive' => __('Author Archive', 'restrict-widgets'),
			'date_archive' => __('Date Archive', 'restrict-widgets'),
			'404_page' => __('404 Page', 'restrict-widgets'),
			'search_page' => __('Search Page', 'restrict-widgets')
		);

		$this->users = array(
			'logged_in' => __('Logged in users', 'restrict-widgets'), 
			'logged_out' => __('Logged out users', 'restrict-widgets')
		);

		$this->devices = array(
			'mobile' => __('Mobile', 'restrict-widgets'),
			'desktop' => __('Desktop, Laptop, etc.', 'restrict-widgets')
		);

		$this->pages = get_pages(
			array(
				'sort_order' => 'ASC',
				'sort_column' => 'post_title',
				'number' => '',
				'post_type' => 'page',
				'post_status' => 'publish'
			)
		);

		//Polylang support
		if($this->polylang_active === TRUE)
		{
			$languages = get_terms('language', array('hide_empty' => FALSE));

			//we need to make WMPL style table
			foreach($languages as $language)
			{
				$this->languages[$language->slug] = array('native_name' => $language->name);
			}
		}
		//WMPL support
		elseif($this->wpml_active === TRUE)
			$this->languages = icl_get_languages('skip_missing=0&orderby=native_name&order=asc');
		else
			$this->languages = FALSE;
	}


	/**
	 * Removes selected sidebars for users without edit_theme_options capability
	*/
	public function init_restrict_sidebars()
	{
		if(!current_user_can('manage_options') && current_user_can('edit_theme_options'))
		{
			$option = get_option('rw_widgets_options');

			foreach(array_keys($option['sidebars']) as $sidebar_id)
			{
				unregister_sidebar($sidebar_id);
			}
		}
	}


	/**
	 * Saves restrict widgets options
	*/
	public function save_restrict_options()
	{
		//are we saving with administration privileges?
		if(current_user_can('manage_options') && isset($_POST['save-widgets-options']))
		{
			global $wp_roles;

			//what we wanna save?
			$save_widgets = array(
				'available' => array(),
				'selection' => array(),
				'sidebars' => array(),
				'groups' => FALSE,
				'sidebar' => FALSE,
				'deactivation' => FALSE
			);

			//display groups?
			$save_widgets['groups'] = (isset($_POST['options-widgets-groups']) ? TRUE : FALSE);

			//modify is_active_sidebar?
			$save_widgets['sidebar'] = (isset($_POST['options-widgets-sidebar']) ? TRUE : FALSE);

			//remove plugin data?
			$save_widgets['deactivation'] = (isset($_POST['options-widgets-deactivation']) ? TRUE : FALSE);

			//do we have some available widgets?
			if(isset($_POST['options-available-widgets']))
			{
				foreach($_POST['options-available-widgets'] as $widget_class)
				{
					$save_widgets['available'][$widget_class] = TRUE;
				}
			}

			//do we have some specific elements?
			if(isset($_POST['options-widgets-selection']))
			{
				$selected = $_POST['options-widgets-selection'];

				//only for custom post types (archives)
				foreach($selected as $element)
				{
					$tmp = explode('_', $element, 2);

					if(in_array($tmp[0], array('cpt', 'cpta'), TRUE))
						$save_widgets['selection'][$tmp[0] === 'cpt' ? 'custom_post_types' : 'custom_post_types_archives'][$tmp[0].'_'.sanitize_key($tmp[1])] = TRUE;
				}

				foreach($this->pages as $page)
				{
					if(in_array('pageid_'.$page->ID, $selected, TRUE))
						$save_widgets['selection']['pages']['pageid_'.$page->ID] = TRUE;
				}

				foreach($this->custom_post_types as $cpt)
				{
					if(in_array('cpt_'.$cpt->name, $selected, TRUE))
						$save_widgets['selection']['custom_post_types']['cpt_'.$cpt->name] = TRUE;
				}

				foreach($this->categories as $category)
				{
					if(in_array('category_'.$category->cat_ID, $selected, TRUE))
						$save_widgets['selection']['categories']['category_'.$category->cat_ID] = TRUE;
				}

				foreach($this->taxonomies as $taxonomy)
				{
					if(in_array('taxonomy_'.$taxonomy->name, $selected, TRUE))
						$save_widgets['selection']['taxonomies']['taxonomy_'.$taxonomy->name] = TRUE;
				}

				foreach($this->others as $key => $value)
				{
					if(in_array('others_'.$key, $selected, TRUE))
						$save_widgets['selection']['others']['others_'.$key] = TRUE;
				}

				foreach($this->users as $key => $value)
				{
					if(in_array('users_'.$key, $selected, TRUE))
						$save_widgets['selection']['users']['users_'.$key] = TRUE;
				}

				foreach($this->devices as $key => $value)
				{
					if(in_array('devices_'.$key, $selected, TRUE))
						$save_widgets['selection']['devices']['devices_'.$key] = TRUE;
				}

				if($this->bbpress_active === TRUE)
				{
					foreach($this->bbpress as $key => $value)
					{
						if(in_array('bbpress_'.$key, $selected, TRUE))
							$save_widgets['selection']['bbpress']['bbpress_'.$key] = TRUE;
					}
				}
			}

			//do we have some sidebars?
			if(isset($_POST['options-widgets-sidebars']))
			{
				foreach($_POST['options-widgets-sidebars'] as $sidebar)
				{
					$save_widgets['sidebars'][$sidebar] = TRUE;
				}
			}

			//do we have some roles?
			$roles_a = (isset($_POST['options-widgets-roles']) ? $_POST['options-widgets-roles'] : array());

			foreach($wp_roles->roles as $role_name => $role_array)
			{
				if($role_name !== 'administrator')
				{
					$role = get_role($role_name);

					if(in_array($role_name, $roles_a))
						$role->add_cap('manage_widgets');
					else
						$role->remove_cap('manage_widgets');
				}
			}

			update_option('rw_widgets_options', $save_widgets);
		}
	}


	/**
	 * Displays restrict widgets options box
	*/
	public function add_widgets_options_box()
	{
		if(!current_user_can('manage_options'))
			return;

		global $wp_roles, $wp_registered_widgets, $wp_registered_sidebars;

		$widgets_unique = array();
		$option = get_option('rw_widgets_options');

		if(isset($option['groups']) === FALSE) $option['groups'] = FALSE;
		if(isset($option['deactivation']) === FALSE) $option['deactivation'] = FALSE;

		//we need to make a copy for sorting
		$widgets = $wp_registered_widgets;
		usort($widgets, array(&$this, 'sort_widgets_by_name'));

		//we need to make unique array to avoid duplicated instances of widgets later
		foreach($widgets as $widget)
		{
			if(isset($widget['callback'][0]) && is_object($widget['callback'][0]))
				$widgets_unique[get_class($widget['callback'][0])] = $widget['name'];
			else
				$widgets_unique[$widget['id']] = $widget['name'];
		}

		echo '
		<div id="widgets-options" class="widgets-holder-wrap restrict-widgets">
			<div class="sidebar-name">
				<div class="sidebar-name-arrow"><br /></div>
				<h3>'.__('Restrict widgets').'</h3>
			</div>
			<div class="widget-holder">
				<form action="" method="post">
					<p class="howto">'.__('Use this settings to manage access to widgets page and to restrict availability of certain widgets, sidebars and widgets options to site administrators only.', 'restrict-widgets').'</p>
					<table>
						<tr>
							<td class="label"><label>'.__('Restrict Users', 'restrict-widgets').'</label></td>
							<td>
								<select name="options-widgets-roles[]" id="options-widgets-roles" multiple="multiple">';

		foreach($wp_roles->roles as $role_name => $role_array)
		{
			echo '<option value="'.$role_name.'" '.selected((in_array('manage_widgets', array_keys($role_array['capabilities']), TRUE) ? TRUE : FALSE), TRUE, FALSE).' '.disabled(($role_name === 'administrator' ? TRUE : FALSE), TRUE, FALSE).'>'.$role_array['name'].'</option>';
		}

		echo '					</select>
								<p class="description">'.__('Select user roles restricted to manage widgets.', 'restrict-widgets').'</p>
							</td>
						</tr>
						<tr>
							<td class="label"><label>'.__('Restrict Sidebars', 'restrict-widgets').'</label></td>
							<td>
								<select name="options-widgets-sidebars[]" id="options-widgets-sidebars" multiple="multiple">';

		foreach($wp_registered_sidebars as $sidebar)
		{
			if($sidebar['id'] !== 'wp_inactive_widgets')
			{
				if(isset($option['sidebars'][$sidebar['id']]) === FALSE)
					$option['sidebars'][$sidebar['id']] = FALSE;

				echo '<option value="'.$sidebar['id'].'" '.selected($option['sidebars'][$sidebar['id']], TRUE, FALSE).'>'.$sidebar['name'].'</option>';
			}
		}

		echo '					</select>
								<p class="description">'.__('Select which sidebars will be restricted to admins only.', 'restrict-widgets').'</p>
							</td>
						</tr>
						<tr>
							<td class="label"><label>'.__('Restrict Widgets', 'restrict-widgets').'</label></td>
							<td>
								<select name="options-available-widgets[]" id="options-available-widgets" multiple="multiple">';

		foreach(array_unique($widgets_unique) as $widget_class => $widget_name)
		{
			if(isset($option['available'][$widget_class]) === FALSE)
				$option['available'][$widget_class] = FALSE;

			echo '<option value="'.$widget_class.'" '.selected($option['available'][$widget_class], TRUE, FALSE).'>'.$widget_name.'</option>';
		}

		echo '					</select>
								<p class="description">'.__('Select which widgets will be restricted to admins only.', 'restrict-widgets').'</p>
							</td>
						</tr>
						<tr>
							<td class="label"><label>'.__('Restrict Widget Options', 'restrict-widgets').'</label></td>
							<td>
								<select name="options-widgets-selection[]" id="options-widgets-selection" multiple="multiple">';

		foreach($this->options as $group_name => $value)
		{
			echo $this->getSelectionGroup($group_name, 'option', '', '', $option);
		}

		echo '					</select>
								<p class="description">'.__('Select which widget options will be restricted to admins only.', 'restrict-widgets').'</p>
							</td>
						</tr>
						<tr>
							<td class="label"><label>'.__('Restrict Option Groups', 'restrict-widgets').'</label></td>
							<td>
								<input type="checkbox" name="options-widgets-groups" id="options-widgets-groups" value="1" '.checked($option['groups'], TRUE, FALSE).' />
								<label for="options-widgets-groups" class="description">'.__('Display widget options in groups', 'restrict-widgets').'</label>
							</td>
						</tr>
						<tr>
							<td class="label"><label>'.__('Modify is_active_sidebar()', 'restrict-widgets').'</label></td>
							<td>
								<input type="checkbox" name="options-widgets-sidebar" id="options-widgets-sidebar" value="1" '.checked($option['sidebar'], TRUE, FALSE).' />
								<label for="options-widgets-sidebar" class="description">'.__('By default is_active_sidebar() function returns TRUE even if no widget is displayed in a sidebar. Check this if you want is_active_sidebar() to recognize Restrict Widgets display settings.', 'restrict-widgets').'</label>
							</td>
						</tr>
						<tr>
							<td class="label"><label>'.__('Plugin Deactivation', 'restrict-widgets').'</label></td>
							<td>
								<input type="checkbox" name="options-widgets-deactivation" id="options-widgets-deactivation" value="1" '.checked($option['deactivation'], TRUE, FALSE).' />
								<label for="options-widgets-deactivation" class="description">'.__('Remove all plugin data on deactivation', 'restrict-widgets').'</label>
							</td>
						</tr>
					</table>
					<input type="submit" value="'.__('Save settings', 'restrict-widgets').'" name="save-widgets-options" class="button button-primary" id="save-widgets-options" />
					<p id="df-credits">Created by<a href="http://www.dfactory.eu/?utm_source=restrict-widgets-settings&utm_medium=link&utm_campaign=created-by" target="_blank" title="dFactory - Quality plugins for WordPress"><img src="'.RESTRICT_WIDGETS_URL.'/images/logo-dfactory.png'.'" title="dFactory - Quality plugins for WordPress" alt="dFactory - Quality plugins for WordPress" /></a></p>
					<br class="clear" />
				</form>
			</div>
		</div>';
	}


	/**
	 * Sorts widgets by name
	*/
	private function sort_widgets_by_name($element_a, $element_b)
	{
		return strnatcasecmp($element_a['name'], $element_b['name']);
	}


	/**
	 * Loads scripts and styles
	*/
	public function widgets_scripts_styles($page)
	{
		if($page !== 'widgets.php')
        	return;

		wp_enqueue_script(
			'chosen',
			RESTRICT_WIDGETS_URL.'/js/chosen.jquery.min.js',
			array('jquery')
		);

		wp_enqueue_script(
			'restrict-widgets',
			RESTRICT_WIDGETS_URL.'/js/restrict-widgets-admin.js',
			array('jquery', 'chosen')
		);

		$js_widgets = $js_class = $js_nonclass = array();
		$orphan_sidebar = 0;

		//only for users without admin privileges
		if(!current_user_can('manage_options'))
		{
			global $wp_registered_widgets;

			$option = get_option('rw_widgets_options');
			$restrict = array_keys($option['available']);
			$widgets = array();
			$orphan_sidebar = 1;

			foreach(wp_get_sidebars_widgets() as $sidebar)
			{
				foreach($sidebar as $widget)
				{
					$widgets[] = $widget;
				}
			}

			//which sidebars to hide
			foreach($widgets as $widget)
			{
				if(
					//standard based widget class
					(isset($wp_registered_widgets[$widget]['callback'][0]) && is_object($wp_registered_widgets[$widget]['callback'][0]) && in_array(get_class($wp_registered_widgets[$widget]['callback'][0]), $restrict)) ||

					//non-standard based widget
					(isset($wp_registered_widgets[$widget]['id']) && in_array($wp_registered_widgets[$widget]['id'], $restrict))
				)
					$js_widgets[] = $widget;
			}

			//which widgets to hide
			foreach($wp_registered_widgets as $widget)
			{
				//standard based widget class
				if(isset($widget['callback'][0]) && is_object($widget['callback'][0]) && in_array(get_class($widget['callback'][0]), $restrict))
					$js_class[] = $widget['callback'][0]->id_base;
				//non-standard based widget
				elseif(in_array($widget['id'], $restrict))
					$js_nonclass[] = $widget['id'];
			}
		}

		wp_localize_script(
			'restrict-widgets',
			'rwArgs',
			array(
				'placeholder_text' => esc_attr__('Select options', 'restrict-widgets'),
				'restrict_available_widgets' => esc_attr__('Select widgets', 'restrict-widgets'),
				'restrict_widgets_selection' => esc_attr__('Select widgets options', 'restrict-widgets'),
				'restrict_sidebars' => esc_attr__('Select sidebars', 'restrict-widgets'),
				'restrict_roles' => esc_attr__('Select roles', 'restrict-widgets'),
				'restrict_languages' => esc_attr__('Select languages', 'restrict-widgets'),
				'restrict_widgets' => $js_widgets,
				'restrict_class' => array_unique($js_class),
				'restrict_nonclass' => array_unique($js_nonclass),
				'restrict_orphan_sidebar' => $orphan_sidebar
			)
		);

		wp_enqueue_style('chosen', RESTRICT_WIDGETS_URL.'/css/chosen.css');
		wp_enqueue_style('style', RESTRICT_WIDGETS_URL.'/css/restrict-widgets-admin.css');
	}


	/**
	 * Displays lists of data (pages, custom post types, categories, taxonomiex, ...) for options box and widget box
	*/
	private function getSelectionGroup($group_name, $type, $widget = '', $instance = '', $option = '')
	{
		$rw_option = get_option('rw_widgets_options');
		$html = '';

		switch($group_name)
		{
			case 'pages':
			{
				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['pages'].'">';

				foreach($this->pages as $page)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['pages']['pageid_'.$page->ID]) === FALSE)
								$option['selection']['pages']['pageid_'.$page->ID] = FALSE;

							$html .= '<option value="pageid_'.$page->ID.'" '.selected($option['selection']['pages']['pageid_'.$page->ID], TRUE, FALSE).'>'.$page->post_title.'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['pages']['pageid_'.$page->ID]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['pageid_'.$page->ID]) === FALSE)
									$instance['rw_opt']['pageid_'.$page->ID] = 0;

								$html .= '<option value="pageid_'.$page->ID.'" '.selected($instance['rw_opt']['pageid_'.$page->ID], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', $page->post_title, 'page').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
			case 'custom_post_types':
			{
				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['custom_post_types'].'">';

				foreach($this->custom_post_types as $cpt)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['custom_post_types']['cpt_'.$cpt->name]) === FALSE)
								$option['selection']['custom_post_types']['cpt_'.$cpt->name] = FALSE;

							$html .= '<option value="cpt_'.$cpt->name.'" '.selected($option['selection']['custom_post_types']['cpt_'.$cpt->name], TRUE, FALSE).'>'.sprintf(__('Single %s','restrict-widgets'), $cpt->label).'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['custom_post_types']['cpt_'.$cpt->name]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['cpt_'.$cpt->name]) === FALSE)
									$instance['rw_opt']['cpt_'.$cpt->name] = 0;

								$html .= '<option value="cpt_'.$cpt->name.'" '.selected($instance['rw_opt']['cpt_'.$cpt->name], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', sprintf(__('Single %s','restrict-widgets'), $cpt->label), 'custom_post_type').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
			case 'custom_post_types_archives':
			{
				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['custom_post_types_archives'].'">';

				foreach($this->custom_post_types_archives as $cpta)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['custom_post_types_archives']['cpta_'.$cpta->name]) === FALSE)
								$option['selection']['custom_post_types_archives']['cpta_'.$cpta->name] = FALSE;

							$html .= '<option value="cpta_'.$cpta->name.'" '.selected($option['selection']['custom_post_types_archives']['cpta_'.$cpta->name], TRUE, FALSE).'>'.sprintf(__('%s Archive','restrict-widgets'), $cpta->label).'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['custom_post_types_archives']['cpta_'.$cpta->name]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['cpta_'.$cpta->name]) === FALSE)
									$instance['rw_opt']['cpta_'.$cpta->name] = 0;

								$html .= '<option value="cpta_'.$cpta->name.'" '.selected($instance['rw_opt']['cpta_'.$cpta->name], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', sprintf(__('%s Archive','restrict-widgets'), $cpta->label), 'custom_post_type_archive').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
			case 'categories':
			{
				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['categories'].'">';

				foreach($this->categories as $category)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['categories']['category_'.$category->cat_ID]) === FALSE)
								$option['selection']['categories']['category_'.$category->cat_ID] = FALSE;

							$html .= '<option value="category_'.$category->cat_ID.'" '.selected($option['selection']['categories']['category_'.$category->cat_ID], TRUE, FALSE).'>'.$category->cat_name.'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['categories']['category_'.$category->cat_ID]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['category_'.$category->cat_ID]) === FALSE)
									$instance['rw_opt']['category_'.$category->cat_ID] = 0;

								$html .= '<option value="category_'.$category->cat_ID.'" '.selected($instance['rw_opt']['category_'.$category->cat_ID], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', $category->cat_name, 'category').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
			case 'taxonomies':
			{
				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['taxonomies'].'">';

				foreach($this->taxonomies as $taxonomy)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['taxonomies']['taxonomy_'.$taxonomy->name]) === FALSE)
								$option['selection']['taxonomies']['taxonomy_'.$taxonomy->name] = FALSE;

							$html .= '<option value="taxonomy_'.$taxonomy->name.'" '.selected($option['selection']['taxonomies']['taxonomy_'.$taxonomy->name], TRUE, FALSE).'>'.$taxonomy->label.'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['taxonomies']['taxonomy_'.$taxonomy->name]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['taxonomy_'.$taxonomy->name]) === FALSE)
									$instance['rw_opt']['taxonomy_'.$taxonomy->name] = 0;

								$html .= '<option value="taxonomy_'.$taxonomy->name.'" '.selected($instance['rw_opt']['taxonomy_'.$taxonomy->name], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', $taxonomy->label, 'taxonomy').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
			case 'others':
			{
				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['others'].'">';

				foreach($this->others as $key => $value)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['others']['others_'.$key]) === FALSE)
								$option['selection']['others']['others_'.$key] = FALSE;

							$html .= '<option value="others_'.$key.'" '.selected($option['selection']['others']['others_'.$key], TRUE, FALSE).'>'.$value.'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['others']['others_'.$key]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['others_'.$key]) === FALSE)
									$instance['rw_opt']['others_'.$key] = 0;

								$html .= '<option value="others_'.$key.'" '.selected($instance['rw_opt']['others_'.$key], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', $value, 'other').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
			case 'devices':
			{
				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['devices'].'">';

				foreach($this->devices as $key => $value)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['devices']['devices_'.$key]) === FALSE)
								$option['selection']['devices']['devices_'.$key] = FALSE;

							$html .= '<option value="devices_'.$key.'" '.selected($option['selection']['devices']['devices_'.$key], TRUE, FALSE).'>'.$value.'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['devices']['devices_'.$key]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['devices_'.$key]) === FALSE)
									$instance['rw_opt']['devices_'.$key] = 0;

								$html .= '<option value="devices_'.$key.'" '.selected($instance['rw_opt']['devices_'.$key], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', $value, 'device').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
			case 'bbpress':
			{
				if($this->bbpress_active === FALSE)
					return $html;

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['bbpress'].'">';

				foreach($this->bbpress as $key => $value)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['bbpress']['bbpress_'.$key]) === FALSE)
								$option['selection']['bbpress']['bbpress_'.$key] = FALSE;

							$html .= '<option value="bbpress_'.$key.'" '.selected($option['selection']['bbpress']['bbpress_'.$key], TRUE, FALSE).'>'.$value.'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['bbpress']['bbpress_'.$key]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['bbpress_'.$key]) === FALSE)
									$instance['rw_opt']['bbpress_'.$key] = 0;

								$html .= '<option value="bbpress_'.$key.'" '.selected($instance['rw_opt']['bbpress_'.$key], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', $value, 'bbpress').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
			case 'users':
			{
				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['users'].'">';

				foreach($this->users as $key => $value)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['users']['users_'.$key]) === FALSE)
								$option['selection']['users']['users_'.$key] = FALSE;

							$html .= '<option value="users_'.$key.'" '.selected($option['selection']['users']['users_'.$key], TRUE, FALSE).'>'.$value.'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['users']['users_'.$key]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['users_'.$key]) === FALSE)
									$instance['rw_opt']['users_'.$key] = 0;

								$html .= '<option value="users_'.$key.'" '.selected($instance['rw_opt']['users_'.$key], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', $value, 'user').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
			case 'languages':
			{
				if($this->languages !== FALSE)
					return $html;

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '<optgroup label="'.$this->options['languages'].'">';

				foreach($this->languages as $key => $language)
				{
					switch($type)
					{
						case 'option':
						{
							if(isset($option['selection']['languages']['language_'.$key]) === FALSE)
								$option['selection']['languages']['language_'.$key] = FALSE;

							$html .= '<option value="language_'.$key.'" '.selected($option['selection']['languages']['language_'.$key], TRUE, FALSE).'>'.$language['native_name'].'</option>';

							break;
						}
						case 'widget':
						{
							if(!isset($rw_option['selection']['languages']['language_'.$key]) || current_user_can('manage_options'))
							{
								if(isset($instance['rw_opt']['language_'.$key]) === FALSE)
									$instance['rw_opt']['language_'.$key] = 0;

								$html .= '<option value="language_'.$key.'" '.selected($instance['rw_opt']['language_'.$key], TRUE, FALSE).'>'.apply_filters('rw_option_display_name', $language['native_name'], 'language').'</option>';
							}

							break;
						}
					}
				}

				if(($rw_option['groups'] === TRUE && $type === 'widget') || current_user_can('manage_options'))
					$html .= '</optgroup>';

				return $html;
			}
		}
	}


	/**
	 * Displays widget box
	*/
	public function display_admin_widgets_options($widget, $empty, $instance)
	{
		if(isset($instance['rw_opt']['widget_select']) === FALSE)
			$instance['rw_opt']['widget_select'] = FALSE;

		echo '
		<div class="restrict-widgets-hide-div restrict-widgets">
			<p class="restrict-widgets-display-label">'.__('Display / Hide Widget', 'restrict-widgets').'</p>
			<select name="'.$widget->get_field_name('widget_select').'" class="restrict-widgets-hide">
				<option value="yes" '.selected($instance['rw_opt']['widget_select'], TRUE, FALSE).'>'.__('Display widget on selected', 'restrict-widgets').'</option>
				<option value="no" '.selected($instance['rw_opt']['widget_select'], FALSE, FALSE).'>'.__('Hide widget on selected', 'restrict-widgets').'</option>
			</select>
		</div>
		<div class="restrict-widgets-select-div restrict-widgets">
			<select class="restrict-widgets-select" multiple="multiple" size="10" name="'.$widget->get_field_name('widget_multiselect').'[]">';

		foreach($this->options as $option => $text)
		{
			echo $this->getSelectionGroup($option, 'widget', $widget, $instance);
		}

		echo '
			</select>
		</div>';
	}


	/**
	 * Saves widget box
	*/
	public function update_admin_widgets_options($instance, $new_instance)
	{
		if(is_array($new_instance['widget_multiselect']))
		{
			$selected = $new_instance['widget_multiselect'];

			//pages
			foreach($this->pages as $page)
			{
				if(in_array('pageid_'.$page->ID, $selected))
					$instance['rw_opt']['pageid_'.$page->ID] = TRUE;
				else
					unset($instance['rw_opt']['pageid_'.$page->ID]);
			}

			//custom post types
			foreach($this->custom_post_types as $cpt)
			{
				if(in_array('cpt_'.$cpt->name, $selected))
					$instance['rw_opt']['cpt_'.$cpt->name] = TRUE;
				else
					unset($instance['rw_opt']['cpt_'.$cpt->name]);
			}

			//custom post types archives
			foreach($this->custom_post_types_archives as $cpta)
			{
				if(in_array('cpta_'.$cpta->name, $selected))
					$instance['rw_opt']['cpta_'.$cpta->name] = TRUE;
				else
					unset($instance['rw_opt']['cpta_'.$cpta->name]);
			}

			//categories
			foreach($this->categories as $category)
			{
				if(in_array('category_'.$category->cat_ID, $selected))
					$instance['rw_opt']['category_'.$category->cat_ID] = TRUE;
				else
					unset($instance['rw_opt']['category_'.$category->cat_ID]);
			}

			//taxonomies
			foreach($this->taxonomies as $taxonomy)
			{
				if(in_array('taxonomy_'.$taxonomy->name, $selected))
					$instance['rw_opt']['taxonomy_'.$taxonomy->name] = TRUE;
				else
					unset($instance['rw_opt']['taxonomy_'.$taxonomy->name]);
			}

			//others
			foreach($this->others as $key => $value)
			{
				if(in_array('others_'.$key, $selected))
					$instance['rw_opt']['others_'.$key] = TRUE;
				else
					unset($instance['rw_opt']['others_'.$key]);
			}

			//devices
			foreach($this->devices as $key => $value)
			{
				if(in_array('devices_'.$key, $selected))
					$instance['rw_opt']['devices_'.$key] = TRUE;
				else
					unset($instance['rw_opt']['devices_'.$key]);
			}

			//bbpress
			if($this->bbpress_active === TRUE)
			{
				foreach($this->bbpress as $key => $value)
				{
					if(in_array('bbpress_'.$key, $selected))
						$instance['rw_opt']['bbpress_'.$key] = TRUE;
					else
						unset($instance['rw_opt']['bbpress_'.$key]);
				}
			}

			//users
			foreach($this->users as $key => $value)
			{
				if(in_array('users_'.$key, $selected))
					$instance['rw_opt']['users_'.$key] = TRUE;
				else
					unset($instance['rw_opt']['users_'.$key]);
			}

			//languages
			if($this->languages !== FALSE)
			{
				foreach($this->languages as $key => $value)
				{
					if(in_array('language_'.$key, $selected))
						$instance['rw_opt']['language_'.$key] = TRUE;
					else
						unset($instance['rw_opt']['language_'.$key]);
				}
			}
		}
		//clear plugin-instance
		else unset($instance['rw_opt']);

		//widget_multiselect
		$instance['rw_opt']['widget_select'] = ($new_instance['widget_select'] === 'yes' ? TRUE : FALSE);

		return $instance;
	}


	private function is_widget_empty($widget, $type)
	{
		if(!empty($widget))
		{
			foreach($widget as $option => $bool)
			{
				$action = explode('_', $option, 2);

				if($type === 'main')
					$array = array('category', 'taxonomy', 'cpt', 'cpta', 'pageid', 'others', 'bbpress');
				elseif($type === 'device')
					$array = array('devices');
				elseif($type === 'lang')
					$array = array('language');
				elseif($type === 'user')
					$array = array('users');

				if(in_array($action[0], $array))
					return FALSE;
			}
		}

		return TRUE;
	}


	/**
	 * Manages front-end display of widgets
	*/
	public function display_frontend_widgets($instance, $class, $args)
	{
		return $this->restrict_widget($instance, TRUE, $args);
	}


	/**
	 * Displays or hides specific widget
	*/
	private function restrict_widget($instance, $filter = TRUE, $args = array())
	{
		global $wp_query;

		$display_lang = $display_user = $display_device = $display_main = '';
		$empty_lang = $empty_user = $empty_device = $empty_main = TRUE;
		$return = FALSE;

		$post_id = $wp_query->get_queried_object_id();
		
		$display_type = isset($instance['rw_opt']['widget_select']) ? $instance['rw_opt']['widget_select'] : FALSE;

		//languages
		if(isset($instance['rw_opt']) && $this->is_widget_empty($instance['rw_opt'], 'lang') === FALSE)
		{
			if($this->polylang_active === TRUE || $this->wpml_active === TRUE)
			{
				$empty_lang = FALSE;

				//fix for WPML
				if(function_exists('icl_object_id'))
				{
					global $sitepress;

					if(isset($sitepress))
						$post_id = icl_object_id($post_id, 'page', TRUE, $sitepress->get_default_language());
					else
						$post_id = icl_object_id($post_id, 'page', FALSE);
				}

				$found_lang = (defined('ICL_LANGUAGE_CODE') && isset($instance['rw_opt']['language_'.ICL_LANGUAGE_CODE]) ? TRUE : FALSE);

				if($display_type === TRUE)
				{
					if($found_lang === TRUE)
						$display_lang = TRUE;
					else
					{
						$return = TRUE;
						$display_lang = FALSE;
					}
				}
				else
					$display_lang = ($found_lang === TRUE ? FALSE : TRUE);
			}
			else
				$display_lang = TRUE;
		}
		else
			$display_lang = TRUE;

		//users
		if($return === FALSE)
		{
			if(isset($instance['rw_opt']) && $this->is_widget_empty($instance['rw_opt'], 'user') === FALSE)
			{
				$empty_user = FALSE;

				if(is_user_logged_in())
				{
					if(isset($instance['rw_opt']['users_logged_in'], $instance['rw_opt']['users_logged_out']) || isset($instance['rw_opt']['users_logged_in']))
						$found_user = TRUE;
					elseif(isset($instance['rw_opt']['users_logged_out']))
						$found_user = FALSE;
				}
				else
				{
					if(isset($instance['rw_opt']['users_logged_out'], $instance['rw_opt']['users_logged_in']) || isset($instance['rw_opt']['users_logged_out']))
						$found_user = TRUE;
					elseif(isset($instance['rw_opt']['users_logged_in']))
						$found_user = FALSE;
				}

				if($display_type === TRUE)
				{
					if($found_user === TRUE)
						$display_user = TRUE;
					else
					{
						$return = TRUE;
						$display_user = FALSE;
					}
				}
				else
					$display_user = ($found_user === TRUE ? FALSE : TRUE);
			}
			else
				$display_user = TRUE;
		}

		//devices
		if($return === FALSE)
		{
			if(isset($instance['rw_opt']) && $this->is_widget_empty($instance['rw_opt'], 'device') === FALSE)
			{
				$empty_device = FALSE;

				if(wp_is_mobile())
				{
					if(isset($instance['rw_opt']['devices_mobile'], $instance['rw_opt']['devices_desktop']) || isset($instance['rw_opt']['devices_mobile']))
						$found_device = TRUE;
					elseif(isset($instance['rw_opt']['devices_desktop']))
						$found_device = FALSE;
				}
				else
				{
					if(isset($instance['rw_opt']['devices_desktop'], $instance['rw_opt']['devices_mobile']) || isset($instance['rw_opt']['devices_desktop']))
						$found_device = TRUE;
					elseif(isset($instance['rw_opt']['devices_mobile']))
						$found_device = FALSE;
				}

				if($display_type === TRUE)
				{
					if($found_device === TRUE)
						$display_device = TRUE;
					else
					{
						$return = TRUE;
						$display_device = FALSE;
					}
				}
				else
					$display_device = ($found_device === TRUE ? FALSE : TRUE);
			}
			else
				$display_device = TRUE;
		}

		//rest
		if($return === FALSE)
		{
			if(isset($instance['rw_opt']) && $this->is_widget_empty($instance['rw_opt'], 'main') === FALSE)
			{
				$empty_main = FALSE;

				if(is_front_page())
				{
					$found_main = isset($instance['rw_opt']['others_front_page']) ? TRUE : FALSE;

					if(is_home() && $found_main == FALSE)
						$found_main = isset($instance['rw_opt']['others_blog_page']) ? TRUE : FALSE;
				}
				elseif(is_home())
					$found_main = isset($instance['rw_opt']['others_blog_page']) ? TRUE : FALSE;
				elseif(is_page())
					$found_main = isset($instance['rw_opt']['pageid_'.$post_id]) ? TRUE : FALSE;
				elseif(is_singular())
				{
					$found_main = isset($instance['rw_opt']['cpt_'.get_post_type($post_id)]) ? TRUE : FALSE;
					
					if(is_single() && $found_main == FALSE)
						$found_main = isset($instance['rw_opt']['others_single_post']) ? TRUE : FALSE;
				}
				elseif(is_category())
					$found_main = isset($instance['rw_opt']['category_'.get_query_var('cat')]) ? TRUE : FALSE;
				elseif(is_tax())
					$found_main = isset($instance['rw_opt']['taxonomy_'.get_query_var('taxonomy')]) ? TRUE : FALSE;
				elseif(is_404())
					$found_main = isset($instance['rw_opt']['others_404_page']) ? TRUE : FALSE;
				elseif(is_sticky())
					$found_main = isset($instance['rw_opt']['others_sticky_post']) ? TRUE : FALSE;
				elseif(is_search())
					$found_main = isset($instance['rw_opt']['others_search_page']) ? TRUE : FALSE;
				elseif(is_author())
					$found_main = isset($instance['rw_opt']['others_author_archive']) ? TRUE : FALSE;
				elseif(is_date())
					$found_main = isset($instance['rw_opt']['others_date_archive']) ? TRUE : FALSE;
				elseif(is_post_type_archive())
					$found_main = isset($instance['rw_opt']['cpta_'.get_post_type($post_id)]) ? TRUE : FALSE;
				elseif(bbp_is_search())
					$found_main = isset($instance['rw_opt']['bbpress_search']) ? TRUE : FALSE;
				elseif(bbp_is_single_user())
					$found_main = isset($instance['rw_opt']['bbpress_single_user']) ? TRUE : FALSE;
				elseif(bbp_is_topic_tag())
					$found_main = isset($instance['rw_opt']['bbpress_topic_tag']) ? TRUE : FALSE;

				$display_main = ($display_type === TRUE ? ($found_main === TRUE ? TRUE : FALSE) : ($found_main === TRUE ? FALSE : TRUE));
			}
			else
				$display_main = TRUE;
		}

		if($filter === FALSE)
			$instance = TRUE;

		if($display_type === TRUE)
			$final_return = ($display_lang === TRUE && $display_user === TRUE && $display_device === TRUE && $display_main === TRUE ? $instance : FALSE);
		else
			$final_return = (
				(
					//4
					($empty_lang === FALSE && $empty_user === FALSE && $empty_device === FALSE && $empty_main === FALSE && $display_lang === FALSE && $display_user === FALSE && $display_device === FALSE && $display_main === FALSE) ||

					//3
					($empty_lang === FALSE && $empty_user === FALSE && $empty_device === FALSE && $display_lang === FALSE && $display_user === FALSE && $display_device === FALSE) ||
					($empty_lang === FALSE && $empty_user === FALSE && $empty_main === FALSE && $display_lang === FALSE && $display_user === FALSE && $display_main === FALSE) ||
					($empty_lang === FALSE && $empty_device === FALSE && $empty_main === FALSE && $display_lang === FALSE && $display_device === FALSE && $display_main === FALSE) ||
					($empty_user === FALSE && $empty_device === FALSE && $empty_main === FALSE && $display_user === FALSE && $display_device === FALSE && $display_main === FALSE) ||

					//2
					($empty_lang === FALSE && $empty_user === FALSE && $display_lang === FALSE && $display_user === FALSE) ||
					($empty_lang === FALSE && $empty_device === FALSE && $display_lang === FALSE && $display_device === FALSE) ||
					($empty_lang === FALSE && $empty_main === FALSE && $display_lang === FALSE && $display_main === FALSE) ||
					($empty_user === FALSE && $empty_device === FALSE && $display_user === FALSE && $display_device === FALSE) ||
					($empty_user === FALSE && $empty_main === FALSE && $display_user === FALSE && $display_main === FALSE) ||
					($empty_device === FALSE && $empty_main === FALSE && $display_device === FALSE && $display_main === FALSE) ||

					//1
					($empty_lang === FALSE && $display_lang === FALSE) || 
					($empty_user === FALSE && $display_user === FALSE) || 
					($empty_device === FALSE && $display_device === FALSE) || 
					($empty_main === FALSE && $display_main === FALSE)
				)
				? FALSE : $instance
			);

		//filter true or false
		if($filter === TRUE)
			$final_return = apply_filters_ref_array('rw_display_widget', array($final_return, $instance));

		//if true return instance
		$final_return = ($final_return === FALSE) ? FALSE : $instance;

		//display: return $instance, hide: return FALSE
		return $final_return;
	}


	/**
	 * Display Appearance menu and link to widgets.php if user can manage_widgets
	*/
	public function manage_widgets_menu()
	{
		global $menu, $submenu;
		
		//if user can manage widgets but can't edit_theme_options, add widgets menu (appearance)
		if(current_user_can('manage_widgets') && !current_user_can('edit_theme_options'))
		{
			foreach($menu as $menu_key => $menu_values)
			{
				if(isset($menu_values[5]) && $menu_values[5] === 'menu-appearance')
				{
					//if appearance menu not exists
					if(empty($submenu[$menu_values[2]]))
					{
						$menu[$menu_key][1] = 'manage_widgets';
						$menu[$menu_key][2] = 'widgets.php';
					}
					else
					//if appearance menu exists
					{
						foreach($submenu[$menu_values[2]] as $submenu_key => $submenu_values)
						{
							$submenu[$menu_values[2]][7] = array(__('Widgets'), 'manage_widgets', 'widgets.php');
						}
					}
				}
			}
		}
		//if user can't manage widgets but can edit_theme_options, remove widgets from menu
		elseif(!current_user_can('manage_widgets') && current_user_can('edit_theme_options'))
		{
			foreach($menu as $menu_key => $menu_values)
			{
				if(isset($menu_values[5]) && $menu_values[5] === 'menu-appearance')
				{
					foreach($submenu[$menu_values[2]] as $submenu_key => $submenu_values)
					{
						if(isset($submenu_values[2]) && $submenu_values[2] == 'widgets.php')
						{
							$submenu[$menu_values[2]][$submenu_key][1] = 'manage_widgets';
						}
					}
				}
			}
		}
	}


	/**
	 * Fix for Appearance menu items on widgets.php if user can manage_widgets but can't edit theme options
	*/
	public function manage_widgets_menu_fix()
	{
		global $menu, $submenu;

		foreach($menu as $menu_key => $menu_values)
		{
			if(isset($menu_values[5]) && $menu_values[5] === 'menu-appearance')
			{
				//if appearance menu not exists
				if(empty($submenu[$menu_values[2]]))
				{
					$menu[$menu_key][1] = 'manage_widgets';
					$menu[$menu_key][2] = 'widgets.php';
				}
				else
				//if appearance menu exists
				{
					$submenu[$menu_values[2]][7] = array(__('Widgets'), 'manage_widgets', 'widgets.php');

					//remove menus with edit_theme_options capability
					foreach($submenu[$menu_values[2]] as $submenu_key => $submenu_values)
					{
						if($submenu_values[1] == 'edit_theme_options')
							unset($submenu[$menu_values[2]][$submenu_key]);
					}

					//fix if appearance menu item is only 1, remove submenu
					if(count($submenu[$menu_values[2]]) < 2)
					{
						unset($submenu[$menu_values[2]][7]);
						$menu[$menu_key][1] = 'manage_widgets';
						$menu[$menu_key][2] = 'widgets.php';
					}
				}
			}
		}
	}


	/**
	 * Manage Widgets Capability
	*/
	public function manage_widgets_cap($allcaps, $cap, $args)
	{
		global $pagenow;

		if($pagenow === 'widgets.php' || (defined('DOING_AJAX') && DOING_AJAX))
		{
			//break if we're not asking to edit widgets
			if(('edit_theme_options' != $args[0]) || empty($allcaps['manage_widgets']))
				return $allcaps;
			else
			{
				//if user can't edit_theme_options but can manage_widgets
				if(empty($allcaps['edit_theme_options']))
				{
					//menu fix
					add_action('admin_menu', array(&$this, 'manage_widgets_menu_fix'), 999);

					//add cap to edit widgets
					$allcaps['edit_theme_options'] = TRUE;
				}
			}
		}

		return $allcaps;
	}
}
?>