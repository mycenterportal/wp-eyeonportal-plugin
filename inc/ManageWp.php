<?php

if ( ! class_exists( 'EyeOnManageWp' ) ) {
	class EyeOnManageWp {

		const MANAGE_API_VERSION = '1';

		public function register() {
			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		}

		public function register_rest_routes() {
			register_rest_route(
				'eyeon-portal/v1',
				'/manage-wp/status',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_status' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'eyeon-portal/v1',
				'/manage-wp/update',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_update' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		private function verify_token( $request ) {
			$token      = $request->get_header( 'X-Eyeon-Api-Token' );
			$settings   = get_option( MCD_REDUX_OPT_NAME );
			$configured = isset( $settings['api_access_token'] ) ? $settings['api_access_token'] : '';

			if ( empty( $configured ) ) {
				return new WP_Error(
					'token_not_configured',
					'API token not configured on site.',
					array( 'status' => 503 )
				);
			}

			if ( empty( $token ) || ! hash_equals( $configured, $token ) ) {
				return new WP_Error(
					'invalid_token',
					'API token mismatch',
					array( 'status' => 401 )
				);
			}

			return true;
		}

		private function is_allowed_package_url( $package_url ) {
			$host = wp_parse_url( $package_url, PHP_URL_HOST );
			if ( empty( $host ) ) {
				return false;
			}

			$allowed_hosts = array(
				'api.github.com',
				'github.com',
				'codeload.github.com',
				'objects.githubusercontent.com',
			);

			return in_array( strtolower( $host ), $allowed_hosts, true );
		}

		private function resolve_package_data( $params ) {
			if ( ! empty( $params['package_url'] ) && is_string( $params['package_url'] ) ) {
				$package_url = esc_url_raw( $params['package_url'] );
				if ( empty( $package_url ) || ! $this->is_allowed_package_url( $package_url ) ) {
					return new WP_Error(
						'invalid_package_url',
						'Plugin package URL is not allowed.',
						array( 'status' => 400 )
					);
				}

				$response = wp_remote_get(
					$package_url,
					array(
						'timeout'     => 180,
						'redirection' => 5,
						'user-agent'  => 'EyeOn-Portal-ManageWp/' . MCD_PLUGIN_VERSION,
					)
				);

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				$status_code = wp_remote_retrieve_response_code( $response );
				if ( $status_code < 200 || $status_code >= 300 ) {
					return new WP_Error(
						'download_failed',
						'Failed to download plugin package.',
						array( 'status' => 502 )
					);
				}

				$package_data = wp_remote_retrieve_body( $response );
				if ( empty( $package_data ) ) {
					return new WP_Error(
						'empty_package',
						'Downloaded plugin package is empty.',
						array( 'status' => 502 )
					);
				}

				return $package_data;
			}

			if ( ! empty( $params['package_base64'] ) && is_string( $params['package_base64'] ) ) {
				$package_data = base64_decode( $params['package_base64'], true );
				if ( false === $package_data || '' === $package_data ) {
					return new WP_Error(
						'invalid_package',
						'Invalid plugin package.',
						array( 'status' => 400 )
					);
				}

				return $package_data;
			}

			return new WP_Error(
				'missing_package',
				'Plugin package not provided.',
				array( 'status' => 400 )
			);
		}

		/**
		 * Prevent any cache layer (WordPress, page-cache plugins, or a server
		 * cache such as RunCloud/Nginx FastCGI) from storing Manage WP API
		 * responses. These must always reflect the live plugin state.
		 */
		private function send_no_cache_headers() {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
				define( 'DONOTCACHEOBJECT', true );
			}
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			if ( ! headers_sent() ) {
				header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				// Nginx/RunCloud FastCGI cache honours this to skip caching.
				header( 'X-Accel-Expires: 0' );
			}
		}

		public function handle_status( $request ) {
			$this->send_no_cache_headers();

			$auth = $this->verify_token( $request );
			if ( is_wp_error( $auth ) ) {
				return $auth;
			}

			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			return rest_ensure_response(
				array(
					'installed_version'  => MCD_PLUGIN_VERSION,
					'wp_version'         => get_bloginfo( 'version' ),
					'plugin_active'      => is_plugin_active( MCD_PLUGIN ),
					'remote_version'     => MCD_PLUGIN_VERSION,
					'update_available'   => false,
					'plugin_slug'        => dirname( MCD_PLUGIN ),
					'manage_api_version' => self::MANAGE_API_VERSION,
				)
			);
		}

		public function handle_update( $request ) {
			$this->send_no_cache_headers();

			$auth = $this->verify_token( $request );
			if ( is_wp_error( $auth ) ) {
				return $auth;
			}

			// The plugin must live in the canonical "eyeonportal" folder. When it
			// does not (e.g. it was installed from a versioned zip as
			// "eyeonportal-0.1.89"), an in-place update cannot reliably target the
			// active plugin, so refuse loudly and force a proper reinstall instead
			// of silently "succeeding" without changing anything.
			$slug = dirname( MCD_PLUGIN );
			if ( 'eyeonportal' !== $slug ) {
				return $this->update_error(
					'invalid_plugin_folder',
					sprintf(
						"EyeOn Portal is installed in the non-standard folder '%s'. It must live in 'wp-content/plugins/eyeonportal'. Please reinstall it into the 'eyeonportal' folder, then update again.",
						$slug
					),
					409
				);
			}

			$params       = $request->get_json_params();
			$package_data = $this->resolve_package_data( $params );
			if ( is_wp_error( $package_data ) ) {
				$status = $package_data->get_error_data();
				$code   = is_array( $status ) && isset( $status['status'] ) ? (int) $status['status'] : 400;

				return new WP_REST_Response(
					array(
						'success' => false,
						'code'    => $package_data->get_error_code(),
						'message' => $package_data->get_error_message(),
					),
					$code
				);
			}

			$target_version = ! empty( $params['target_version'] ) ? (string) $params['target_version'] : null;

			return $this->install_plugin_package(
				$package_data,
				MCD_PLUGIN_VERSION,
				$target_version
			);
		}

		private function update_error( $code, $message, $status = 500 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => $code,
					'message' => $message,
				),
				$status
			);
		}

		/**
		 * Recursively delete a directory. Best-effort; never throws.
		 */
		private function rrmdir( $dir ) {
			if ( empty( $dir ) || ! file_exists( $dir ) ) {
				return;
			}
			if ( is_link( $dir ) || ! is_dir( $dir ) ) {
				@unlink( $dir );
				return;
			}
			$items = @scandir( $dir );
			if ( is_array( $items ) ) {
				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}
					$path = $dir . '/' . $item;
					if ( is_dir( $path ) && ! is_link( $path ) ) {
						$this->rrmdir( $path );
					} else {
						@unlink( $path );
					}
				}
			}
			@rmdir( $dir );
		}

		/**
		 * Remove dev-only paths that may be present in GitHub zipballs.
		 * Release zips built by CI already omit these paths.
		 */
		private function remove_dev_paths_from_plugin_root( $root ) {
			$paths = array( '.git', '.cursor', '.github', '.vscode', '.gitignore' );
			foreach ( $paths as $path ) {
				$full = $root . '/' . $path;
				if ( is_dir( $full ) ) {
					$this->rrmdir( $full );
				} elseif ( is_file( $full ) ) {
					@unlink( $full );
				}
			}
		}

		/**
		 * Keep only the map release versions referenced by THREEJS_MAP_VERSION
		 * and THREEJS_MAP_V2_VERSION.
		 */
		private function remove_unused_map_releases_from_plugin_root( $root ) {
			$this->prune_map_release_dir(
				$root . '/assets/map-releases',
				$root . '/eyeonportal.php',
				'THREEJS_MAP_VERSION'
			);
			$this->prune_map_release_dir(
				$root . '/assets/map-v2-releases',
				$root . '/eyeonportal.php',
				'THREEJS_MAP_V2_VERSION'
			);
		}

		/**
		 * Keep only the active version directory under a map releases folder.
		 */
		private function prune_map_release_dir( $map_releases_dir, $plugin_php, $constant_name ) {
			if ( ! is_dir( $map_releases_dir ) ) {
				return;
			}

			$active_version = null;
			if ( is_file( $plugin_php ) ) {
				$content = @file_get_contents( $plugin_php );
				$pattern = "/OR define\s*\(\s*'" . preg_quote( $constant_name, '/' ) . "'\s*,\s*'([^']+)'\s*\)/";
				if ( is_string( $content ) && preg_match( $pattern, $content, $matches ) ) {
					$active_version = $matches[1];
				}
			}

			if ( ! $active_version ) {
				return;
			}

			$items = @scandir( $map_releases_dir );
			if ( ! is_array( $items ) ) {
				return;
			}

			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item || '.gitkeep' === $item ) {
					continue;
				}
				$path = $map_releases_dir . '/' . $item;
				if ( is_dir( $path ) && $item !== $active_version ) {
					$this->rrmdir( $path );
				}
			}
		}

		/**
		 * Find the directory that directly contains eyeonportal.php inside an
		 * extracted package. Handles both a normalized "eyeonportal/" root and
		 * GitHub's "owner-repo-<sha>/" zipball root.
		 */
		private function locate_plugin_root( $base ) {
			if ( file_exists( $base . '/eyeonportal.php' ) ) {
				return $base;
			}
			$items = @scandir( $base );
			if ( ! is_array( $items ) ) {
				return false;
			}
			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}
				$path = $base . '/' . $item;
				if ( is_dir( $path ) && file_exists( $path . '/eyeonportal.php' ) ) {
					return $path;
				}
			}
			return false;
		}

		/**
		 * Install the plugin package using native filesystem operations only.
		 *
		 * This deliberately avoids WP_Filesystem / Plugin_Upgrader (which fail
		 * with an opaque "Plugin upgrade failed." whenever the host cannot get
		 * "direct" filesystem access) and never deactivates the plugin. Files
		 * are extracted to a staging directory on the same filesystem as the
		 * plugins directory, then swapped in atomically with rename(), with a
		 * full rollback if anything goes wrong.
		 */
		private function install_plugin_package( $package_data, $previous_version, $target_version = null ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				return $this->update_error( 'zip_unavailable', 'The PHP ZipArchive extension is not available on this server.', 500 );
			}
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$slug        = dirname( MCD_PLUGIN );
			$plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
			$plugins_dir = rtrim( $plugins_dir, '/\\' );
			$plugin_dir  = $plugins_dir . '/' . $slug;
			$was_active  = is_plugin_active( MCD_PLUGIN );

			// Preflight: we must be able to write into the plugins directory and
			// (if present) the current plugin directory. Fail loudly and early,
			// before touching anything, if we cannot.
			if ( ! is_writable( $plugins_dir ) ) {
				return $this->update_error( 'plugins_dir_not_writable', 'The plugins directory is not writable by the web server: ' . $plugins_dir, 500 );
			}
			if ( is_dir( $plugin_dir ) && ! is_writable( $plugin_dir ) ) {
				return $this->update_error( 'plugin_dir_not_writable', 'The plugin directory is not writable by the web server: ' . $plugin_dir, 500 );
			}

			$work_id     = 'eyeon-upd-' . substr( md5( uniqid( '', true ) ), 0, 12 );
			$tmp_zip     = $plugins_dir . '/.' . $work_id . '.zip';
			$staging_dir = $plugins_dir . '/.' . $work_id;
			$backup_dir  = $plugins_dir . '/.' . $work_id . '-bak';

			$cleanup = function () use ( $tmp_zip, $staging_dir ) {
				if ( file_exists( $tmp_zip ) ) {
					@unlink( $tmp_zip );
				}
				$this->rrmdir( $staging_dir );
			};

			// 1) Persist the package to disk (same filesystem as the destination).
			if ( false === @file_put_contents( $tmp_zip, $package_data ) ) {
				$cleanup();
				return $this->update_error( 'write_failed', 'Could not write the plugin package to disk.', 500 );
			}
			unset( $package_data );

			// 2) Extract with the native ZipArchive (no WP_Filesystem required).
			$zip     = new ZipArchive();
			$opened  = $zip->open( $tmp_zip );
			if ( true !== $opened ) {
				$cleanup();
				return $this->update_error( 'invalid_zip', 'The downloaded package is not a valid zip archive (code ' . $opened . ').', 502 );
			}
			if ( ! is_dir( $staging_dir ) && ! @mkdir( $staging_dir, 0755, true ) && ! is_dir( $staging_dir ) ) {
				$zip->close();
				$cleanup();
				return $this->update_error( 'staging_failed', 'Could not create the staging directory for the update.', 500 );
			}
			if ( ! $zip->extractTo( $staging_dir ) ) {
				$zip->close();
				$cleanup();
				return $this->update_error( 'extract_failed', 'Could not extract the plugin package.', 500 );
			}
			$zip->close();

			// 3) Find and validate the new plugin root.
			$new_root = $this->locate_plugin_root( $staging_dir );
			if ( ! $new_root ) {
				$cleanup();
				return $this->update_error( 'plugin_entry_missing', 'The package does not contain eyeonportal.php.', 502 );
			}
			$this->remove_dev_paths_from_plugin_root( $new_root );
			$this->remove_unused_map_releases_from_plugin_root( $new_root );
			$new_data = get_file_data( $new_root . '/eyeonportal.php', array( 'version' => 'Version' ) );
			if ( empty( $new_data['version'] ) ) {
				$cleanup();
				return $this->update_error( 'invalid_plugin', 'The new plugin package is missing a version header.', 502 );
			}

			// 4) Atomic swap with rollback. rename() is atomic on the same
			//    filesystem, and staging/backup live inside the plugins dir.
			$had_existing = is_dir( $plugin_dir );
			if ( $had_existing ) {
				$this->rrmdir( $backup_dir );
				if ( ! @rename( $plugin_dir, $backup_dir ) ) {
					$cleanup();
					return $this->update_error( 'backup_failed', 'Could not move the current plugin aside for the update.', 500 );
				}
			}

			if ( ! @rename( $new_root, $plugin_dir ) ) {
				// Roll back to the previous version.
				if ( $had_existing && ! is_dir( $plugin_dir ) ) {
					@rename( $backup_dir, $plugin_dir );
				}
				$cleanup();
				$this->rrmdir( $backup_dir );
				return $this->update_error( 'install_failed', 'Could not install the new plugin files. The previous version has been restored.', 500 );
			}

			// 5) Success — remove backup and staging remnants.
			$this->rrmdir( $backup_dir );
			$cleanup();

			// 6) Invalidate caches so the next request loads the new code.
			clearstatcache( true );
			if ( function_exists( 'opcache_reset' ) ) {
				@opcache_reset();
			}
			if ( function_exists( 'wp_clean_plugins_cache' ) ) {
				wp_clean_plugins_cache( true );
			}

			// 7) Keep the plugin active (it was never deactivated).
			$this->ensure_plugin_active( $was_active );

			clearstatcache( true );
			$installed = get_file_data( $plugin_dir . '/eyeonportal.php', array( 'version' => 'Version' ) );

			return rest_ensure_response(
				array(
					'success'           => true,
					'previous_version'  => $previous_version,
					'installed_version' => ! empty( $installed['version'] ) ? $installed['version'] : $new_data['version'],
					'plugin_active'     => is_plugin_active( MCD_PLUGIN ),
					'message'           => 'Plugin updated successfully',
				)
			);
		}

		private function ensure_plugin_active( $was_active = true ) {
			// Respect the prior state: if the plugin was deactivated before the
			// update, leave it deactivated afterwards.
			if ( ! $was_active ) {
				return;
			}

			if ( ! function_exists( 'activate_plugin' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( is_plugin_active( MCD_PLUGIN ) ) {
				return;
			}

			// Activate silently. The main plugin file is already loaded in this
			// request, so a non-silent activation would run plugin_sandbox_scrape()
			// and re-include the file, which can fatal on redeclared symbols and
			// leave the plugin deactivated.
			$result = activate_plugin( MCD_PLUGIN, '', false, true );

			// Fallback: force the option directly if activation still failed.
			if ( is_wp_error( $result ) || ! is_plugin_active( MCD_PLUGIN ) ) {
				$active = get_option( 'active_plugins', array() );
				if ( ! in_array( MCD_PLUGIN, $active, true ) ) {
					$active[] = MCD_PLUGIN;
					update_option( 'active_plugins', $active );
				}
			}
		}
	}

	$eyeon_manage_wp = new EyeOnManageWp();
	$eyeon_manage_wp->register();
}
