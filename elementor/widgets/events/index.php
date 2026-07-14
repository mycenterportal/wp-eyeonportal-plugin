<?php

class EyeOn_Events_Widget extends \Elementor\Widget_Base {
  public function get_name() {
      return 'eyeon_events_widget';
  }

  public function get_title() {
      return __( 'EyeOn Events', EYEON_NAMESPACE );
  }

  public function get_icon() {
      return 'eicon-calendar';
  }

  public function get_categories() {
      return ['eyeon'];
  }

  public function get_script_depends() {
		return [
      'eyeon-rrule',
      'eyeon-moment',
      'eyeon-elementor-utils',
      'eyeon-owl-carousel',
    ];
	}
  
	public function get_style_depends() {
    return [
      'eyeon-owl-carousel',
      'eyeon-owl-carousel-theme',
      'eyeon-elementor-style'
    ];
	}

  private function get_categories_from_api() {
    $response = mcd_api_data(MCD_API_EVENTS.'/categories');
    $eventCategoriesResp = $response['data'] ?? null;
    $options = array(
      0 => 'All',
    );
    if ( is_array( $eventCategoriesResp ) && ! empty( $eventCategoriesResp['items'] ) ) {
      foreach( $eventCategoriesResp['items'] as $category ) {
        $options[$category['id']] = $category['title'];
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
        'label' => esc_html__( 'Settings', EYEON_NAMESPACE ),
        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
      ]
    );

    $this->add_control(
      'view_mode',
      [
        'label' => __( 'View Mode', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SELECT,
        'default' => 'grid',
        'options' => [
          'grid' => __( 'Grid', EYEON_NAMESPACE ),
          'carousel' => __( 'Carousel', EYEON_NAMESPACE ),
        ],
      ]
    );

    $this->add_control(
      'fetch_all',
      [
        'label' => esc_html__( 'Fetch All', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SWITCHER,
        'label_on' => esc_html__( 'Yes', EYEON_NAMESPACE ),
        'label_off' => esc_html__( 'No', EYEON_NAMESPACE ),
        'return_value' => 'yes',
        'default' => 'yes',
      ]
    );

    $this->add_control(
      'fetch_limit',
      [
        'type' => \Elementor\Controls_Manager::NUMBER,
        'label' => esc_html__( 'Custom Limit', EYEON_NAMESPACE ),
        'placeholder' => '0',
        'min' => 1,
        'max' => 100,
        'step' => 1,
        'default' => 8,
        'condition' => [
          'fetch_all' => '',
        ],
      ]
    );

    $this->add_responsive_control(
      'items_per_row',
      [
        'type' => \Elementor\Controls_Manager::NUMBER,
        'label' => esc_html__( 'Items per Row', EYEON_NAMESPACE ),
        'min' => 1,
        'max' => 10,
        'step' => 1,
        'default' => 4,
        'render_type' => 'ui',
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .events-list' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
        ],
        'condition' => [
          'view_mode' => 'grid',
        ],
      ]
    );

    $this->add_control(
      'categories_filters',
      [
        'label' => __( 'Categories Filters', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SWITCHER,
        'label_on' => __( 'Show', EYEON_NAMESPACE ),
        'label_off' => __( 'Hide', EYEON_NAMESPACE ),
        'return_value' => 'show',
        'default' => 'show',
        'condition' => [
          'view_mode' => 'grid',
        ],
      ]
    );

    $this->add_control(
			'hr_1',
			[
				'type' => \Elementor\Controls_Manager::DIVIDER,
			]
		);

    $this->add_control(
      'event_title',
      [
        'label' => esc_html__( 'Title', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SWITCHER,
        'label_on' => esc_html__( 'Show', EYEON_NAMESPACE ),
        'label_off' => esc_html__( 'Hide', EYEON_NAMESPACE ),
        'return_value' => 'show',
        'default' => 'show',
      ]
    );

    $this->add_control(
      'only_static_events',
      [
        'label' => esc_html__( 'Only Static Events', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SWITCHER,
        'label_on' => esc_html__( 'Yes', EYEON_NAMESPACE ),
        'label_off' => esc_html__( 'No', EYEON_NAMESPACE ),
        'return_value' => 'yes',
        'default' => '',
      ]
    );

    $this->add_control(
      'external_event_new_tab',
      [
        'label' => esc_html__( 'External Events in New Tab', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SWITCHER,
        'label_on' => esc_html__( 'Yes', EYEON_NAMESPACE ),
        'label_off' => esc_html__( 'No', EYEON_NAMESPACE ),
        'return_value' => 'yes',
        'default' => 'yes',
      ]
    );

    $this->add_control(
			'hr_2',
			[
				'type' => \Elementor\Controls_Manager::DIVIDER,
			]  
		);

    // $this->add_control(
    //   'event_categories',
    //   [
    //     'label' => __( 'Categories', EYEON_NAMESPACE ),
    //     'type' => \Elementor\Controls_Manager::SELECT2,
    //     'options' => [],
    //     'default' => [],
    //     'multiple' => true,
    //     'label_block' => true,
    //     'frontend_available' => true,
    //   ]
    // );

    $this->add_control(
      'event_category',
      [
        'label' => __( 'Filter by Category', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SELECT,
        'options' => $this->get_categories_from_api(),
        'default' => 0,
        'label_block' => true,
        'frontend_available' => true,
      ]
    );

    $this->end_controls_section();

    include(MCD_PLUGIN_PATH.'elementor/widgets/common/carousel/controls.php');

    // ================================================================
    // FILTERS
    // ================================================================
    
    $this->start_controls_section(
      'categories_style_settings',
      [
        'label' => __( 'Categories', EYEON_NAMESPACE ),
        'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        'condition' => [
          'view_mode' => 'grid',
          'categories_filters' => 'show',
        ],
      ]
    );

    $this->add_responsive_control(
      'categories_margin_bottom',
      [
        'label' => __( 'Margin Bottom', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SLIDER,
        'range' => [
          'px' => [
            'min' => 0,
            'max' => 60,
            'step' => 1
          ],
        ],
        'size_units' => ['px', '%'],
        'default' => [
          'unit' => 'px',
          'size' => 40,
        ],
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .categories' => 'margin-bottom: {{SIZE}}{{UNIT}};',
        ],
      ]
    );

    $this->add_responsive_control(
      'categories_item_padding',
      [
        'label' => __( 'Padding', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::DIMENSIONS,
        'size_units' => [ 'px', '%' ],
        'default' => [
          'top' => '5',
          'right' => '18',
          'bottom' => '5',
          'left' => '18',
          'unit' => 'px',
          'isLinked' => false,
        ],
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .categories ul li' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
        ],
      ]
    );

    $this->add_responsive_control(
      'categories_item_bottom_width',
      [
        'label' => __( 'Border Width', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SLIDER,
        'range' => [
          'px' => [
            'min' => 0,
            'max' => 5,
            'step' => 1
          ],
        ],
        'size_units' => ['px', '%'],
        'default' => [
          'unit' => 'px',
          'size' => 0,
        ],
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .categories ul li' => 'border-bottom-width: {{SIZE}}{{UNIT}}; border-style: solid;',
        ],
      ]
    );

    $this->start_controls_tabs(
			'item_style_tabs'
		);

      $this->start_controls_tab(
        'item_style_normal_tab',
        [
          'label' => esc_html__( 'Normal', EYEON_NAMESPACE ),
        ]
      );

        $this->add_group_control(
          \Elementor\Group_Control_Typography::get_type(),
          [
            'name' => 'item_style_normal_typography',
            'selector' => '{{WRAPPER}} .eyeon-events .categories ul li',
          ]
        );

        $this->add_control(
          'item_style_normal_color',
          [
            'label' => __( 'Text Color', EYEON_NAMESPACE ),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
              '{{WRAPPER}} .eyeon-events .categories ul li' => 'color: {{VALUE}}',
            ],
            'default' => '#101921',
          ]
        );

        $this->add_control(
          'item_style_normal_border_color',
          [
            'label' => __( 'Border Color', EYEON_NAMESPACE ),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
              '{{WRAPPER}} .eyeon-events .categories ul li' => 'border-color: {{VALUE}}',
            ],
            'default' => '#DDDDDD',
          ]
        );

        $this->add_control(
          'item_style_normal_bg_color',
          [
            'label' => __( 'Background Color', EYEON_NAMESPACE ),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
              '{{WRAPPER}} .eyeon-events .categories ul li' => 'background-color: {{VALUE}}',
            ],
            'default' => '#FFFFFF',
          ]
        );

      $this->end_controls_tab();

      $this->start_controls_tab(
        'item_style_active_tab',
        [
          'label' => esc_html__( 'Active', EYEON_NAMESPACE ),
        ]
      );

        $this->add_group_control(
          \Elementor\Group_Control_Typography::get_type(),
          [
            'name' => 'item_style_active_typography',
            'selector' => '{{WRAPPER}} .eyeon-events .categories ul li.active',
          ]
        );

        $this->add_control(
          'item_style_active_color',
          [
            'label' => __( 'Text Color', EYEON_NAMESPACE ),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
              '{{WRAPPER}} .eyeon-events .categories ul li.active' => 'color: {{VALUE}}',
            ],
            'default' => '#101921',
          ]
        );

        $this->add_control(
          'item_style_active_border_color',
          [
            'label' => __( 'Border Color', EYEON_NAMESPACE ),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
              '{{WRAPPER}} .eyeon-events .categories ul li.active' => 'border-color: {{VALUE}}',
            ],
            'default' => '#888888',
          ]
        );

        $this->add_control(
          'item_style_active_bg_color',
          [
            'label' => __( 'Background Color', EYEON_NAMESPACE ),
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
              '{{WRAPPER}} .eyeon-events .categories ul li.active' => 'background-color: {{VALUE}}',
            ],
            'default' => '#49caee',
          ]
        );

      $this->end_controls_tab();

    $this->end_controls_section();

    // ================================================================
    // GRID SETTINGS
    // ================================================================

    $this->start_controls_section(
      'grid_style_settings',
      [
        'label' => esc_html__( 'Grid', EYEON_NAMESPACE ),
        'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        'condition' => [
          'view_mode' => 'grid',
        ],
      ]
    );

    $this->add_responsive_control(
      'grid_gap',
      [
        'label' => esc_html__( 'Spacing', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SLIDER,
        'range' => [
          'px' => [
            'min' => 0,
            'max' => 60,
            'step' => 1,
          ],
        ],
        'size_units' => ['px'],
        'default' => [
          'unit' => 'px',
          'size' => 20,
        ],
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .events-list' => 'grid-gap: {{SIZE}}{{UNIT}};',
        ],
      ]
    );

    $this->end_controls_section();

    $this->start_controls_section(
      'event_title_style_settings',
      [
        'label' => esc_html__( 'Title', EYEON_NAMESPACE ),
        'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        'condition' => [
          'event_title' => 'show',
        ],
      ]
    );

    $this->add_group_control(
      \Elementor\Group_Control_Typography::get_type(),
      [
        'name' => 'event_title_typography',
        'selector' => '{{WRAPPER}} .eyeon-events .events-list .event .event-title',
      ]
    );

    $this->add_control(
			'event_title_text_color',
			[
				'label' => esc_html__( 'Text Color', EYEON_NAMESPACE ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .eyeon-events .events-list .event .event-title' => 'color: {{VALUE}}',
				],
			]
		);

    $this->add_responsive_control(
      'event_title_spacing',
      [
        'label' => esc_html__( 'Spacing', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SLIDER,
        'range' => [
          'px' => [
            'min' => 0,
            'max' => 20,
            'step' => 1,
          ],
        ],
        'size_units' => ['px'],
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .events-list .event .event-title' => 'margin-top: {{SIZE}}{{UNIT}};',
        ],
      ]
    );

    $this->end_controls_section();

    $this->start_controls_section(
      'event_metadata_style_settings',
      [
        'label' => esc_html__( 'Date & Time', EYEON_NAMESPACE ),
        'tab' => \Elementor\Controls_Manager::TAB_STYLE
      ]
    );

    $this->add_group_control(
      \Elementor\Group_Control_Typography::get_type(),
      [
        'name' => 'event_metadata_typography',
        'selector' => '{{WRAPPER}} .eyeon-events .events-list .event .metadata',
      ]
    );

    $this->add_control(
			'event_metadata_icon_text_color',
			[
				'label' => esc_html__( 'Icon Color', EYEON_NAMESPACE ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .eyeon-events .events-list .event .metadata i.far' => 'color: {{VALUE}}',
				],
			]
		);

    $this->add_responsive_control(
      'event_metadata_icon_size',
      [
        'label' => esc_html__( 'Icon Size', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SLIDER,
        'range' => [
          'px' => [
            'min' => 0,
            'max' => 32,
            'step' => 1,
          ],
        ],
        'size_units' => ['px'],
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .events-list .event .metadata i.far' => 'font-size: {{SIZE}}{{UNIT}};',
        ],
      ]
    );

    $this->add_responsive_control(
      'event_metadata_margin_top',
      [
        'label' => esc_html__( 'Margin Top', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SLIDER,
        'range' => [
          'px' => [
            'min' => 0,
            'max' => 20,
            'step' => 1,
          ],
        ],
        'size_units' => ['px'],
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .events-list .event .metadata' => 'margin-top: {{SIZE}}{{UNIT}};',
        ],
      ]
    );

    $this->add_responsive_control(
			'event_metadata_direction',
			[
				'label' => esc_html__( 'Direction', EYEON_NAMESPACE ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'row' => [
						'title' => esc_html__( 'Row - horizontal', EYEON_NAMESPACE ),
						'icon' => 'eicon-arrow-right',
					],
					'column' => [
						'title' => esc_html__( 'Column - vertical', EYEON_NAMESPACE ),
						'icon' => 'eicon-arrow-down',
					],
				],
				'default' => 'row',
				'toggle' => true,
				'selectors' => [
					'{{WRAPPER}} .eyeon-events .events-list .event .metadata' => 'flex-direction: {{VALUE}};',
				],
			]
		);

    $this->add_responsive_control(
      'event_metadata_flex_spacing',
      [
        'label' => esc_html__( 'Spacing', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::SLIDER,
        'range' => [
          'px' => [
            'min' => 0,
            'max' => 20,
            'step' => 1,
          ],
        ],
        'size_units' => ['px'],
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .events-list .event .metadata' => 'gap: {{SIZE}}{{UNIT}};',
        ],
      ]
    );

    $this->end_controls_section();

    // ================================================================
    // NO RESULTS FOUND - STYLE
    // ================================================================

    $this->start_controls_section(
      'no_results_found_style_settings',
      [
        'label' => __( 'No Results Found', EYEON_NAMESPACE ),
        'tab' => \Elementor\Controls_Manager::TAB_STYLE,
      ]
    );

    $this->add_control(
      'no_results_found_text',
      [
        'label' => __( 'Text', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::TEXT,
        'default' => 'More Events Coming Soon!',
        'label_block' => true,
        'frontend_available' => true,
      ]
    );

    $this->add_control(
      'no_results_found_align',
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
        'default' => 'center',
        'toggle' => true,
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .no-items-found' => 'text-align: {{VALUE}};',
        ],
      ]
    );

    $this->add_group_control(
      \Elementor\Group_Control_Typography::get_type(),
      [
        'name' => 'no_results_found_typography',
        'selector' => '{{WRAPPER}} .eyeon-events .no-items-found',
      ]
    );

    $this->add_control(
      'no_results_found_color',
      [
        'label' => __( 'Text Color', EYEON_NAMESPACE ),
        'type' => \Elementor\Controls_Manager::COLOR,
        'selectors' => [
          '{{WRAPPER}} .eyeon-events .no-items-found' => 'color: {{VALUE}}',
        ],
      ]
    );

    $this->end_controls_section();
  }

}
