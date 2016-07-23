<?php

class SyncAuthorsModel
{
	private $_api_response = NULL;
	private $_error_code = 0;
	private $_error_message = NULL;

	/**
	 * Gets the list of fetched attributors
	 * @param boolean $force_fetch Whether or not to fetch the list from the target site, regardless if cached data exists
	 * @return array|NULL Array of user data on success; otherwise NULL
	 */
	public function get_attributors($force_fetch = FALSE)
	{
		// TODO: can be removed
		// TODO: check callers and ensure they're looking for WP_Error return values

		$api = new SyncApiRequest();
		$host_url = parse_url($api->host, PHP_URL_HOST);
		$transient_key = 'spectrom_sync_authors_' . $host_url;

		$attributors_cached = get_transient($transient_key);

		if (FALSE === $attributors_cached || $force_fetch) {
			$response = $api->api('getauthors');
SyncDebug::log(__METHOD__.'(): api response: ' . var_export($response, TRUE));
			$this->_api_response = $response;

			if (0 !== $response->response->error_code) {
SyncDebug::log(__METHOD__.'() got an error');
				$this->_error_code = $response->response->error_code;
SyncDebug::log(' - error code=' . $this->_error_code);
				switch ($this->_error_code) {
				case SyncApiRequest::ERROR_UNRECOGNIZED_REQUEST:
					$this->_error_message = __('Error: The WPSiteSync Authors add-on is not active on the Target site.', 'wpsitesync-authors');
					break;
				default:
					$this->_error_message = SyncApiRequest::error_code_to_string($this->_error_code);
					break;
				}
SyncDebug::log(__METHOD__.'() code=' . $this->_error_code . ' msg=' . $this->_error_message);
				return NULL;
			}
//			if (is_wp_error($response))
//				return $response;

			$attrib = array();
			if (isset($response->response)) {
				$api_response = $response->response;
				if (isset($api_response->success) && 1 == $api_response->success) {
					if (isset($api_response->data->attributors)) {
						$attrib = $api_response->data->attributors;
if (0 === count($attrib))
	SyncDebug::log(__METHOD__.'() empty attributor list');
					} else
						SyncDebug::log(__METHOD__.'() no attributor data found');
				}
			}
			$attributors_cached = $attrib;

			// save to cache
			// TODO: make TTL a class constant
			// TODO: add button in UI to flush transient
			set_transient($transient_key, $attributors_cached, 24 * HOUR_IN_SECONDS);
		}
		return $attributors_cached;
	}

	public function get_error_code()
	{
		return $this->_error_code;
	}

	public function get_message()
	{
		return $this->_error_message;
	}

	public function get_response()
	{
		return $this->_api_response;
	}
}

// EOF