<?php

/*
 * Allows management of authors between the Source and Target sites and allow posting of content on behalf of authors on the Target site
 * @package Sync
 * @author Dave Jesch
 */
class SyncAuthorsAdmin
{
	private static $_instance = NULL;

	const META_TARGET_AUTHOR = '_spectrom_aync_target_author';
	const POST_TARGET_AUTHOR = 'sync_target_author';

	private function __construct()
	{
		// TODO: add filter 'spectrom_sync_validate_settings' and remove users when/if Target changes

		add_filter('spectrom_sync_ajax_operation', array($this, 'ajax_query'), 10, 3);

		// hook for displaying content in metabox
		add_action('spectrom_sync_metabox_operations', array($this, 'add_attributor_to_metabox'), 100, 1);
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
	 * Checks if the current ajax operation is for this plugin
	 * @param  boolean $found Return TRUE or FALSE if the operation is found
	 * @param  string $operation The type of operation requested
	 * @param  SyncApiResponse $response The response to be sent
	 * @return boolean Return TRUE if the current ajax operation is for this plugin, otherwise return $found
	 */
	public function ajax_query($found, $operation, SyncApiResponse $response)
	{
		// TODO: can be removed
//SyncDebug::log(__METHOD__.'() operation=' . $operation);
		if ('get_attributors' === $operation) {
			$found = TRUE;

			$attrib_model = new SyncAuthorsModel();
			$attributors = $attrib_model->get_attributors(TRUE);
//SyncDebug::log(__METHOD__.'() attributors: ' . var_export($attributors, TRUE));

			if (NULL === $attributors) {
//SyncDebug::log(__METHOD__.'() error retrieving authors');
				// error returned
				$response->error_code($attrib_model->get_error_code());
//SyncDebug::log(__METHOD__.'() setting error message ' . $attrib_model->get_message());
				$response->set('error_message', $attrib_model->get_message());
			} else {
				$response->success(TRUE);
				$response->set('attributors', $attributors);
			}
//SyncDebug::log(__METHOD__.'() responding with: ' . var_export($response, TRUE));
		}

		return $found;
	}

	/**
	 * Adds the username of the Author to the Sync metabox
	 * @param boolean $error TRUE or FALSE depending on whether the connection settings are correct
	 */
	public function add_attributor_to_metabox($error)
	{
		if ($error)
			return;

		global $post;
		$author_id = $post->post_author;
		$user = new WP_User($author_id);
		if (FALSE !== $user) {
			echo '<p>', PHP_EOL;
			$display_name = trim($user->first_name . ' ' . $user->last_name);
			if (!empty($display_name))
				$display_name = '(' . $display_name . ')';
			printf(__('Syncing as Author: %1$s %2$s', 'wpsitesync-authors'),
				$user->user_nicename, $display_name);
			echo '</p>', PHP_EOL;
		}
	}
}

// EOF