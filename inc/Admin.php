<?php
$mcd_settings = get_option(MCD_REDUX_OPT_NAME);

function on_mcd_plugin_page() {
	return isset($_GET['page']) && $_GET['page']===MCD_PLUGIN_NAME;
}

function mcd_page_widths() {
	return array(
		'default' => 'Default',
		'fullwidth' => 'Full Width',
	);
}

function mcd_pages_list() {
	$pages = array();
	$args_posts = array(
		'post_type' => 'page',
		'post_status' => 'publish',
		'posts_per_page' => -1,
	);
	$loop_pages = new WP_Query( $args_posts );

	foreach ($loop_pages->posts as $key => $page) {
		$pages[$page->ID] = $page->post_title;
	}

	return $pages;
}

// add admin stylesheets and scripts here
function mcd_admin_assets() {
	if ( ! on_mcd_plugin_page() ) {
		return;
	}

	mcd_include_css( 'admin-style', 'inc/admin/assets/css/style.css' );
	mcd_include_js( 'admin-general-settings', 'inc/admin/assets/js/general-settings.js', true );
}
add_action( 'admin_enqueue_scripts', 'mcd_admin_assets' );

// show admin notice error to install Redux Framework plugin first
function general_admin_notice() {
	if( !is_plugin_active('redux-framework/redux-framework.php') ) {
		echo '<div class="notice notice-error">
			<p><strong>My Center Portal</strong>: Please install <a href="https://wordpress.org/plugins/redux-framework/" target="_blank">Redux Framework</a> plugin in order to see <a href="https://wordpress.org/plugins/my-center-deals" target="_blank">My Center Portal</a> settings.</p>
		</div>';
	}
}
// add_action('admin_notices', 'general_admin_notice');

// REDUX FRAMEWORK OPTIONS
require MCD_PLUGIN_PATH.'inc/redux-core/framework.php';
require MCD_PLUGIN_PATH . 'inc/admin/options.php';

