<?php

namespace Fenguoz\MachineLease\Models;

use App\Models\Model;

class UsersModel extends Model
{
	/**
	 * 数据表
	 *
	 * @var string
	 */
	protected $table = 'plugin_users';

	/**
	 * 时间戳
	 *
	 * @var boolean
	 */
	public $timestamps = false;
}
