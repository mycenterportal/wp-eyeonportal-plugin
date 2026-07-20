<?php

class EyeOn_Alert_Bar_Widget extends \Elementor\Widget_Base {
  public function get_name() {
    return 'eyeon_alert_bar_widget';
  }

  public function get_title() {
    return __( 'EyeOn Alert Bar', EYEON_NAMESPACE );
  }

  public function get_icon() {
    return 'eicon-alert';
  }

  public function get_categories() {
    return ['eyeon'];
  }

  public function get_script_depends() {
    return [
      'eyeon-elementor-utils',
    ];
  }

  public function get_style_depends() {
    return [
      'eyeon-elementor-style',
    ];
  }

  protected function render() {
    global $mcd_settings;
    $is_edit_mode = \Elementor\Plugin::$instance->editor->is_edit_mode();
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
      'content_info',
      [
        'type' => \Elementor\Controls_Manager::RAW_HTML,
        'raw' => __( 'Alert bar content and schedule are managed in the EyeOn Portal under Center Info → Alert Bar. The bar appears on the live site only when today falls within the configured start and end dates.', EYEON_NAMESPACE ),
        'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
      ]
    );

    $this->end_controls_section();

    $this->start_controls_section(
      'style_settings',
      [
        'label' => __( 'Alert Bar', EYEON_NAMESPACE ),
        'tab' => \Elementor\Controls_Manager::TAB_STYLE,
      ]
    );

    $this->add_group_control(
      \Elementor\Group_Control_Typography::get_type(),
      [
        'name' => 'alert_bar_typography',
        'selector' => '{{WRAPPER}} .eyeon-alert-bar .alert-bar-message, {{WRAPPER}} .eyeon-alert-bar .alert-bar-message__text',
      ]
    );

    $this->add_responsive_control(
      'alert_bar_text_align',
      [
        'label' => __( 'Alignment', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::CHOOSE,
        'options' => [
          'left' => [
            'title' => __( 'Left', EYEON_NAMESPACE ),
            'icon' => 'eicon-text-align-left',
          ],
          'center' => [
            'title' => __( 'Center', EYEON_NAMESPACE ),
            'icon' => 'eicon-text-align-center',
          ],
          'right' => [
            'title' => __( 'Right', EYEON_NAMESPACE ),
            'icon' => 'eicon-text-align-right',
          ],
        ],
        'default' => 'left',
        'toggle' => true,
        'selectors' => [
          '{{WRAPPER}} .eyeon-alert-bar .alert-bar-message, {{WRAPPER}} .eyeon-alert-bar .alert-bar-message__text' => 'text-align: {{VALUE}};',
        ],
      ]
    );

    $this->add_control(
      'alert_bar_text_color',
      [
        'label' => __( 'Text Color', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::COLOR,
        'selectors' => [
          '{{WRAPPER}} .eyeon-alert-bar .alert-bar-message, {{WRAPPER}} .eyeon-alert-bar .alert-bar-message__text' => 'color: {{VALUE}}',
          '{{WRAPPER}} .eyeon-alert-bar .alert-bar-message a, {{WRAPPER}} .eyeon-alert-bar .alert-bar-message__text a' => 'color: {{VALUE}}',
        ],
      ]
    );

    $this->end_controls_section();
  }
}
