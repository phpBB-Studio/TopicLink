<?php
/**
 *
 * phpBB Studio - Topic links. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2020, phpBB Studio, https://www.phpbbstudio.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbstudio\tlink\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * phpBB Studio - Topic links Event listener.
 */
class main implements EventSubscriberInterface
{
	/* @var \phpbb\auth\auth */
	protected $auth;

	/* @var \phpbb\language\language */
	protected $language;

	/* @var \phpbb\request\request */
	protected $request;

	public static function getSubscribedEvents()
	{
		return array(
			'core.user_setup_after'						=> 'tlink_load_language_on_setup',
			'core.permissions'							=> 'tlink_add_permissions',
			/* Posting */
			'core.posting_modify_submission_errors'		=> 'tlink_posting_modify_submission_errors',
			'core.posting_modify_template_vars'			=> 'tlink_posting_modify_template_vars',
			'core.posting_modify_submit_post_before'	=> 'tlink_posting_modify_submit_post_before',
			'core.submit_post_modify_sql_data'			=> 'tlink_submit_post_modify_sql_data',
			/* Forums */
			'core.viewforum_modify_topicrow'			=> 'tlink_viewforum_modify_topicrow',
			'core.display_forums_modify_forum_rows'		=> 'tlink_display_forums_modify_forum_rows',
			'core.display_forums_modify_sql'			=> 'tlink_display_forums_modify_sql',
			'core.display_forums_modify_template_vars'	=> 'tlink_display_forums_modify_template_vars',
			/* Search */
			'core.search_modify_tpl_ary'				=> 'tlink_search_modify_tpl_ary',
		);
	}

	/**
	 * Constructor
	 *
	 * @param \phpbb\language\language	$language	Language object
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\language\language $language, \phpbb\request\request $request)
	{
		$this->auth			= $auth;
		$this->language		= $language;
		$this->request		= $request;
	}

	/**
	 * Load common language files after user setup
	 *
	 * @event core.user_setup_after
	 * @return void
	 */
	public function tlink_load_language_on_setup()
	{
		$this->language->add_lang('common', 'phpbbstudio/tlink');
	}

	/**
	 * Add permissions to the "ACP / Permissions settings" page
	 *
	 * @event core.permissions
	 * @param \phpbb\event\data		$event		The event object
	 * @return void
	 */
	public function tlink_add_permissions(\phpbb\event\data $event)
	{
		$categories = $event['categories'];
		$permissions = $event['permissions'];

		if (empty($categories['phpbb_studio']))
		{
			/* Set up a custom cat. tab */
			$categories['phpbb_studio'] = 'ACL_CAT_PHPBB_STUDIO';

			$event['categories'] = $categories;
		}

		$perms = [
			'u_phpbbstudio_tlink',
		];

		foreach ($perms as $permission)
		{
			$permissions[$permission] = ['lang' => 'ACL_' . utf8_strtoupper($permission), 'cat' => 'phpbb_studio'];
		}

		$event['permissions'] = $permissions;
	}

	/**
	 * Check input for the tlink
	 *
	 * @event core.posting_modify_submission_errors
	 * @param \phpbb\event\data	$event		The event object
	 * @return void
	 */
	public function tlink_posting_modify_submission_errors(\phpbb\event\data $event)
	{
		$error = $event['error'];

		$url = $this->request->variable('tlink', '', true);

		$valid = $this->is_url($url);

		if (!empty($url) && !$valid)
		{
			$error[] = $this->language->lang('URL_INVALID');
		}

		$event['error'] = $error;
	}

	/**
	 * Accept input for the tlink in the topic starter
	 *
	 * @event core.posting_modify_template_vars
	 * @param \phpbb\event\data	$event		The event object
	 * @return void
	 */
	public function tlink_posting_modify_template_vars(\phpbb\event\data $event)
	{
		$mode = $event['mode'];
		$post_data = $event['post_data'];
		$page_data = $event['page_data'];

		$post_data['tlink'] = (!empty($post_data['tlink'])) ? $post_data['tlink'] : '';

		if ($mode == 'post' || ($mode == 'edit' && $post_data['topic_first_post_id'] == $post_data['post_id']))
		{
			$page_data['TOPIC_LINK']	= $this->request->variable('tlink', $post_data['tlink'], true);
			$page_data['S_TOPIC_LINK']	= (bool) $this->auth->acl_get('u_phpbbstudio_tlink');
		}

		$event['page_data']	= $page_data;
	}

	/**
	 * Add the tlink to the post data
	 *
	 * @event core.posting_modify_submit_post_before
	 * @param \phpbb\event\data	$event		The event object
	 * @return void
	 */
	public function tlink_posting_modify_submit_post_before(\phpbb\event\data $event)
	{
		if (($event['mode'] === 'edit') && $event['post_data']['topic_first_post_id'] == $event['post_id'])
		{
			$url = $this->request->variable('tlink', '', true);

			if (!empty($url))
			{
				$url = $this->clean_url($url);
			}

			$event->update_subarray('post_data', 'tlink', $url);
		}
	}

	/**
	 * Add the tlink to the topic's SQL data
	 *
	 * @event core.submit_post_modify_sql_data
	 * @param \phpbb\event\data	$event		The event object
	 * @return void
	 */
	public function tlink_submit_post_modify_sql_data(\phpbb\event\data $event)
	{
		if (
			in_array($event['post_mode'], ['edit_first_post', 'edit_topic', 'post'], true)
			&&
			$event['data']['topic_first_post_id'] == $event['data']['post_id']
		)
		{
			$url = $this->request->variable('tlink', '', true);

			if (!empty($url))
			{
				$url = $this->clean_url($url);
			}

			$sql_data = $event['sql_data'];

			$sql_data[TOPICS_TABLE]['sql']['tlink'] = $url;

			if ($url)
			{
				$sql_data[TOPICS_TABLE]['sql']['topic_status'] = ITEM_LOCKED;
			}
			else
			{
				$sql_data[TOPICS_TABLE]['sql']['topic_status'] = ITEM_UNLOCKED;
			}

			$event['sql_data'] = $sql_data;
		}
	}

	/**
	 * Use tlink on topic title in viewforum
	 *
	 * @event core.viewforum_modify_topicrow
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function tlink_viewforum_modify_topicrow(\phpbb\event\data $event)
	{
		if ($event['row']['tlink'] != '')
		{
			$tpl_array = [
				'U_VIEW_TOPIC'		=> $event['row']['tlink'],
				'TOPIC_IMG_STYLE'	=> 'forum_link',
				'VIEWS'				=> '',
				'REPLIES'			=> '',
			];

			foreach ($tpl_array as $key => $value)
			{
				$event->update_subarray('topic_row', $key, $value);
			}

			/* If not authed for edit make the last post URL as tlink */
			if (!$this->auth->acl_get('u_phpbbstudio_tlink'))
			{
				$event->update_subarray('topic_row', 'U_LAST_POST', $event['row']['tlink']);
			}
		}
	}

	/**
	 * Get the last post data for forums
	 *
	 * @event core.display_forums_modify_sql
	 * @param \phpbb\event\data	$event		The event object
	 * @return	void
	 */
	public function tlink_display_forums_modify_sql(\phpbb\event\data $event)
	{
		$sql_array = $event['sql_ary'];

		$sql_array['LEFT_JOIN'][] = [
			'FROM' => [TOPICS_TABLE => 't'],
			'ON' => 'f.forum_last_post_id = t.topic_last_post_id'
		];

		$sql_array['SELECT'] .= ', t.tlink';

		$event['sql_ary'] = $sql_array;
	}

	/**
	 * Store last post data in the forum rows
	 *
	 * @event core.display_forums_modify_forum_rows
	 * @param \phpbb\event\data	$event		The event object
	 * @return	void
	 */
	public function tlink_display_forums_modify_forum_rows(\phpbb\event\data $event)
	{
		$forum_rows = $event['forum_rows'];

		if (
			($event['row']['forum_last_post_time'] == $forum_rows[$event['parent_id']]['forum_last_post_time'])
			&&
			$event['row']['tlink'] != ''
			&&
			!$this->auth->acl_get('u_phpbbstudio_tlink')
		)
		{
			$forum_rows[$event['parent_id']]['topic_id_last_post'] = $event['row']['tlink'];

			$event['forum_rows'] = $forum_rows;
		}
	}

	/**
	 * Do not show last post subject in forums if not authorized
	 *
	 * @event core.display_forums_modify_template_vars
	 * @param \phpbb\event\data	$event		The event object
	 * @return	void
	 */
	public function tlink_display_forums_modify_template_vars(\phpbb\event\data $event)
	{
		if (!$this->auth->acl_get('u_phpbbstudio_tlink') && $event['row']['tlink'] != '')
		{
			$event->update_subarray('forum_row', 'U_LAST_POST', $event['row']['tlink']);
		}
	}

	/**
	 * Alter the search results for not authorized users
	 *
	 * @event  core.search_modify_tpl_ary
	 * @param  \phpbb\event\data	$event		The event object
	 * @return void
	 */
	public function tlink_search_modify_tpl_ary(\phpbb\event\data $event)
	{
		if ($event['show_results'] === 'posts')
		{
			if ($event['row']['tlink'] != '')
			{
				if (!$this->auth->acl_get('u_phpbbstudio_tlink'))
				{
					$tpl_array = [
						'U_LAST_POST'		=> $event['row']['tlink'],
						'TOPIC_TITLE'		=> $event['row']['tlink'],
						'U_VIEW_POST'		=> $event['row']['tlink'],
					];

					foreach ($tpl_array as $key => $value)
					{
						$event->update_subarray('tpl_ary', $key, $value);
					}
				}
			}
		}

		if ($event['show_results'] === 'topics')
		{
			if ($event['row']['tlink'] != '')
			{
				if (!$this->auth->acl_get('u_phpbbstudio_tlink'))
				{
					$tpl_array = [
						'U_LAST_POST'		=> $event['row']['tlink'],
						'U_VIEW_TOPIC'		=> $event['row']['tlink'],
						'TOPIC_IMG_STYLE'	=> 'forum_link',
					];
				}
				else
				{
					$tpl_array = [
						'U_VIEW_TOPIC'		=> $event['row']['tlink'],
						'TOPIC_IMG_STYLE'	=> 'forum_link',
					];
				}

				foreach ($tpl_array as $key => $value)
				{
					$event->update_subarray('tpl_ary', $key, $value);
				}
			}
		}
	}

	/**
	 * Check wether the URL is valid
	 *
	 * @param  string $url    URL to be checked
	 * @return bool           True if valid false otherwise
	 */
	public function is_url($url)
	{
		$valid = false;

		$url = str_replace(' ', '%20', $url);

		if (
			preg_match('#^' . get_preg_expression('url') . '$#iu', $url) ||
			preg_match('#^' . get_preg_expression('www_url') . '$#iu', $url) ||
			preg_match('#^' . preg_quote(generate_board_url(), '#') . get_preg_expression('relative_url') . '$#iu', $url)
		)
		{
			$valid = true;
		}

		return (bool) $valid;
	}

	/**
	 * Clean up URL
	 *
	 * @param  string $url    URL to be parsed
	 * @return string         The parsed URL
	 */
	public function clean_url($url)
	{
		/* Remove the session ID if present */
		$url = preg_replace('/(&amp;|\?)sid=[0-9a-f]{32}&amp;/', '\1', $url);
		$url = preg_replace('/(&amp;|\?)sid=[0-9a-f]{32}$/', '', $url);

		/* If there is no URL scheme then add the http one */
		if (!preg_match('#^[a-z][a-z\d+\-.]*:/{2}#i', $url))
		{
			$url = 'http://' . $url;
		}

		return (string) $url;
	}
}
