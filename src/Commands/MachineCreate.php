<?php

namespace Fenguoz\MachineLease\Commands;

use App\Exceptions\Common\CommonException;
use App\Models\Order\OrderSkuModel;
use Exception;
use Fenguoz\MachineLease\Models\OrderQueueModel;
use Fenguoz\MachineLease\Models\RewardRuleModel;
use Fenguoz\MachineLease\Models\UsersMachineModel;
use Fenguoz\MachineLease\Models\UsersModel;
use Fenguoz\MachineLease\Models\WalletQueueModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MachineCreate extends Command
{
    /**
     * The name and signature of the console command. 
     *
     * @var string
     */
    protected $signature = 'machine:create {client_id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成矿机';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        request()->attributes->add([
            'client_id' => $this->argument('client_id'),
        ]);

        $count = 10;
        $time = time();
        $data = OrderQueueModel::where([
            'status' => 0
        ])
            ->limit($count)
            ->get();

        if (count($data) == 0) exit("MachineCreate:暂无需处理项！");

        foreach ($data as $k => $v) {
            $skus = OrderSkuModel::where('sub_sn', $v->order_sub_sn)->get();

            if (!$skus) continue;

            $machine_data = [];
            foreach ($skus as $sku) {
                if ($sku->is_machine == 0) continue;

                $sku_info = $this->goods((int) $sku->sku_id);
                if (!isset($sku_info[0])) continue;
                $machine_data[] = [
                    'user_id' => $sku->buyer_id,
                    'order_sn' => $sku->order_sn,
                    'order_sub_sn' => $sku->sub_sn,
                    'sku_id' => $sku_info[0]['id'],
                    'sku_name' => $sku_info[0]['title'],
                    'start_time' => strtotime(date('Y-m-d 0:0:0', time())) + 86400, //次日生效
                    'expired_time' => strtotime(date('Y-m-d 23:59:59', time())) + $sku_info[0]['cycle'] * 3600,
                    'power' => $sku_info[0]['power'],
                    'computing_power' => $sku->buy_nums,
                    'cycle' => $sku_info[0]['cycle'],
                    'machine_type' => 'BTC',
                    'type' => 1,
                    'worth' => bcmul($sku->sku_price, $sku->buy_nums),
                    'created_at' => $time,
                    'updated_at' => $time,
                ];

                if (isset($sku_info[0]['giving_rate']) && $sku_info[0]['giving_rate'] > 0) {
                    $machine_data[] = [
                        'user_id' => $sku->buyer_id,
                        'order_sn' => $sku->order_sn,
                        'order_sub_sn' => $sku->sub_sn,
                        'sku_id' => $sku_info[0]['id'],
                        'sku_name' => $sku_info[0]['title'],
                        'start_time' => strtotime(date('Y-m-d 0:0:0', time())) + 86400, //次日生效
                        'expired_time' => strtotime(date('Y-m-d 23:59:59', time())) + $sku_info[0]['cycle'] * 3600,
                        'power' => $sku_info[0]['power'],
                        'computing_power' => bcmul($sku->buy_nums, $sku_info[0]['giving_rate'], 8),
                        'cycle' => $sku_info[0]['cycle'],
                        'machine_type' => 'BTC',
                        'type' => 3,
                        'worth' => 0,
                        'created_at' => $time,
                        'updated_at' => $time,
                    ];
                }
            }

            $this->machine_create_before($machine_data);
            // runHook('machine_create_before', $machine_data);

            try {
                foreach ($machine_data as $machine) {
                    $user_info = UsersModel::where('user_id', $machine['user_id'])->first();
                    if (!$user_info) {
                        $result = UsersModel::insert(['user_id' => $machine['user_id']]);
                        if (!$result) throw new Exception();
                        $user_info = UsersModel::where('user_id', $machine['user_id'])->first();
                    }

                    switch ($machine['type']) {
                        case 1:
                            $team_info = empty($user_info->team_info) ? [] : json_decode($user_info->team_info, true);
                            $team_info['self']['buy'] = isset($team_info['self']['buy']) ? $team_info['self']['buy'] + $machine['computing_power'] : $machine['computing_power'];
                            $user_info->team_info = json_encode($team_info);
                            $user_info->power = bcadd($machine['computing_power'],$user_info->power,8);
                            $user_info->save();
                            break;
                        case 3:
                            $user_info->reward_power = bcadd($machine['computing_power'],$user_info->reward_power,8);
                            $user_info->save();
                            break;
                        case 10:
                            $user_info->reward_team_power = bcadd($machine['computing_power'],$user_info->reward_team_power,8);
                            $user_info->save();
                            break;
                    }
                }
                $result = UsersMachineModel::insert($machine_data);
                if (!$result) throw new Exception('UsersMachine:Add Error');

                $result = OrderQueueModel::where(['id' => $v->id, 'status' => 0])->update(['status' => 1]);
                if (!$result) throw new Exception('OrderQueue:Update Error');
                echo "MachineLease:Success!}";
            } catch (Exception $e) {
                echo "MachineLease:{$e->getMessage()}!";
            }
        }
    }

    public function goods(int $sku_id)
    {
        if ($sku_id <= 0) return false;

        try {
            $http = new \GuzzleHttp\Client;
            $response = $http->post(env('GOODS_URL') . '/goods.GoodsController.getSkus', [
                'headers' => [
                    'Authorization' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjY5MjM0MWI5MTI0YjIxYzdmZjY5NGQxNTYxOTFlOGJhNWFhZmJkYWE5Mjg5MmExZTFmNzdiODIxNmEwMWI0YjRiYzdhYTU2ZTZjMDA4ZmM1In0.eyJhdWQiOiIxIiwianRpIjoiNjkyMzQxYjkxMjRiMjFjN2ZmNjk0ZDE1NjE5MWU4YmE1YWFmYmRhYTkyODkyYTFlMWY3N2I4MjE2YTAxYjRiNGJjN2FhNTZlNmMwMDhmYzUiLCJpYXQiOjE1Nzc1NjQyNDEsIm5iZiI6MTU3NzU2NDI0MSwiZXhwIjoxNTc4MTY5MDQxLCJzdWIiOiIxIiwic2NvcGVzIjpbIioiXX0.WD9FAX4fFlHenm449v3oxVcF7_h9exrpIWv-2_5GaL3J9Tq1em6kkUiLKEVJrH_hM1wOpjEmrxgLuoAYgIbXlyqAFMcmH6Wj-vbDpvXSb_MZ_NPKjvOARSH0GDEwiAm1WD82-LSxXPnaaXP8sNDN6KCMjE44RpUY4CD-oDzQnzT_B75CvfcO_TIKmyaigHR_Q9cG3P8o8W2ZuS4DeYlKS0yeGVsTdoBoLNCfTKMoBOYO0ldkX-1OCb9ZpsAHRychCci7L8BTzA2UYmnG9y5MaiFcOMFOuBk0-oqXLAFOQ4xydFaSIIanpJGOU9vnl7gwjyLo4d-fsPM82eTY1qUpJpThePt2Xt7_y2X3v_S0Liu0TEpdGD8ZfMHrmyZUKGyvP1qh28tf4A4bknfXeTWs6VFglwotZml7UJ24a8iDIz33j7WwVanmWl0yv6GO_KoxSBMeksgfIuNo_nr_217Kmxb81yB2clsIEsip7EwCLG-owRu66UmLRgK29OWh5vs-vSrkL_YD2dOnP_ZprkalgxvFIECI8EvBcbs0tTWCeNhxPwuJbZg8XkYcvsDugiZIkIVub6__WT6xcjjtgMvNdPMa7WVdFnF81fIX0olDngjw_cpVUHdDXi7Efs3aNEm0BVDxqxezH_yf1FkbXN1KEYeb81qz68OwYHYdFmG9S44'
                ],
                'query' => [
                    'sku_id' => $sku_id,
                ]
            ]);
            $data = json_decode((string) $response->getBody(), true);
            if ($data['code'] != 200) {
                throw new CommonException(CommonException::CUSTOMIZE_ERROR, $data['message']);
            }
        } catch (\Exception $e) {
            throw new CommonException(CommonException::CUSTOMIZE_ERROR, $e->getMessage());
        }
        return $data['data'];
    }

    public function machine_create_before(&$data)
    {
        $reward_rule_data = RewardRuleModel::get();

        $reward_rule = [];
        foreach ($reward_rule_data as $v) {
            if ($v->tier_restrictions) {
                $tier_arr = explode(',', $v->tier_restrictions);

                $tier2rate = [];
                foreach ($tier_arr as $value) {
                    list($tier, $rate) = explode(':', $value);
                    $tier2rate[$tier] = $rate;
                }
                $reward_rule[$v->mark][$v->level_restrictions] = [
                    'tier' => $tier2rate,
                    'is_dynamic_reward' => $v->is_dynamic_reward,
                ];
            }
        }
        foreach ($reward_rule as $key => $value) {
            $$key = $value; // $reward $see_point_reward
        }

        $machine_data = [];
        $reward_data = [];
        foreach ($data as $machine) {
            if ($machine['type'] == 1) {
                $user = DB::table('rryb_users.user_relation')->where('user_id', $machine['user_id'])->first();
                if (!$user) continue;

                $root = empty($user->root) ? [] : explode(',', trim($user->root, ','));
                foreach ($root as $k => $user_id) {
                    $parent_level = DB::table('rryb_users.user_relation')->where('user_id', $user_id)->value('level');

                    // 推荐
                    if (isset($reward[$parent_level]) && isset($reward[$parent_level]['tier'][$k + 1])) {
                        $reward_amount = ($reward[$parent_level]['is_dynamic_reward'] == 1) ? bcmul($reward[$parent_level]['tier'][$k + 1], $machine['computing_power'], 8) : $reward[$parent_level]['tier'][$k + 1];
                        $machine_data[] = [
                            'user_id' => $user_id,
                            'order_sn' => $machine['order_sn'],
                            'order_sub_sn' => $machine['order_sub_sn'],
                            'sku_id' => $machine['sku_id'],
                            'sku_name' => $machine['sku_name'],
                            'start_time' => $machine['start_time'], //次日生效
                            'expired_time' => $machine['expired_time'],
                            'power' => $machine['power'],
                            'computing_power' => $reward_amount,
                            'cycle' => $machine['cycle'],
                            'machine_type' => $machine['machine_type'],
                            'type' => 10,
                            'worth' => 0,
                            'created_at' => $machine['created_at'],
                            'updated_at' => $machine['updated_at'],
                        ];
                    }

                    // 见点
                    if (isset($see_point_reward[$parent_level]) && isset($see_point_reward[$parent_level]['tier'][$k + 1])) {
                        $reward_amount = ($see_point_reward[$parent_level]['is_dynamic_reward'] == 1) ? bcmul($see_point_reward[$parent_level]['tier'][$k + 1], $machine['computing_power'], 8) : $see_point_reward[$parent_level]['tier'][$k + 1];
                        $reward_data[] = [
                            'user_id' => $user_id,
                            'order_sn' => $machine['order_sn'],
                            'order_sub_sn' => $machine['order_sub_sn'],
                            'money' => $reward_amount,
                            'type_id' => 2,
                            'coin_id' => 10, //usdt
                            'remark' => '管理津贴',
                            'created_at' => time(),
                            'updated_at' => time(),
                        ];
                    }
                }
            }
        }

        if (!empty($reward_data)) {
            foreach($reward_data as $v){
                $user_info = UsersModel::where('user_id', $v['user_id'])->first();
                if (!$user_info) {
                    $result = UsersModel::insert(['user_id' => $v['user_id']]);
                    if (!$result) throw new Exception();
                    $user_info = UsersModel::where('user_id', $v['user_id'])->first();
                }
                $user_info->reward_commission = bcadd($user_info->reward_commission,$v['money'],8);
                $user_info->save();
            }
            WalletQueueModel::insert($reward_data);
        }
        if (!empty($machine_data)) $data = array_merge($data, $machine_data);
    }
}
