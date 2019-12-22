<?php

use Fenguoz\MachineLease\Models\OrderQueueModel;

class PluginMachineleaseHook
{
	public function paySuccess(&$order_info)
	{
		return OrderQueueModel::insert([
			'order_sn' => $order_info['order_sub']['order_sn'],
			'order_sub_sn' => $order_info['order_sub']['sub_sn']
		]);
	}
}
