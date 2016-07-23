<?php

class SyncAuthorApiRequest
{
	const NOTICE_AUTHOR_ACCOUNT_EXISTS = 201;

	/**
	 * Processes request data on Target in response to API request
	 * @param int $target_post_id The ID of the Post on the Target
	 * @param array $data The data array passed in from the API request
	 * @param SyncApiResponse $response The response instance
	 */
	public function process_request($target_post_id, $data, $response)
	{
SyncDebug::log(__METHOD__.'(' . $target_post_id . ') post=' . var_export($_POST, TRUE));
		if (!isset($_POST['author_data'])) {
SyncDebug::log(__METHOD__.'() no author_data found');
			return;
		}

		$new_author_id = NULL;

		$input = new SyncInput();
		$author = $input->post_raw('author_data'); //  $data['author_data'];
		$user_login = get_user_by('login', $author['user_login']);
		$user_email = get_user_by('email', $author['user_email']);
		$user_slug = get_user_by('slug', $author['user_nicename']);
		if (FALSE === $user_login && FALSE === $user_email && FALSE === $user_slug) {
			// user not found by login, email or display name
SyncDebug::log('user not found - creating');
			// create the user
			$role = 'author';
			if (isset($author['roles']) && count($author['roles']) > 0)
				$role = $author['roles'][0];

			$data = array(
				'user_login' => $author['user_login'],
				'user_pass' => $author['user_pass'],
				'user_nicename' => $author['user_nicename'],
				'user_email' => $author['user_email'],
				'first_name' => $author['first_name'],
				'last_name' => $author['last_name'],
				'role' => $role,
			);
			$id = wp_insert_user($data);
			if (!is_wp_error($id)) {
SyncDebug::log('- created user id' . $id);
				$new_author_id = $id;
				// TODO: reset password
			}
		} else {
			$checks = 0;
			if (FALSE === $user_login)
				++$checks;
			if (FALSE === $user_email)
				++$checks;
			if (FALSE === $user_slug)
				++$checks;

			if (0 !== $checks ||
				(FALSE !== $user_login && FALSE !== $user_email && $user_login->ID !== $user_email->ID) ||
				(FALSE !== $user_login && FALSE !== $user_slug && $user_login->ID !== $user_slug->ID)) {
				// the IDs of the user searches do not match; return error indicator to Source
SyncDebug::log(__METHOD__.'() non-matching attributes');
				$response->notice_code(self::NOTICE_AUTHOR_ACCOUNT_EXISTS);
			} else {
SyncDebug::log(__METHOD__.'() user exists, updating post to author ' . $user_login->ID);
				// the user exists and the login name, email and name match. Update the author information
				$new_author_id = $user_login->ID;
			}
		}

		if (NULL !== $new_author_id) {
			$data = array(
				'ID' => $target_post_id,
				'post_author' => $new_author_id,
			);
SyncDebug::log(__METHOD__.'() calling wp_update_post() with ' . var_export($data, TRUE));
			wp_update_post($data);
		}
	}
}

// EOF