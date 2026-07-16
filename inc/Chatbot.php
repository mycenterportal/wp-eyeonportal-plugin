<?php

if ( ! class_exists( 'EyeOnChatbot' ) ) {
	class EyeOnChatbot {

		private $mcd_settings;
		private $enabled = false;

		private static $style_defaults = array(
			'chatbot_header_bg'           => '#3d80b9',
			'chatbot_header_text'         => '#ffffff',
			'chatbot_chat_bg'             => '#f8f9fb',
			'chatbot_user_bg'             => '#3d80b9',
			'chatbot_user_text'           => '#ffffff',
			'chatbot_assistant_bg'        => '#ffffff',
			'chatbot_assistant_text'      => '#222222',
			'chatbot_send_bg'             => '#3d80b9',
			'chatbot_send_text'           => '#ffffff',
			'chatbot_launcher_bg'         => '#3d80b9',
			'chatbot_launcher_icon_color' => '#ffffff',
		);

		function __construct() {
			$this->mcd_settings = get_option( MCD_REDUX_OPT_NAME );
		}

		function register() {
			add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
			add_action( 'wp_footer', array( $this, 'render' ) );

			add_action( 'wp_ajax_eyeon_chat_request', array( $this, 'chat_request' ) );
			add_action( 'wp_ajax_nopriv_eyeon_chat_request', array( $this, 'chat_request' ) );
			add_action( 'wp_ajax_eyeon_chat_nonce', array( $this, 'chat_nonce' ) );
			add_action( 'wp_ajax_nopriv_eyeon_chat_nonce', array( $this, 'chat_nonce' ) );
		}

		function is_enabled() {
			if ( $this->enabled ) {
				return true;
			}
			$this->enabled = function_exists( 'eyeon_is_chatbot_enabled' ) && eyeon_is_chatbot_enabled();
			return $this->enabled;
		}

		private function get_setting( $key, $default = '' ) {
			if ( isset( $this->mcd_settings[ $key ] ) && $this->mcd_settings[ $key ] !== '' && null !== $this->mcd_settings[ $key ] ) {
				return $this->mcd_settings[ $key ];
			}

			return $default;
		}

		private function get_color_setting( $key ) {
			$default = isset( self::$style_defaults[ $key ] ) ? self::$style_defaults[ $key ] : '#000000';
			$value   = trim( (string) $this->get_setting( $key, $default ) );

			if ( '' === $value ) {
				return $default;
			}

			if ( '#' !== $value[0] ) {
				$value = '#' . $value;
			}

			$color = sanitize_hex_color( $value );

			return $color ? $color : $default;
		}

		private function get_position() {
			$position = $this->get_setting( 'chatbot_position', 'bottom-right' );

			return 'bottom-left' === $position ? 'bottom-left' : 'bottom-right';
		}

		private function get_launcher_icon_url() {
			$media = $this->get_setting( 'chatbot_launcher_icon', array() );

			if ( is_array( $media ) ) {
				if ( ! empty( $media['url'] ) ) {
					return esc_url_raw( $media['url'] );
				}
				if ( ! empty( $media['id'] ) ) {
					$url = wp_get_attachment_url( (int) $media['id'] );
					if ( $url ) {
						return esc_url_raw( $url );
					}
				}
			}

			// Legacy text URL field.
			$legacy = $this->get_setting( 'chatbot_icon_url', '' );
			if ( ! empty( $legacy ) ) {
				return esc_url_raw( $legacy );
			}

			return '';
		}

		private function get_style_vars() {
			return array(
				'--eyeon-chat-header-bg'           => $this->get_color_setting( 'chatbot_header_bg' ),
				'--eyeon-chat-header-text'         => $this->get_color_setting( 'chatbot_header_text' ),
				'--eyeon-chat-bg'                  => $this->get_color_setting( 'chatbot_chat_bg' ),
				'--eyeon-chat-user-bg'             => $this->get_color_setting( 'chatbot_user_bg' ),
				'--eyeon-chat-user-text'           => $this->get_color_setting( 'chatbot_user_text' ),
				'--eyeon-chat-assistant-bg'        => $this->get_color_setting( 'chatbot_assistant_bg' ),
				'--eyeon-chat-assistant-text'      => $this->get_color_setting( 'chatbot_assistant_text' ),
				'--eyeon-chat-send-bg'             => $this->get_color_setting( 'chatbot_send_bg' ),
				'--eyeon-chat-send-text'           => $this->get_color_setting( 'chatbot_send_text' ),
				'--eyeon-chat-launcher-bg'         => $this->get_color_setting( 'chatbot_launcher_bg' ),
				'--eyeon-chat-launcher-icon-color' => $this->get_color_setting( 'chatbot_launcher_icon_color' ),
				'--eyeon-chat-link'                => $this->get_color_setting( 'chatbot_user_bg' ),
			);
		}

		private function build_root_style_attr() {
			$parts = array();

			foreach ( $this->get_style_vars() as $name => $value ) {
				$parts[] = $name . ': ' . $value;
			}

			return implode( '; ', $parts ) . ';';
		}

		function maybe_enqueue() {
			if ( is_admin() || ! $this->is_enabled() ) {
				return;
			}

			mcd_include_css( 'chatbot', 'assets/chatbot/chatbot.css' );
			mcd_include_js( 'chatbot', 'assets/chatbot/chatbot.js', true );

			$center    = function_exists( 'eyeon_get_center' ) ? eyeon_get_center() : array();
			$center_id = ! empty( $center['id'] ) ? (int) $center['id'] : 0;

			wp_localize_script(
				'eyeon-chatbot',
				'EYEON_CHATBOT',
				array(
					'ajaxurl'        => admin_url( 'admin-ajax.php' ),
					'centerId'       => $center_id,
					'botName'        => $this->get_setting( 'chatbot_bot_name', 'Center Assistant' ),
					'welcomeMessage' => $this->get_setting( 'chatbot_welcome_message', 'Hi! Ask me anything about our center.' ),
					'offlineMessage' => $this->get_setting( 'chatbot_offline_message', 'Sorry, the assistant is temporarily unavailable.' ),
					'position'       => $this->get_position(),
					'linkBases'      => array(
						'deal'   => mcd_single_page_url( 'mycenterdeal' ),
						'store'  => mcd_single_page_url( 'mycenterstore' ),
						'event'  => mcd_single_page_url( 'mycenterevent' ),
						'career' => mcd_single_page_url( 'mycentercareer' ),
						'news'   => mcd_single_page_url( 'mycenterblogpost' ),
					),
				)
			);
		}

		function render() {
			if ( is_admin() || ! $this->is_enabled() ) {
				return;
			}

			$position  = $this->get_position();
			$bot_name  = $this->get_setting( 'chatbot_bot_name', 'Center Assistant' );
			$icon_url  = $this->get_launcher_icon_url();
			$root_style = $this->build_root_style_attr();
			?>
			<div id="eyeon-chatbot-root" class="eyeon-chatbot eyeon-chatbot--<?php echo esc_attr( $position ); ?>" style="<?php echo esc_attr( $root_style ); ?>" aria-live="polite">
				<button type="button" class="eyeon-chatbot__launcher" id="eyeon-chatbot-launcher" aria-label="<?php echo esc_attr( sprintf( __( 'Open %s', EYEON_NAMESPACE ), $bot_name ) ); ?>">
					<?php if ( $icon_url ) : ?>
						<img src="<?php echo esc_url( $icon_url ); ?>" alt="" class="eyeon-chatbot__launcher-icon eyeon-chatbot__launcher-icon--image" />
					<?php else : ?>
						<svg class="eyeon-chatbot__launcher-icon eyeon-chatbot__launcher-icon--svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
					<?php endif; ?>
				</button>
				<div class="eyeon-chatbot__panel" id="eyeon-chatbot-panel" hidden>
					<div class="eyeon-chatbot__header">
						<div class="eyeon-chatbot__header-info">
							<?php if ( $icon_url ) : ?>
								<img src="<?php echo esc_url( $icon_url ); ?>" alt="" class="eyeon-chatbot__avatar" />
							<?php endif; ?>
							<strong class="eyeon-chatbot__title"><?php echo esc_html( $bot_name ); ?></strong>
						</div>
						<button type="button" class="eyeon-chatbot__close" id="eyeon-chatbot-close" aria-label="<?php esc_attr_e( 'Close chat', EYEON_NAMESPACE ); ?>">&times;</button>
					</div>
					<div class="eyeon-chatbot__messages" id="eyeon-chatbot-messages"></div>
					<form class="eyeon-chatbot__form" id="eyeon-chatbot-form">
						<input type="text" class="eyeon-chatbot__input" id="eyeon-chatbot-input" placeholder="<?php esc_attr_e( 'Type your question...', EYEON_NAMESPACE ); ?>" maxlength="500" autocomplete="off" />
						<button type="submit" class="eyeon-chatbot__send" id="eyeon-chatbot-send"><?php esc_html_e( 'Send', EYEON_NAMESPACE ); ?></button>
					</form>
				</div>
			</div>
			<?php
		}

		function chat_nonce() {
			wp_send_json_success(
				array(
					'nonce' => wp_create_nonce( 'eyeon_api_nonce' ),
				)
			);
		}

		function chat_request() {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'eyeon_api_nonce' ) ) {
				wp_send_json_error( array( 'msg' => "You're not authorized to make this request." ), 403 );
			}

			if ( ! eyeon_is_chatbot_enabled() ) {
				wp_send_json_error( array( 'msg' => 'Chatbot is not enabled for this center.' ), 403 );
			}

			$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
			if ( empty( $message ) ) {
				wp_send_json_error( array( 'msg' => 'Message is required.' ), 400 );
			}

			$history = array();
			if ( ! empty( $_POST['history_json'] ) ) {
				$decoded = json_decode( wp_unslash( $_POST['history_json'] ), true );
				if ( is_array( $decoded ) ) {
					$_POST['history'] = $decoded;
				}
			}
			if ( ! empty( $_POST['history'] ) && is_array( $_POST['history'] ) ) {
				foreach ( array_slice( $_POST['history'], -6 ) as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$role = isset( $item['role'] ) ? sanitize_text_field( $item['role'] ) : '';
					$content = isset( $item['content'] ) ? sanitize_textarea_field( wp_unslash( $item['content'] ) ) : '';
					if ( in_array( $role, array( 'user', 'assistant' ), true ) && $content !== '' ) {
						$history[] = array(
							'role'    => $role,
							'content' => mb_substr( $content, 0, 2000 ),
						);
					}
				}
			}

			$visitor_id = isset( $_POST['visitor_id'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_id'] ) ) : '';

			$client_ip = '';
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
				$parts     = explode( ',', $forwarded );
				$client_ip = trim( $parts[0] );
			} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
				$client_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			}

			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: '';

			$result = mcd_api_post(
				MCD_API_CHAT,
				array(
					'message'    => mb_substr( $message, 0, 500 ),
					'history'    => $history,
					'visitor_id' => mb_substr( $visitor_id, 0, 64 ),
					'client_ip'  => mb_substr( $client_ip, 0, 45 ),
					'user_agent' => mb_substr( $user_agent, 0, 500 ),
				)
			);

			if ( $result['status'] === 200 && ! empty( $result['data']['reply'] ) ) {
				wp_send_json_success( $result['data'] );
			}

			$error_msg = 'Unable to get a response. Please try again.';
			if ( ! empty( $result['data']['error']['description'] ) ) {
				$desc = $result['data']['error']['description'];
				if ( is_array( $desc ) ) {
					$error_msg = implode( ' ', array_map( 'strval', $desc ) );
				} else {
					$error_msg = (string) $desc;
				}
			} elseif ( ! empty( $result['data']['error']['error_message'] ) ) {
				$error_msg = 'AI service error. Please try again later.';
			}

			wp_send_json_error(
				array(
					'msg'    => $error_msg,
					'status' => $result['status'],
				),
				$result['status'] >= 400 ? $result['status'] : 500
			);
		}
	}

	$eyeonChatbot = new EyeOnChatbot();
	$eyeonChatbot->register();
}
