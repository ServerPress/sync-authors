<?php
/*
Plugin Name: WPSiteSync for Authors
Plugin URI: http://wpsitesync.com
Description: Allow setting author/attribution while Synchronizing content between the Source and Target sites using WPSiteSync for Content.
Author: WPSiteSync
Author URI: http://wpsitesync.com
Version: 1.0.2
Text Domain: wpsitesync-authors

The PHP code portions are distributed under the GPL license. If not otherwise stated, all
images, manuals, cascading stylesheets and included JavaScript are NOT GPL.
*/

if (!class_exists('WPSiteSync_Authors', FALSE)) {
	/*
	 * @package WPSiteSync_Authors
	 * @author Dave Jesch
	 */
	class WPSiteSync_Authors
	{
		private static $_instance = NULL;

		const PLUGIN_NAME = 'WPSiteSync for Authors';
		const PLUGIN_VERSION = '1.0.1';
		const PLUGIN_KEY = '115e12f6e84055cafdf05c3d1ce0bd3a';

		private function __construct()
		{
			add_action('spectrom_sync_init', array($this, 'init'));
			add_action('wp_loaded', array($this, 'wp_loaded'));
		}

		/*
		 * retrieve singleton class instance
		 * @return instance reference to plugin
		 */
		public static function get_instance()
		{
			if (NULL === self::$_instance)
				self::$_instance = new self();
			return self::$_instance;
		}

		/**
		 * Initialize the WPSiteSync authors plugin
		 */
		public function init()
		{
//SyncDebug::log(__METHOD__.'():' . __LINE__);
			add_filter('spectrom_sync_active_extensions', array($this, 'filter_active_extensions'), 10, 2);

			if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_authors', self::PLUGIN_KEY, self::PLUGIN_NAME)) {
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' no license');
				return;
			}

			if (is_admin()) {
				require_once __DIR__ . '/classes/authorsadmin.php';
				require_once __DIR__ . '/classes/authorsmodel.php';
				SyncAuthorsAdmin::get_instance();
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' SyncAuthorsAdmin loaded');
			}

			// TODO: move into 'spectrom_sync_api_init' callback
			add_filter('spectrom_sync_api', array($this, 'check_api_query'), 20, 3);
			add_filter('spectrom_sync_api_push_content', array($this, 'filter_push_request'), 10, 2);		// moved from SyncAuthorsAdmin
			add_action('spectrom_sync_push_content', array($this, 'process_push_request'), 20, 3);
			add_filter('spectrom_sync_notice_code_to_text', array($this, 'filter_notice_code'), 10, 2);
		}

		/**
		 * Called when WP is loaded so we can check if parent plugin is active.
		 */
		public function wp_loaded()
		{
			if (is_admin() && !class_exists('WPSiteSyncContent', FALSE) && current_user_can('activate_plugins')) {
				add_action('admin_notices', array($this, 'notice_requires_wpss'));
				add_action('admin_init', array($this, 'disable_plugin'));
			}
		}

		/**
		 * Displays the warning message stating that WPSiteSync is not present.
		 */
		public function notice_requires_wpss()
		{
			$install = admin_url('plugin-install.php?tab=search&s=wpsitesync');
			$activate = admin_url('plugins.php');
			$msg = sprintf(__('The <em>WPSiteSync for Authors</em> plugin requires the main <em>WPSiteSync for Content</em> plugin to be installed and activated. Please %1$sclick here</a> to install or %2$sclick here</a> to activate.', 'wpsitesync-authors'),
						'<a href="' . $install . '">',
						'<a href="' . $activate . '">');
			$this->_show_notice($msg, 'notice-warning');
		}

		/**
		 * Helper method to display notices
		 * @param string $msg Message to display within notice
		 * @param string $class The CSS class used on the <div> wrapping the notice
		 * @param boolean $dismissable TRUE if message is to be dismissable; otherwise FALSE.
		 */
		private function _show_notice($msg, $class = 'notice-success', $dismissable = FALSE)
		{
			echo '<div class="notice ', $class, ' ', ($dismissable ? 'is-dismissible' : ''), '">';
			echo '<p>', $msg, '</p>';
			echo '</div>';
		}

		/**
		 * Disables the plugin if WPSiteSync not installed or ACF is too old
		 */
		public function disable_plugin()
		{
			deactivate_plugins(plugin_basename(__FILE__));
		}

		/*
		 * Return reference to asset, relative to the base plugin's /assets/ directory
		 * @param string $ref asset name to reference
		 * @return string href to fully qualified location of referenced asset
		 */
		public static function get_asset($ref)
		{
			$ret = plugin_dir_url(__FILE__) . 'assets/' . $ref;
			return $ret;
		}

		/**
		 * Filters the post data Content on the Source before it's sent to the Target.
		 * @param array $data All of the post data
		 * @param SyncApiRequest $api_request The API request instance making the API call
		 * @return array The filtered data
		 */
		public function filter_push_request($data, $api_request)
		{
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' post id=' . @$data['post_data']['ID']);
			$author_id = abs($data['post_data']['post_author']);
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' author id=' . $author_id);
			if ($author_id > 0) {
				$user = new WP_User($author_id);

				// make sure it's a valid user instance
				if (is_a($user, 'WP_User') && 0 !== $user->ID) {
					$user_data = array(
						'roles' => $user->roles,
						'first_name' => $user->first_name,
						'last_name' => $user->last_name,
						'user_login' => $user->user_login,
						'user_pass' => $user->user_pass,
						'user_nicename' => $user->user_nicename,
						'user_email' => $user->user_email,
						'display_name' => $user->dislay_name,
					);
//SyncDebug::log(__METHOD__.'() user=' . var_export($user, TRUE));
					$data['author_data'] = $user_data;
				} else {
//SyncDebug::log(__METHOD__.'() unable to find user id ' . $author_id);
				}
			} else {
//SyncDebug::log(__METHOD__.'() no valid author id found');
			}
			return $data;
		}

		/**
		 * Checks the API request if the action is to get the authors
		 * @param boolean $return The return value. TRUE indicates API was processed; otherwise FALSE
		 * @param string $action The API requested
		 * @param SyncApiResponse $response Instance of SyncAjaxResponse
		 */
		// TODO: move this logic to a SyncAuthorsApiRequest class
		// TODO: ensure only called once Sync is initialized
		public function check_api_query($return, $action, SyncApiResponse $response)
		{
			if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_authors', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return $return;

			$input = new SyncInput();
//SyncDebug::log(__METHOD__.'() action=' . $action);

			if ('getauthors' === $action) {
				// TODO: nonce verification to be done in SyncApiController::__construct() so we don't have to do it here
//				if (!wp_verify_nonce($input->get('_spectrom_sync_nonce'), $input->get('site_key'))) {
//					$response->error_code(SyncApiRequest::ERROR_SESSION_EXPIRED);
////					$response->success(FALSE);
////					$response->set('errorcode', SyncApiRequest::ERR_INVALID_NONCE);
//					return;
//				}

				$all_users = get_users(array('fields' => 'all_with_meta'));
				$attributors = array();

				foreach ($all_users as $user) {
					// check that user has capability to publish posts
				    if ($user->has_cap('publish_posts')) {
				    	$_user = array(
				    		'user_id' => $user->ID,
				    		'user_nicename' => $user->user_nicename,
				    		'user_firstname' => $user->first_name,
				    		'user_lastname' => $user->last_name,
				    	);

				        $attributors[] = $_user;
				    }
				}

				$response->set('attributors', $attributors);
				$response->success(TRUE);

				$return = TRUE;			// notify Sync core that we handled the request
			}

			// return the filter value
			return $return;
		}

		/**
		 * Handles processing of push requests on Target. Called from SyncApiController->push()
		 * @param int $target_post_id Post ID on Target site
		 * @param array $data Data array to be sent with API request
		 * @param SyncApiResponse $response The Response instance
		 */
		public function process_push_request($target_post_id, $data, $response)
		{
//SyncDebug::log(__METHOD__.'():' . __LINE__ . ' target post=' . $target_post_id);
			if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_authors', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return;
			require_once __DIR__ . '/classes/authorapirequest.php';
			$req = new SyncAuthorApiRequest();
			$req->process_request($target_post_id, $data, $response);
		}

		/**
		 * Converts numeric notice code to message string
		 * @param string $msg Notice message
		 * @param int $code The notice code to convert
		 * @return string Modified message if one of WPSiteSync Authors' notice codes
		 */
		public function filter_notice_code($msg, $code)
		{
			require_once __DIR__ . '/classes/authorapirequest.php';
			switch ($code) {
			case SyncAuthorApiRequest::NOTICE_AUTHOR_ACCOUNT_EXISTS:		$msg = __('Cannot Sync Author- account already exists.', 'wpsitesync-authors'); break;
			}
			return $msg;
		}

		/**
		 * Adds the WPSiteSync Authors add-on to the list of known WPSiteSync extensions
		 * @param array $extensions The list of extensions
		 * @param boolean TRUE to force adding the extension; otherwise FALSE
		 * @return array Modified list of extensions
		 */
		public function filter_active_extensions($extensions, $set = FALSE)
		{
			if ($set || WPSiteSyncContent::get_instance()->get_license()->check_license('sync_authors', self::PLUGIN_KEY, self::PLUGIN_NAME))
				$extensions['sync_authors'] = array(
					'name' => self::PLUGIN_NAME,
					'version' => self::PLUGIN_VERSION,
					'file' => __FILE__,
				);
			return $extensions;
		}
	}
}

// Initialize the extension
WPSiteSync_Authors::get_instance();

// EOF