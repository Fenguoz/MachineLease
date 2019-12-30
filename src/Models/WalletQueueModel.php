<?php

namespace Fenguoz\MachineLease\Models;

use App\Models\Model;

class WalletQueueModel extends Model
{
	/**
	 * 数据表
	 *
	 * @var string
	 */
	protected $table = 'plugin_wallet_queue';

	/**
	 * 时间戳
	 *
	 * @var boolean
	 */
	public $timestamps = false;
}
