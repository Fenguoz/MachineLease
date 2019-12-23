<?php

namespace Fenguoz\MachineLease\Models;

use App\Models\Model;

class OrderQueueModel extends Model
{
	/**
	 * 数据表
	 *
	 * @var string
	 */
	protected $table = 'plugin_order_queue';

	/**
	 * 时间戳
	 *
	 * @var boolean
	 */
	public $timestamps = false;
}
