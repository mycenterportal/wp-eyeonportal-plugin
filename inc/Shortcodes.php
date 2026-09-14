<?php

if( !class_exists('MCDShortcodes') ) {
	class MCDShortcodes {

		private $mcd_settings;
		private $page_title = MCD_PLUGIN_TITLE;
		private $og_description = '';
		private $og_image = '';
		private $template = '';
		private $search = array();
		private $links_page_template = 'templates/links.php';

		function __construct() {
			$this->mcd_settings = get_option(MCD_REDUX_OPT_NAME);
		}

		function register() {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue') );

      // add_shortcode('mcd_search_form', function() { return ''; });
      // add_shortcode('mcd_opening_hours_week', function() { return ''; });
      // add_shortcode('mcd_opening_hours_today', function() { return ''; });

      add_shortcode('mcp_site_name', function() { return get_bloginfo('name'); });
      add_shortcode('mcp_site_url', function() { return site_url(); });
      add_shortcode('mcp_site_domain', function() { return $_SERVER['HTTP_HOST']; });
			
			add_filter( 'query_vars', array( $this, 'add_rewrite_vars' ) );
			add_action( 'template_redirect', array( $this, 'single_page_rewrite_catch' ) );
			add_action( 'redux/options/'.MCD_REDUX_OPT_NAME.'/settings/change', array( $this, 'redux_options_saved') );
      
      add_action( 'parse_request', array($this, 'handle_image_proxy_request') );

			add_filter( 'body_class', array( $this, 'add_plugin_body_class') );
			add_filter( 'wp_head', array( $this, 'dynamic_styles_scripts') );
			add_action( 'wp_head', array( $this, 'output_eyeon_script'), 1 );
			add_action( 'init', array( $this, 'mcd_init' ) );

			add_filter( 'theme_page_templates', array( $this, 'links_page_template' ) );
			add_filter( 'template_include', array( $this, 'links_change_page_template' ) );

			add_filter( 'wp_title', array( $this, 'change_page_title' ), 999 );
			add_filter( 'wpseo_title', array( $this, 'change_page_title' ), 999 );
			add_filter( 'pre_get_document_title', array( $this, 'change_page_title' ), 999 );
			
			// Rank Math SEO filters - override OG tags with our custom data
			add_filter( 'rank_math/frontend/title', array( $this, 'change_page_title' ), 999 );
			add_filter( 'rank_math/frontend/description', array( $this, 'rankmath_description' ), 999 );
			add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'change_page_title' ), 999 );
			add_filter( 'rank_math/opengraph/facebook/og_description', array( $this, 'rankmath_description' ), 999 );
			add_filter( 'rank_math/opengraph/facebook/og_url', array( $this, 'rankmath_url' ), 999 );
			add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'rankmath_image' ), 999 );
			add_filter( 'rank_math/opengraph/twitter/title', array( $this, 'change_page_title' ), 999 );
			add_filter( 'rank_math/opengraph/twitter/description', array( $this, 'rankmath_description' ), 999 );
			add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'rankmath_image' ), 999 );
			add_filter( 'rank_math/frontend/canonical', array( $this, 'rankmath_url' ), 999 );
			
			// Fallback OG meta for sites without Rank Math
			add_action( 'wp_head', array( $this, 'output_opengraph_meta' ), 5 );

      add_action('wp_ajax_eyeon_api_request', array( $this, 'eyeon_api_request' ) );
      add_action('wp_ajax_nopriv_eyeon_api_request', array( $this, 'eyeon_api_request' ) );

      add_action('wp_ajax_eyeon_save_map_response', array( $this, 'eyeon_save_map_response' ) );
      add_action('wp_ajax_nopriv_eyeon_save_map_response', array( $this, 'eyeon_save_map_response' ) );

      // Register REST API proxy endpoint
      add_action('rest_api_init', array( $this, 'register_rest_api_proxy' ) );
    }

		function mcd_init() {
      // flush rewrite rules
			global $wp_rewrite;

			$db_mcd_plugin_version = get_option('mcd_plugin_version');
			$this->add_rewrite_rules($this->mcd_settings);
			if( MCD_PLUGIN_VERSION != $db_mcd_plugin_version ) {
				$wp_rewrite->flush_rules(false);
				update_option('mcd_plugin_version', MCD_PLUGIN_VERSION);
			}
    }

		function redux_options_saved($options) {
			global $wp_rewrite;
			$this->add_rewrite_rules($options);
			$wp_rewrite->flush_rules(false);
		}

		function add_plugin_body_class($classes) {
			global $post, $wp_query;
			$classes[] = mcd_current_theme_name();
			
			if( $this->is_querystring_present() ) {
        $classes[] = MCD_PLUGIN_NAME.'-single';
			}
			return $classes;
		}

		function return_include_output($file) {
			ob_start();
			include( $file );
			return ob_get_clean(); 
		}

		function add_rewrite_vars( $vars ) {
			$vars[] = 'mycenterdeal';
			$vars[] = 'mycenterstore';
			$vars[] = 'mycenterevent';
			$vars[] = 'mycentercareer';
			$vars[] = 'mycenterblogpost';
			$vars[] = 'mcdmapretailer';
			return $vars;
		}

    function handle_image_proxy_request() {
      if (isset($_GET['eyeonmedia'])) {
        $image_url = urldecode($_GET['eyeonmedia']);

        // Security check - you might want to add more validation here
        $allowed_domains = array(
          'eyeon-media-development.eyeondev1.com',
          'eyeon-media-staging.eyeondev1.com',
          'eyeon-media-production.eyeondev1.com',
        );

        $parsed_url = parse_url($image_url);
        if (!in_array($parsed_url['host'], $allowed_domains)) {
          status_header(403);
          die('Domain not allowed');
        }
        
        // Fetch the image
        $response = wp_remote_get($image_url, array(
          'timeout' => 10,
          'sslverify' => false
        ));

        if (is_wp_error($response)) {
          status_header(404);
          die('Failed to fetch image');
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $image_data = wp_remote_retrieve_body($response);

        // Verify it's an image
        if (!strpos($content_type, 'image/') === 0) {
          status_header(400);
          die('Invalid image type');
        }

        // Set headers
        nocache_headers();
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($image_data));

        // Output image
        echo $image_data;
        exit;
      }
    }

		function add_rewrite_rules($saved_options) {
			add_rewrite_tag( '%mycenterdeal%', '([^&]+)' );
			add_rewrite_rule(
				'^'.$saved_options['deals_single_page_slug'].'/([^/]*)/?',
				'index.php?page_id='.$this->mcd_settings['deals_single_page_template'].'&mycenterdeal=$matches[1]',
				'top'
			);

			add_rewrite_tag( '%mycenterstore%', '([^&]+)' );
			add_rewrite_rule(
				'^'.$saved_options['stores_single_page_slug'].'/([^/]*)/?',
				'index.php?page_id='.$this->mcd_settings['stores_single_page_template'].'&mycenterstore=$matches[1]',
				'top'
			);

			add_rewrite_tag( '%mycenterevent%', '([^&]+)' );
			add_rewrite_rule(
				'^'.$saved_options['events_single_page_slug'].'/([^/]*)/?',
				'index.php?page_id='.$this->mcd_settings['events_single_page_template'].'&mycenterevent=$matches[1]',
				'top'
			);

			add_rewrite_tag( '%mycentercareer%', '([^&]+)' );
			add_rewrite_rule(
				'^'.$saved_options['careers_single_page_slug'].'/([^/]*)/?',
				'index.php?page_id='.$this->mcd_settings['careers_single_page_template'].'&mycentercareer=$matches[1]',
				'top'
			);

			add_rewrite_tag( '%mycenterblogpost%', '([^&]+)' );
			add_rewrite_rule(
				'^'.$saved_options['blog_single_page_slug'].'/([^/]*)/?',
				'index.php?page_id='.$this->mcd_settings['blog_single_page_template'].'&mycenterblogpost=$matches[1]',
				'top'
			);

			$map_page_slug = get_post_field('post_name', $this->mcd_settings['map_page']);
			add_rewrite_tag( '%mcdmapretailer%', '([^&]+)' );
			add_rewrite_rule(
				'^'.$map_page_slug.'/([^/]*)/?',
				'index.php?page_id='.$this->mcd_settings['map_page'].'&mcdmapretailer=$matches[1]',
				'top'
			);
		}

		function single_page_rewrite_catch() {
			global $wp_query;

			// redirect query string pages to SEO frienly URLs
			if( isset($_GET['mycenterdeal']) ) {
				wp_redirect( home_url( "/".$this->mcd_settings['stores_single_page_slug']."/" ) . urlencode( get_query_var( 'mycenterdeal' ) ) ); exit;
			}
			if( isset($_GET['mycenterstore']) ) {
				wp_redirect( home_url( "/".$this->mcd_settings['stores_single_page_slug']."/" ) . urlencode( get_query_var( 'mycenterstore' ) ) ); exit;
			}
			if( isset($_GET['mycenterevent']) ) {
				wp_redirect( home_url( "/".$this->mcd_settings['stores_single_page_slug']."/" ) . urlencode( get_query_var( 'mycenterevent' ) ) ); exit;
			}
			if( isset($_GET['mycentercareer']) ) {
				wp_redirect( home_url( "/".$this->mcd_settings['careers_single_page_slug']."/" ) . urlencode( get_query_var( 'mycentercareer' ) ) ); exit;
			}
			if( isset($_GET['mycenterblogpost']) ) {
				wp_redirect( home_url( "/".$this->mcd_settings['blog_single_page_slug']."/" ) . urlencode( get_query_var( 'mycenterblogpost' ) ) ); exit;
			}
			if( isset($_GET['mcdmapretailer']) ) {
				wp_redirect( home_url( "/".$this->mcd_settings['map_page']."/" ) . urlencode( get_query_var( 'mcdmapretailer' ) ) ); exit;
			}

			if ( array_key_exists( 'mycenterdeal', $wp_query->query_vars ) ) {
				$this->template = 'templates/deal.php';
				$req_url = MCD_API_DEALS.'/'.get_query_var('mycenterdeal', 0);
        $response = mcd_api_data($req_url);
        $dealData = $response['data'];
				$this->mcd_settings['mycenterdeal'] = $dealData;
				if( $this->mcd_settings['deals_single_page_title'] == 'custom' ) {
					$this->page_title = $this->mcd_settings['deals_single_page_custom_title'];
				} else {
					$this->page_title = @$this->mcd_settings['mycenterdeal']['title'];
				}
				// Set Open Graph data
				$this->og_description = @$dealData['description'] ?: '';
				$this->og_image = @$dealData['media']['url'] ?: '';
			} elseif ( array_key_exists( 'mycenterstore', $wp_query->query_vars ) ) {
				$this->template = 'templates/store.php';
        $multiple_location_retailer_id = (isset($_GET['r']) && !empty(['r'])) ? $_GET['r'] : null;
				$req_url = MCD_API_STORES.'/'.get_query_var('mycenterstore', 0).($multiple_location_retailer_id?'/'.$multiple_location_retailer_id:'');
				$response = mcd_api_data($req_url);
				$storeData = $response['data'];
				$this->mcd_settings['mycenterstore'] = $storeData;
				if( $this->mcd_settings['stores_single_page_title'] == 'custom' ) {
					$this->page_title = $this->mcd_settings['stores_single_page_custom_title'];
				} else {
					$this->page_title = @$this->mcd_settings['mycenterstore']['name'];
				}
				// Set Open Graph data
				$this->og_description = @$storeData['description'] ?: '';
				$this->og_image = @$storeData['media']['url'] ?: '';
			} elseif ( array_key_exists( 'mycenterevent', $wp_query->query_vars ) ) {
				$this->template = 'templates/event.php';

        // ==================
        $this->mcd_settings['events_single_add_to_calendar'] = false;
				// mcd_include_js('add-to-calendar', 'assets/plugins/add-to-calendar.min.js', true);
        // ==================

				$req_url = MCD_API_EVENTS.'/'.get_query_var('mycenterevent', 0);
        $response = mcd_api_data($req_url);
        $eventData = $response['data'];
				$this->mcd_settings['mycenterevent'] = $eventData;
				if( $this->mcd_settings['events_single_page_title'] == 'custom' ) {
					$this->page_title = $this->mcd_settings['events_single_page_custom_title'];
				} else {
					$this->page_title = $this->mcd_settings['mycenterevent']['title'];
				}
				// Set Open Graph data
				$this->og_description = @$eventData['description'] ?: '';
				$this->og_image = @$eventData['media']['url'] ?: '';
			} elseif ( array_key_exists( 'mycentercareer', $wp_query->query_vars ) ) {
				$this->template = 'templates/career.php';
				$req_url = MCD_API_CAREERS.'/'.get_query_var('mycentercareer', 0);
        $response = mcd_api_data($req_url);
        $careerData = $response['data'];
				$this->mcd_settings['mycentercareer'] = $careerData;
				if( $this->mcd_settings['careers_single_page_title'] == 'custom' ) {
					$this->page_title = $this->mcd_settings['careers_single_page_custom_title'];
				} else {
					$this->page_title = $this->mcd_settings['mycentercareer']['title'];
				}
				// Set Open Graph data
				$this->og_description = @$careerData['description'] ?: '';
				// Careers typically don't have images, use retailer logo if available
				$this->og_image = @$careerData['retailer']['media']['url'] ?: '';
			} elseif ( array_key_exists( 'mycenterblogpost', $wp_query->query_vars ) ) {
				$this->template = 'templates/blog.php';
        wp_enqueue_script( 'eyeon-moment' );
        wp_enqueue_script( 'eyeon-elementor-utils' );
				$req_url = MCD_API_NEWS.'/'.get_query_var('mycenterblogpost', 0);
				$response = mcd_api_data($req_url);
				$blogpost = $response['data'];
        $this->mcd_settings['mycenterblogpost'] = $blogpost;
				if( $this->mcd_settings['blog_single_page_title'] == 'custom' ) {
					$this->page_title = $this->mcd_settings['blog_single_page_custom_title'];
				} else {
					$this->page_title = $this->mcd_settings['mycenterblogpost']['title'];
				}
				// Set Open Graph data
				$this->og_description = @$blogpost['excerpt'] ?: (@$blogpost['content'] ?: '');
				$this->og_image = @$blogpost['media']['url'] ?: '';
			}

			add_filter( 'the_content', array( $this, 'change_single_page_content') );
		}
 
		function links_page_template( $templates ) {
			$templates[$this->links_page_template] = 'MCP Links Template';
			return $templates;
		}

		function links_change_page_template($template) {
			if (is_page()) {
				$meta = get_post_meta(get_the_ID());

				if (!empty($meta['_wp_page_template'][0]) && $meta['_wp_page_template'][0] != $template) {
					$selected_template = $meta['_wp_page_template'][0];
					if( $selected_template == $this->links_page_template ) {
						$template = MCD_PLUGIN_PATH.$meta['_wp_page_template'][0];
					}
				}
			}

			return $template;
		}

		function change_single_page_content( $content ) {
			global $post, $wp_query;

			if( !empty($this->template) ) {
				$content .= $this->return_include_output( MCD_PLUGIN_PATH . $this->template );
			}
			return $content;
		}

		function change_page_title($title) {
			if( $this->is_querystring_present() ) {
        $title = $this->page_title.' - '.get_bloginfo('name');
			}
			return $title;
		}

		/**
		 * Rank Math SEO: Override description
		 */
		function rankmath_description($description) {
			if( $this->is_querystring_present() && !empty($this->og_description) ) {
				$og_description = $this->og_description;
				$og_description = html_entity_decode($og_description, ENT_QUOTES, 'UTF-8');
				$og_description = wp_strip_all_tags($og_description);
				$og_description = preg_replace('/\s+/', ' ', $og_description);
				$og_description = trim($og_description);
				$og_description = wp_trim_words($og_description, 30, '...');
				return $og_description;
			}
			return $description;
		}

		/**
		 * Rank Math SEO: Override URL
		 */
		function rankmath_url($url) {
			if( $this->is_querystring_present() ) {
				return get_current_url();
			}
			return $url;
		}

		/**
		 * Rank Math SEO: Override image
		 */
		function rankmath_image($image) {
			if( $this->is_querystring_present() && !empty($this->og_image) ) {
				return $this->og_image;
			}
			return $image;
		}

		/**
		 * Output Open Graph meta tags for social sharing (fallback for sites without Rank Math)
		 */
		function output_opengraph_meta() {
			// Skip if Rank Math is active - it handles OG tags
			if( class_exists('RankMath') ) {
				return;
			}
			if( !$this->is_querystring_present() ) {
				return;
			}

			$og_title = esc_attr($this->page_title . ' - ' . get_bloginfo('name'));
			$og_url = esc_url(get_current_url());
			$og_site_name = esc_attr(get_bloginfo('name'));
			
			// Clean description - decode HTML entities, strip tags, normalize whitespace, limit length
			$og_description = $this->og_description;
			$og_description = html_entity_decode($og_description, ENT_QUOTES, 'UTF-8'); // Decode HTML entities
			$og_description = wp_strip_all_tags($og_description); // Strip HTML tags
			$og_description = preg_replace('/\s+/', ' ', $og_description); // Normalize whitespace
			$og_description = trim($og_description);
			$og_description = wp_trim_words($og_description, 30, '...');
			$og_description = esc_attr($og_description);
			
			// Get og:image - use fallback if empty
			$og_image = $this->og_image;
			if (empty($og_image)) {
				// Fallback to site logo
				$custom_logo_id = get_theme_mod('custom_logo');
				if ($custom_logo_id) {
					$og_image = wp_get_attachment_image_url($custom_logo_id, 'full');
				}
			}
			$og_image = esc_url($og_image);

			echo "\n<!-- EyeOn Portal Open Graph Meta Tags -->\n";
			echo '<meta property="og:type" content="article" />' . "\n";
			echo '<meta property="og:title" content="' . $og_title . '" />' . "\n";
			echo '<meta property="og:url" content="' . $og_url . '" />' . "\n";
			echo '<meta property="og:site_name" content="' . $og_site_name . '" />' . "\n";
			
			if (!empty($og_description)) {
				echo '<meta property="og:description" content="' . $og_description . '" />' . "\n";
				echo '<meta name="description" content="' . $og_description . '" />' . "\n";
			}
			
			// Always output og:image - LinkedIn and Facebook require it explicitly
			echo '<meta property="og:image" content="' . $og_image . '" />' . "\n";
			if (!empty($og_image)) {
				echo '<meta property="og:image:secure_url" content="' . $og_image . '" />' . "\n";
			}

			// Twitter Card meta tags
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			echo '<meta name="twitter:title" content="' . $og_title . '" />' . "\n";
			
			if (!empty($og_description)) {
				echo '<meta name="twitter:description" content="' . $og_description . '" />' . "\n";
			}
			
			echo '<meta name="twitter:image" content="' . $og_image . '" />' . "\n";
			
			echo "<!-- End EyeOn Portal Open Graph Meta Tags -->\n\n";
		}

		function enqueue() {
			global $post, $wp_query;
			
			mcd_include_css('fontawesome', 'assets/plugins/fontawesome/css/fontawesome-all.min.css');
			
			if( $this->is_querystring_present() ) {
				mcd_include_js('main', 'assets/js/main.js');
				mcd_include_css('style', 'assets/css/style.min.css');
        wp_enqueue_style( 'eyeon-elementor-style' );
			}
		}

		function output_eyeon_script() {
			// Output EYEON variable directly in wp_head
			// This ensures it's always available regardless of jQuery's enqueue state
			$ajaxurl = admin_url('admin-ajax.php');
			$nonce = wp_create_nonce('eyeon_api_nonce');
			echo "<script type='text/javascript'>\n";
			echo "var EYEON = " . wp_json_encode([
				'ajaxurl' => $ajaxurl,
				'nonce' => $nonce
			]) . ";\n";
			echo "</script>\n";
		}

		function is_querystring_present() {
			global $post, $wp_query;
			$query_strings = array(
				'mycenterdeal',
				'mycenterstore',
				'mycenterevent',
				'mycentercareer',
				'mycenterblogpost',
			);
			$response = false;
			foreach ($query_strings as $query_string) {
				if( array_key_exists($query_string, $wp_query->query_vars) ) {
					$response = true;
					break;
				}
			}
			return $response;
		}

		function dynamic_styles_scripts() {
			global $post, $wp_query;
			$mcd_settings = $this->mcd_settings;

			if( $this->is_querystring_present() ) {
				include ( MCD_PLUGIN_PATH . 'assets/dynamic.php');
			}
		}

    function eyeon_api_request() {
      // Verify nonce for security (replaces cookie-based auth to avoid race condition on first page load)
      $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
      if (!wp_verify_nonce($nonce, 'eyeon_api_nonce')) {
        wp_send_json_error(['msg' => "You're not authorized to access this resource."], 403);
      }

      $apiUrl = isset($_POST['apiUrl']) ? $_POST['apiUrl'] : '';
      $params = isset($_POST['params']) ? $_POST['params'] : array();
      $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh']==='true' ? true : false;
      $paginated_data = isset($_POST['paginated_data']) && $_POST['paginated_data']==='true' ? true : false;
      $nocache = isset($_POST['nocache']) && $_POST['nocache']==='true' ? true : false;

      if (!$apiUrl) {
        wp_send_json_error(['msg' => "API URL missing"], 400);
      }

      // Generate unique cache key per API + params
      $apiNameForCache = isset($_GET['api']) ? $_GET['api'] : $apiUrl;
      $option_key = get_eyeon_api_cache_key($apiNameForCache);

      // Read cached data
      if(!$force_refresh && !$nocache) {
        $cached = get_option($option_key);
        $cached_data = $cached ? json_decode($cached, true) : null;
        
        // Send cached data immediately if available
        if ($cached_data) {
          $cached_data['stale_data'] = true;
          wp_send_json($cached_data);
        }
      }

      // If no cached data, fetch fresh (first load)
      $fresh_data = null;
      $api_status = 200;
      if($paginated_data) {
        $result = $this->fetch_api_data_and_handle_pagination($apiUrl, $params);
        $fresh_data = $result['data'];
        $api_status = $result['status'];
      } else {
        $result = mcd_api_data($apiUrl.'?'.http_build_query($params));
        $fresh_data = $result['data'];
        $api_status = $result['status'];
      }
      
      // Only cache successful responses (status 200 and no error)
      if(!$nocache && $api_status === 200 && $fresh_data && !isset($fresh_data['error'])) {
        update_option($option_key, json_encode($fresh_data));
      }
      
      $fresh_data['stale_data'] = false;
      wp_send_json($fresh_data);
    }

    function fetch_api_data_and_handle_pagination($apiUrl, $params) {
      $all_items = array();
      $page = 1;
      $limit = 100;
      $total_count = 0;
      $last_status = 200;

      do {
        $params['page'] = $page;
        $params['limit'] = $limit;
        
        $response = mcd_api_data($apiUrl . '?' . http_build_query($params));
        $last_status = $response['status'];
        $data = $response['data'];
        
        // If API returns an error, return it immediately (don't cache errors)
        if ($data && isset($data['error'])) {
          return array(
            'status' => $last_status,
            'data' => $data
          );
        }
        
        if (!$data || !isset($data['items'])) {
          break;
        }

        $all_items = array_merge($all_items, $data['items']);
        $total_count = isset($data['count']) ? intval($data['count']) : 0;

        if (count($all_items) < $total_count) {
          $page++;
        } else {
          break;
        }
      } while (true);

      return array(
        'status' => $last_status,
        'data' => array(
          'items' => $all_items,
          'count' => $total_count
        )
      );
    }

    /**
     * Save map API response to wp_options
     */
    function eyeon_save_map_response() {
      // Get the map API response data
      // It's sent as a JSON string to avoid max_input_vars limits
      $map_response_json = isset($_POST['mapResponse']) ? stripslashes($_POST['mapResponse']) : null;
      $map_response = json_decode($map_response_json, true);
      
      if (empty($map_response)) {
        // Fallback: try reading it directly if not JSON string (backward compatibility)
        $map_response = isset($_POST['mapResponse']) ? $_POST['mapResponse'] : null;
      }
      
      if (empty($map_response)) {
        wp_send_json_error(['msg' => 'Map response data is required.'], 400);
      }

      // Save to wp_options (one website = one center data)
      $saved = update_option(THREEJS_MAP_API_RESPONSE_KEY, $map_response);

      if ($saved) {
        wp_send_json_success([
          'msg' => 'Map API response saved successfully.',
          'option_name' => THREEJS_MAP_API_RESPONSE_KEY
        ]);
      } else {
        // If data hasn't changed, update_option returns false. 
        // We should check if option exists and matches to determine if it's an error or just no change.
        if (get_option(THREEJS_MAP_API_RESPONSE_KEY) === $map_response) {
            wp_send_json_success([
            'msg' => 'Map API response already up to date.',
            'option_name' => THREEJS_MAP_API_RESPONSE_KEY
          ]);
        } else {
            wp_send_json_error(['msg' => 'Failed to save map API response.'], 500);
        }
      }
    }

    /**
     * Register REST API proxy endpoint
     * This allows libraries to send requests directly to WordPress which then proxies to the API
     * Usage: /wp-json/eyeon-portal/map/v1/retailers?limit=10&page=1
     */
    function register_rest_api_proxy() {
      register_rest_route('eyeon-portal', '/map/(?P<path>.*)', array(
        'methods' => 'GET',
        'callback' => array($this, 'handle_rest_api_proxy'),
        'permission_callback' => '__return_true', // We'll handle auth in the callback
      ));
    }

    /**
     * Handle REST API proxy requests
     */
    function handle_rest_api_proxy($request) {
      // Get the API path from the route parameter
      $api_path = $request->get_param('path');
      if (empty($api_path)) {
        return new WP_Error('bad_request', 'API path is required.', array('status' => 400));
      }

      // Ensure path starts with /
      if (strpos($api_path, '/') !== 0) {
        $api_path = '/' . $api_path;
      }

      // Get query parameters from the request (GET only)
      $query_params = $request->get_query_params();
      // Remove 'path' from query params as it's a route parameter
      unset($query_params['path']);

      // Build the full API URL with query parameters
      // Note: mcd_api_data will prepend API_BASE_URL and add time parameter
      $api_url = $api_path;
      if (!empty($query_params)) {
        $api_url .= '?' . http_build_query($query_params);
      }

      // Preserve JSON objects (`{}`). `json_decode(..., true)` turns empty
      // objects into `[]`, which crashes the v2 map viewer on floor switch
      // (`(value ?? "").trim is not a function` for style/label/theme fields).
      $response = mcd_api_data($api_url, false);

      // Return the response data
      return rest_ensure_response($response['data']);
    }
	}

	$mcd_shortcodes = new MCDShortcodes();
	$mcd_shortcodes->register();
}

