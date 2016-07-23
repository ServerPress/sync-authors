<?php
/*
Plugin Name: WPSiteSync for Authors
Plugin URI: http://wpsitesync.com
Description: Allow setting author/attribution while Synchronizing content between the Source and Target sites using WPSiteSync for Content.
Author: WPSiteSync
Author URI: http://wpsitesync.com
Version: 1.0
Text Domain: wpsitesync-authors

The PHP code portions are distributed under the GPL license. If not otherwise stated, all
images, manuals, cascading stylesheets and included JavaScript are NOT GPL.
*/

if (!class_exists('WPSiteSync_Authors')) {
	/*
	 * @package WPSiteSync_Authors
	 * @author Dave Jesch
	 */
	class WPSiteSync_Authors
	{
		private static $_instance = NULL;

		const PLUGIN_NAME = 'WPSiteSync for Authors';
		const PLUGIN_VERSION = '1.0';
		const PLUGIN_KEY = '115e12f6e84055cafdf05c3d1ce0bd3a';

		private $_license = NULL;

		private function __construct()
		{
			add_action('spectrom_sync_init', array(&$this, 'init'));
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
			add_filter('spectrom_sync_active_extensions', array(&$this, 'filter_active_extensions'), 10, 2);

			$this->_license = new SyncLicensing();
			if (!$this->_license->check_license('sync_authors', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return;

			if (is_admin()) {
				require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'authorsadmin.php');
				require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'authorsmodel.php');
				SyncAuthorsAdmin::get_instance();
			}

			add_filter('spectrom_sync_api', array(&$this, 'check_api_query'), 20, 3);
			add_action('spectrom_sync_push_content', array(&$this, 'process_push_request'), 20, 3);
			add_filter('spectrom_sync_notice_code_to_text', array(&$this, 'filter_notice_code'), 10, 2);
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
		 * Checks the API request if the action is to get the authors
		 * @param boolean $return The return value. TRUE indicates API was processed; otherwise FALSE
		 * @param string $action The API requested
		 * @param SyncApiResponse $response Instance of SyncAjaxResponse
		 */
		// TODO: move this logic to a SyncAuthorsApiRequest class
		// TODO: ensure only called once Sync is initialized
		public function check_api_query($return, $action, SyncApiResponse $response)
		{
			// TODO: can be removed
			if (!$this->_license->check_license('sync_authors', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return $return;

			$input = new SyncInput();
SyncDebug::log(__METHOD__.'() action=' . $action);

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
		 * Handles processing of push requests. Called from SyncApiController->push()
		 * @param int $target_post_id Post ID on Target site
		 * @param array $data Data array to be sent with API request
		 * @param SyncApiResponse $response The Response instance
		 */
		public function process_push_request($target_post_id, $data, $response)
		{
			if (!$this->_license->check_license('sync_authors', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return $return;
			require_once(dirname(__FILE__) . '/classes/authorapirequest.php');
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
			require_once(dirname(__FILE__) . '/classes/authorapirequest.php');
			switch ($code) {
			case SyncAuthorApiRequest::NOTICE_AUTHOR_ACCOUNT_EXISTS:		$msg = __('Cannot Sync Author- account already exists.', 'wpsitesync-authors'); break;
			}
			return $msg;
		}

		/**
		 * Adds the WPSiteSync Pull add-on to the list of known WPSiteSync extensions
		 * @param array $extensions The list of extensions
		 * @param boolean TRUE to force adding the extension; otherwise FALSE
		 * @return array Modified list of extensions
		 */
		public function filter_active_extensions($extensions, $set = FALSE)
		{
			if ($set || $this->_license->check_license('sync_authors', self::PLUGIN_KEY, self::PLUGIN_NAME))
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