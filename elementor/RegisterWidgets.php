<?php

/*
Elementor Categories Group
*/
function register_eyeon_elementor_categories( $elements_manager ) {
  $elements_manager->add_category(
    'eyeon',
    [
      'title' => esc_html__( 'EyeOn Portal', EYEON_NAMESPACE ),
      'icon' => 'fa fa-plug',
    ]
  );
}
add_action( 'elementor/elements/categories_registered', 'register_eyeon_elementor_categories' );

/*
Include Scripts & Styles
*/
function eyeon_elementor_scripts() {
  // mcd_include_css('fontawesome', 'assets/plugins/fontawesome/css/fontawesome-all.min.css');
  wp_register_style( 'eyeon-elementor-style', mcd_version_url( 'assets/css/elementor.min.css' ) );

  wp_register_script( 'eyeon-moment', mcd_version_url( 'assets/plugins/calendar/moment.min.js' ) );
  wp_register_script( 'eyeon-elementor-utils', mcd_version_url( 'elementor/js/utils.js' ) );
  wp_register_script( 'eyeon-elementor-center-website', mcd_version_url( 'elementor/js/center-website.js' ) );

  wp_register_script( 'eyeon-owl-carousel', mcd_version_url( 'assets/plugins/owl/owl.carousel.min.js' ) );
  wp_register_style( 'eyeon-owl-carousel', mcd_version_url( 'assets/plugins/owl/assets/owl.carousel.min.css' ) );
  wp_register_style( 'eyeon-owl-carousel-theme', mcd_version_url( 'assets/plugins/owl/assets/owl.theme.default.min.css' ) );
  
  wp_register_script( 'eyeon-rrule', mcd_version_url( 'assets/plugins/rrule/rrule.min.js' ), array('jquery'), null, array('strategy'  => 'defer'));
  
  wp_register_script( 'eyeon-date-fns', mcd_version_url( 'assets/plugins/date-fns.min.js' ) );

  wp_register_script( 'eyeon-map', mcd_version_url( 'assets/map-releases/'.THREEJS_MAP_VERSION.'/main.js' ), array(), null, true );
  wp_register_style( 'eyeon-map', mcd_version_url( 'assets/map-releases/'.THREEJS_MAP_VERSION.'/main.css' ) );
}
add_action( 'wp_enqueue_scripts', 'eyeon_elementor_scripts' );

/*
Scripts & Styles for Elementor widget editor
*/
function eyeon_elementor_editor_scripts() {
  global $mcd_settings;

  // Retailers Categories Select2
  wp_register_script( 'eyeon-retailers-categories-script', mcd_version_url( 'elementor/controls/retailer-categories.js' ), array('jquery') );
  $categoriesCustomData = array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'api_endpoint' => MCD_API_STORES.'/categories',
    'nonce' => wp_create_nonce('eyeon_api_nonce')
  );
  wp_localize_script('eyeon-retailers-categories-script', 'categoriesCustomData', $categoriesCustomData);
  wp_enqueue_script( 'eyeon-retailers-categories-script' );

  // Retailers Tags Select2
  wp_register_script( 'eyeon-retailers-tags-script', mcd_version_url( 'elementor/controls/retailer-tags.js' ) );
  $tagsCustomData = array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'api_endpoint' => MCD_API_STORES.'/tags',
    'nonce' => wp_create_nonce('eyeon_api_nonce')
  );
  wp_localize_script('eyeon-retailers-tags-script', 'tagsCustomData', $tagsCustomData);
  wp_enqueue_script( 'eyeon-retailers-tags-script' );

  // Events Categories Select2
  // wp_register_script( 'eyeon-events-categories-script', mcd_version_url( 'elementor/controls/event-categories.js' ) );
  // $categoriesCustomData = array(
  //   'center_id' => $mcd_settings['center_id'],
  //   'api_endpoint' => MCD_API_EVENTS.'/categories'
  // );
  // wp_localize_script('eyeon-events-categories-script', 'categoriesCustomData', $categoriesCustomData);
  // wp_enqueue_script( 'eyeon-events-categories-script' );
}
add_action('elementor/editor/after_enqueue_scripts', 'eyeon_elementor_editor_scripts');

/*
Register Elementor Widgets
*/
function register_eyeon_widgets( $widgets_manager ) {
  require_once plugin_dir_path( __FILE__).'widgets/stores/index.php';
  $widgets_manager->register( new \EyeOn_Stores_Widget() );

  require_once plugin_dir_path( __FILE__).'widgets/events/index.php';
  $widgets_manager->register( new \EyeOn_Events_Widget() );

  require_once plugin_dir_path( __FILE__).'widgets/deals/index.php';
  $widgets_manager->register( new \EyeOn_Deals_Widget() );

  require_once plugin_dir_path( __FILE__).'widgets/careers/index.php';
  $widgets_manager->register( new \EyeOn_Careers_Widget() );

  require_once plugin_dir_path( __FILE__).'widgets/map/index.php';
  $widgets_manager->register( new \EyeOn_Map_Widget() );

  require_once plugin_dir_path( __FILE__).'widgets/news/index.php';
  $widgets_manager->register( new \EyeOn_News_Widget() );

  require_once plugin_dir_path( __FILE__).'widgets/center-hours/index.php';
  $widgets_manager->register( new \EyeOn_Center_Hours_Widget() );

  require_once plugin_dir_path( __FILE__).'widgets/alert-bar/index.php';
  $widgets_manager->register( new \EyeOn_Alert_Bar_Widget() );

  require_once plugin_dir_path( __FILE__).'widgets/banners/index.php';
  $widgets_manager->register( new \EyeOn_Banner_Widget() );

  require_once plugin_dir_path( __FILE__).'widgets/search/index.php';
  $widgets_manager->register( new \EyeOn_Search_Widget() );
}  
add_action('elementor/widgets/register', 'register_eyeon_widgets');

