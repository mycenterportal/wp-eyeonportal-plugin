<?php defined( 'ABSPATH' ) || exit;

$api_token         = isset( $mcd_settings['api_access_token'] ) ? $mcd_settings['api_access_token'] : '';
$center_lookup     = mcd_api_data( MCD_API_CENTER );
$center            = ( isset( $center_lookup['data'] ) && is_array( $center_lookup['data'] ) ) ? $center_lookup['data'] : null;
$environment_badge = eyeon_get_api_token_environment_badge_html( $api_token );

$center_desc = '';
if ( $center && ! empty( $center['id'] ) && ! empty( $center['name'] ) ) {
	$center_desc = sprintf(
		'#%1$d - %2$s',
		(int) $center['id'],
		esc_html( $center['name'] )
	);

	if ( $environment_badge ) {
		$center_desc .= ' ' . $environment_badge;
	}
} elseif ( ! empty( $api_token ) ) {
	$status = isset( $center_lookup['status'] ) ? (int) $center_lookup['status'] : 0;
	if ( $status >= 200 && $status < 300 && empty( $center_lookup['error'] ) ) {
		$error_message = 'Unexpected API response — center id/name missing.';
	} else {
		$error_message = eyeon_get_api_error_message( $center_lookup );
	}

	if ( $environment_badge ) {
		$center_desc .= $environment_badge . ' ';
	}
	$center_desc .= '<span class="eyeon-api-token-error">' . esc_html( $error_message ) . '</span>';
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
