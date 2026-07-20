<?php defined( 'ABSPATH' ) || exit;

$center    = eyeon_get_center();
$api_token = isset( $mcd_settings['api_access_token'] ) ? $mcd_settings['api_access_token'] : '';

$center_desc = '';
if ( $center && ! empty( $center['id'] ) && ! empty( $center['name'] ) ) {
	$center_desc = sprintf(
		'#%1$d - %2$s',
		(int) $center['id'],
		esc_html( $center['name'] )
	);

	$environment_badge = eyeon_get_api_token_environment_badge_html( $api_token );
	if ( $environment_badge ) {
		$center_desc .= ' ' . $environment_badge;
	}
}

Redux::set_section(
	$opt_name,
	array(
		'title' => __( 'General Settings', EYEON_NAMESPACE ),
		'id' => 'general_settings',
		'icon' => 'el el-home',
		'fields' => array(
			array(
				'id' => 'api_access_token',
				'type' => 'password',
				'title' => __( 'API Access Token', EYEON_NAMESPACE ),
				'default' => $api_token,
				'desc' => $center_desc,
				'class' => 'eyeon-api-token-input',
				'ajax_save' => false,
			),
			array(
				'id' => 'default_page_width',
				'type' => 'text',
				'title' => __( 'Default Page Width', 'redux-framework-demo' ),
				'subtitle' => __( 'Max container width', 'redux-framework-demo' ),
				'default' => isset($mcd_settings['default_page_width']) ? $mcd_settings['default_page_width'] : 1200,
			),
			array(
				'id' => 'accent_color',
				'type' => 'color',
				'title' => __( 'Accent Color', 'redux-framework-demo' ),
				'subtitle' => __( 'Max container width of Single page', 'redux-framework-demo' ),
				'default' => isset($mcd_settings['accent_color']) ? $mcd_settings['accent_color'] : '#3d80b9',
				'validate' => 'color',
			),
		)
	)
);
