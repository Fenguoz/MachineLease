<?php

namespace Fenguoz\MachineLease\Models;

use App\Models\Model;

class UsersMachineModel extends Model
{
	/**
	 * 数据表
	 *
	 * @var string
	 */
	protected $table = 'plugin_users_machine';

	/**
	 * 时间戳
	 *
	 * @var boolean
	 */
	public $timestamps = false;
}
