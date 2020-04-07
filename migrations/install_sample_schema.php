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

class install_sample_schema extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritdoc
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'topics', 'tlink');
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
	public function update_schema()
	{
		return [
			'add_columns'	=> [
				$this->table_prefix . 'topics'	=> [
					'tlink'				=> ['VCHAR_UNI', null],
				],
			],
		];
	}

	/**
	 * {@inheritdoc
	 */
	public function revert_schema()
	{
		return [
			'drop_columns'	=> [
				$this->table_prefix . 'topics'	=> [
					'tlink',
				],
			],
		];
	}
}
