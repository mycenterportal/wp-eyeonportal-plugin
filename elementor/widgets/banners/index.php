<?php

class EyeOn_Banner_Widget extends \Elementor\Widget_Base {
  public function get_name() {
      return 'eyeon_banner_widget';
  }

  public function get_title() {
      return __( 'EyeOn Banner', EYEON_NAMESPACE );
  }

  public function get_icon() {
      return 'eicon-slider-device';
  }

  public function get_categories() {
      return ['eyeon'];
  }

  public function get_script_depends() {
    return [
      'eyeon-owl-carousel',
      'eyeon-elementor-center-website',
    ];
  }
  
  public function get_style_depends() {
    return [
      'eyeon-owl-carousel',
      'eyeon-owl-carousel-theme',
      'eyeon-elementor-style'
    ];
  }

  private function get_banners_from_api() {
    $response = mcd_api_data(MCD_API_BANNERS);
    $bannersResp = $response['data'];
    $options = array();
    if ( is_array( $bannersResp ) && ! empty( $bannersResp['items'] ) ) {
      foreach( $bannersResp['items'] as $banner ) {
        $options[$banner['id']] = $banner['name'];
      }
    }
    return $options;
  }

  protected function render() {
    global $mcd_settings;
    include dirname(__FILE__) . '/render.php';
  }

  protected function register_controls() {

    $this->start_controls_section(
      'content_settings',
      [
        'label' => __( 'Settings', EYEON_NAMESPACE ),
        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
      ]
    );

    $this->add_control(
      'banner_id',
      [
        'label' => __( 'Select Banner', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SELECT,
        'options' => $this->get_banners_from_api(),
        'default' => 0,
        'label_block' => false,
      ]
    );

    $this->end_controls_section();

  }

}
