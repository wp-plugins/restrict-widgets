# Restrict Widgets
Contributors: dfactory, [Andy King](https://github.com/treminaor)

Tags: widget, widgets, widget-only, cms, conditional tags, conditional, widget logic, widget context, restrict, manage, management, capability, capabilities, sidebar, sidebars, user, permission, permissions

Requires at least: 3.8

Tested up to: 5.3.0

Stable tag: 1.4.0

License: MIT License

License URI: http://opensource.org/licenses/MIT

All in one widgets and sidebars management in WordPress. Allows you to hide or display widgets on specified pages and restrict access for users.

## Description

[Restrict Widgets](http://www.dfactory.eu/plugins/restrict-widgets/) is all in one solution for widget management in WordPress. It lets you easily control the pages that each widget will appear on and avoid creating multiple sidebars and duplicating widgets. You can also set who can manage widgets, which sidebars and widgets will be available to selected users, which widget options will be available and how it will be displayed.

By default, Hide widget on selected is enabled with no options selected, so all current widgets will continue to display on all pages.

For more information, check out plugin page at [dFactory](http://www.dfactory.eu/) or plugin [support forum](http://www.dfactory.eu/support/forum/restrict-widgets/).

### Features include:

* Hide or display each widget on selected pages, posts, categories, custom taxonomies, custom post types, single posts, archives, special pages, for logged in or logged out users, current language, mobile device and so on
* Select which user roles are restricted to manage widgets
* Select which sidebars will be restricted to admins only
* Select which widgets will be restricted to admins only
* Select which widget options will be restricted to admins only
* Choose to display or not widget options as groups
* Option to modify the is_active_sidebar() function to use Restrict Widgets display settings
* Multisite compatible
* WPML compatible
* Polylang compatible
* .pot file for translations included

### Translations:

* Chinese - by Changmeng Hu
* Czech - by Martin Kucera
* German - by [Angelika Reisiger](http://apart-webdesign.de/)
* Hebrew - by [Ahrale Shrem](http://atar4u.com/)
* Italian - by [Davide Pante](http://sododesign.it/)
* Polish - by Bartosz Arendt


## Installation

1. Install Restrict Widgets either via the WordPress.org plugin directory, or by uploading the files to your server
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the Widgets menu and set your desired widgets options.

## Frequently Asked Questions

No questions yet.

## Changelog

### 1.4.1
* Fixed: Dropdown fastselects were showing twice on newly created widgets. The bug was not present after an ajaxrefresh or on an already-existing widget instance.

### 1.4.0
* New: jQuery dynamic multiselect has been changed to [jQuery Fastselect](https://dbrekalo.github.io/fastselect)
* New: Exclusion option has been added to widget options. For example if you want a widget to hide on all "Single Pages" but you want a specific page slug to show the widget, you can add the page via the new "Excluded" choicebox in the widget settings.

### 1.3.1
* New: Categories and Tags available in Taxonomy selection
* Tweak: Removed local translation files in favor of WP repository translations.

### 1.3.0
* New: Customizer support
* New: Dedicated options page
* Tweak: Switched from Chosen.js to Select2 script
* Tweak: General code cleanup

### 1.2.9
* New: Italian translation by [Davide Pante](http://sododesign.it/)

### 1.2.8
* New: German translation by [Angelika Reisiger](http://apart-webdesign.de/)

### 1.2.7
* Fix: Enable display/hide on any single page
* Tweak: Change post type archive detection from get_post_type() to get_query_var

### 1.2.6.1
* Fix: Page hide/display settings broken after 1.2.6 update

### 1.2.6
* New: Hebrew translation by [Ahrale Shrem](http://atar4u.com/)

### 1.2.5
* Tweak: UI improvements
* Tweak: jQuery chosen updated to 1.2.0

### 1.2.4
* Tweak: Widget options interface adjustments
* Tweak: Confirmed WP 4.0 compatibility

### 1.2.3
* Fix: Language options not accessible for WPML and Polylang

### 1.2.2
* Tweak: UI fixes for WP 3.8

### 1.2.1
* Fix: tags not working properly
* Tweak: UI fixes for WP 3.8

### 1.2.0
* New: Multisite support
* New: Option to hide / show widget depending on the device (mobile or not)
* New: bbPress specific options for hide / show widget
* Fix: get_class error on widgets not using Widgets API
* Fix: Logged-in or out users option not working properly

### 1.1.4
* New: Introducing rw_option_display_name filter hook
* Tweak: WPML not displaying page names in default site language

### 1.1.3
* New: Czech translation by Martin Kucera

### 1.1.2
* New: Option to modify the is_active_sidebar() function to use Restrict Widgets display settings
* Fix: Compatibility fix for WordPress 3.6

### 1.1.1
* Tweak: Optimized rw_display_widget() filter
* New: Chinese translation by Changmeng Hu

### 1.1.0
* Fix: Show on selected not working in some cases
* Fix: Restricting on user_logged_in/out
* Fix: Restricting for Polylang and WPML languages
* New: Introducing filter rw_display_widget()

### 1.0.1
* Fix: Widget options not saving if no options selected 

### 1.0
Initial release
