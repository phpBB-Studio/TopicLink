<?php
/**
 *
 * phpBB Studio - Topic links. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2020, phpBB Studio, https://www.phpbbstudio.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace phpbbstudio\tlink\migrations;

class install_permissions extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritdoc
	 */
	public function effectively_installed()
	{
		$sql = 'SELECT * FROM ' . $this->table_prefix . "acl_options
			WHERE auth_option = 'u_phpbbstudio_tlink'";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row !== false;
	}

	/**
	 * {@inheritdoc
	 */
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v32x\v325'];
	}

	/**
	 * {@inheritdoc
	 */
	public function update_data()
	{
		return [['permission.add', ['u_phpbbstudio_tlink']]];
	}
}
