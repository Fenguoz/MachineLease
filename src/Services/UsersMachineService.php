<?php

namespace Fenguoz\MachineLease\Services;

use App\Services\Order\Client\GoodsService;
use App\Services\Service;
use Fenguoz\MachineLease\Exceptions\CommonException;
use Fenguoz\MachineLease\Exceptions\MachineException;
use Fenguoz\MachineLease\Exceptions\UsersMachineException;
use Fenguoz\MachineLease\Models\LevelModel;
use Fenguoz\MachineLease\Models\UsersMachineModel;
use Fenguoz\MachineLease\Models\UsersMachineOutputModel;
use Fenguoz\MachineLease\Models\UsersModel;
use Fenguoz\MachineLease\Models\WalletQueueModel;
use Illuminate\Support\Facades\DB;

class UsersMachineService extends Service
{
    public function getMachineList(array $params = [], array $option = [])
    {
        $count = isset($option['count']) ? $option['count'] : 10;
        $data = UsersMachineModel::where($params)->paginate($count);
        if (!$data)
            throw new CommonException(CommonException::DATA_ERRPR);

        foreach ($data as $k => $v) {
            $v->output_amount = UsersMachineOutputModel::where('machine_id', $v->id)->sum('output');

            $cycle_str = '';
            $cycle_day = (int) ($v->cycle / 24);
            $cycle_hour = (int) ($v->cycle % 24);
            if ($cycle_day > 0) $cycle_str .= $cycle_day . '天';
            if ($cycle_hour > 0) $cycle_str .= $cycle_hour . '小时';
            $v->cycle_show = $cycle_str;

            $expired_str = '';
            $expired_day = (int) ($v->expired_time / 86400);
            if ($expired_day > 0) $expired_str .= $expired_day . '天';
            $expired_hour = (int) (($v->cycle / 3600) % 24);
            if ($expired_hour > 0) $expired_str .= $expired_hour . '小时';
            $v->expired_day = $expired_str;

            $v->can_extend = ($v->worth > 0 && $v->type == 1 && $v->expired_time < time()) ? 1 : 0;
            $v->can_refund = ($v->worth > 0 && $v->type == 1 && $v->expired_time < time()) ? 1 : 0;
        }

        return $data;
    }

    public function getMachineOutput(array $params = [], array $option = [])
    {
        if (!isset($params['machine_id']))
            throw new CommonException(CommonException::USER_ID_EMPTY);
        if ((int) $params['machine_id'] <= 0)
            throw new MachineException(MachineException::MACHINE_ID_ERROR);

        $count = isset($option['count']) ? $option['count'] : 10;
        $data = UsersMachineOutputModel::where($params)->paginate($count);
        if (!$data)
            throw new CommonException(CommonException::DATA_ERRPR);
        return $data;
    }

    public function getUserInfo(int $user_id)
    {
        if ((int) $user_id <= 0)
            throw new CommonException(CommonException::USER_ID_ERROR);

        // $user_id = 46;
        $user = UsersModel::where([
            'user_id' => $user_id
        ])->first();
        if (!$user) {
            UsersModel::insert(['user_id' => $user_id]);
            $user = UsersModel::where([
                'user_id' => $user_id
            ])->first();
        }

        $user_level = DB::table('rryb_users.user_relation')->where('user_id', $user_id)->value('level') ?? 0;
        $level_info = LevelModel::where('level', $user_level + 1)->first();
        $upgrade_info = [];
        if ($level_info) {
            $rules = json_decode($level_info->rules, true);
            foreach ($rules as $type => $rule) {

                $team_info = json_decode($user, true);
                switch ($type) {
                    case 'self_cert':
                        $status = DB::table('rryb.certification')->where('user_id', $user_id)->value('status') ?? 0;
                        $actual = ($status == 1 ? 1 : 0);
                        $title = '实名认证';
                        $description = '完成且通过实名认证';
                        break;
                    case 'self_buy':
                        $actual = isset($team_info['self']['buy']) ? $team_info['self']['buy'] : 0;
                        $title = '自持算力T';
                        $description = '自持矿机算力达到' . $rule . 'T';
                        break;
                    case 'invite_1':
                        $actual = isset($team_info['invite']['1']) ? $team_info['invite']['1'] : 0;
                        $title = '直推实名人数';
                        $description = '直推实名会员数达到' . $rule;
                        break;
                    case 'team_1':
                        $actual = isset($team_info['team']['1']) ? $team_info['team']['1'] : 0;
                        $title = '伞下实名人数';
                        $description = '伞下总实名会员数达到' . $rule;
                        break;
                    case 'team_2':
                        $actual = isset($team_info['team']['2']) ? $team_info['team']['2'] : 0;
                        $title = '银牌会员人数';
                        $description = '伞下银牌会员数数达到' . $rule;
                        break;
                    case 'team_3':
                        $actual = isset($team_info['team']['3']) ? $team_info['team']['3'] : 0;
                        $title = '金牌会员人数';
                        $description = '伞下金牌会员数数达到' . $rule;
                        break;
                    default:
                        $actual = 0;
                        $title = '**会员人数';
                        $description = '伞下**会员数数达到99';
                        break;
                }

                $upgrade_info[$type] = [
                    'goal' => $rule,
                    'actual' => (int) $actual,
                    'title' => $title,
                    'description' => $description,
                    'status' => ($actual >= $rule ? 1 : 0),
                ];
            }
        }

        $output_yesterday = UsersMachineOutputModel::where('user_id', $user_id)->whereBetween('created_at', [strtotime(date('Y-m-d 0:0:0', time())), strtotime(date('Y-m-d 23:59:59', time()))])->sum('output') ?? '0.00000000';
        $output_amount = UsersMachineOutputModel::where('user_id', $user_id)->sum('output') ?? '0.00000000';

        $user_ids = DB::table('rryb_users.user_relation')->where('root', 'like', "%,{$user_id},%")->pluck('user_id') ?? [];
        $team_power = UsersModel::whereIn('user_id', $user_ids)->sum('power') ?? '0.00000000';

        $data = [
            'power' => $user->power,
            'reward_power' => $user->reward_power,
            'reward_commission' => $user->reward_commission,
            'reward_team_power' => $user->reward_team_power,
            'team_info' => $user->team_info,
            'team_power' => $team_power,
            'output_amount' => $output_amount,
            'output_yesterday' => $output_yesterday,
            'output_today' => $output_yesterday,
            'upgrade_info' => $upgrade_info
        ];

        runHook('user_machine_info_ext', $data);

        return $data;
    }

    public function extend(int $user_id, int $machine_id)
    {
        if ($user_id <= 0)
            throw new CommonException(CommonException::USER_ID_ERROR);
        if ($machine_id <= 0)
            throw new MachineException(MachineException::MACHINE_ID_ERROR);

        $machine = UsersMachineModel::where([
            'id' => $machine_id,
        ])->first();
        if (!$machine)
            throw new UsersMachineException(UsersMachineException::MACHINE_NOT_EXIST);
        // if ($machine->type != 1 || $machine->worth == 0 || $machine->expired_time > time())
        //     throw new UsersMachineException(UsersMachineException::MACHINE_CANT_EXTEND);
        if ($user_id != $machine->user_id)
            throw new UsersMachineException(UsersMachineException::NOT_PERMISSION);
        $sku_info = (new GoodsService)->good($machine->sku_id);
        if (!isset($sku_info[0]))
            throw new UsersMachineException(UsersMachineException::MACHINE_GOODS_NOT_EXIST);

        DB::beginTransaction();
        try {
            //收益储存发放
            if ($machine->output_storage > 0) {
                $result = WalletQueueModel::insert([
                    'user_id' => $user_id,
                    'type_id' => 1,
                    'coin_id' => 14,
                    'money' => $machine->output_storage,
                    'remark' => '矿机产出',
                    'order_sn' => $machine->order_sn,
                    'order_sub_sn' => $machine->order_sub_sn,
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
                if (!$result) throw new CommonException(CommonException::UDATE_ERROR);
            }

            $machines = UsersMachineModel::where([
                'order_sn' => $machine->order_sn,
            ])->get();

            //算力更新
            foreach ($machines as $v) {
                $result = UsersMachineModel::where('id', $v->id)->update([
                    'cycle' => $sku_info[0]['cycle'],
                    'status' => 1,
                    'start_time' => strtotime(date('Y-m-d 0:0:0', time())) + 86400, //次日生效
                    'expired_time' => strtotime(date('Y-m-d 23:59:59', time())) + $sku_info[0]['cycle'] * 3600,
                    'output_storage' => 0,
                ]);
                if (!$result) throw new CommonException(CommonException::UDATE_ERROR);

                if ($v->status == 1) continue;
                switch ($v->type) {
                    case 1:
                        $result = UsersModel::where('user_id', $v->user_id)->increment('power', $v->computing_power);
                        break;
                    case 3:
                        $result = UsersModel::where('user_id', $v->user_id)->increment('reward_power', $v->computing_power);
                        break;
                    case 10:
                        $result = UsersModel::where('user_id', $v->user_id)->increment('reward_team_power', $v->computing_power);
                        break;
                }
                if (!$result) throw new CommonException(CommonException::UDATE_ERROR);
            }
            DB::commit();
        } catch (CommonException $e) {
            DB::rollBack();
            throw new CommonException(CommonException::CUSTOMIZE_ERROR, $e->getMessage());
        }
        return true;
    }

    public function refund(int $user_id, int $machine_id)
    {
        if ($user_id <= 0)
            throw new CommonException(CommonException::USER_ID_ERROR);
        if ($machine_id <= 0)
            throw new MachineException(MachineException::MACHINE_ID_ERROR);

        $machine = UsersMachineModel::where([
            'id' => $machine_id,
        ])->first();
        if (!$machine)
            throw new UsersMachineException(UsersMachineException::MACHINE_NOT_EXIST);
        if ($machine->type != 1 || $machine->worth == 0 || $machine->expired_time > time())
            throw new UsersMachineException(UsersMachineException::MACHINE_CANT_EXTEND);
        if ($user_id != $machine->user_id)
            throw new UsersMachineException(UsersMachineException::NOT_PERMISSION);

        //本金返还
        DB::beginTransaction();
        try {
            $result = WalletQueueModel::insert([
                'user_id' => $user_id,
                'type_id' => 3,
                'coin_id' => 10,
                'money' => $machine->worth,
                'remark' => '矿机退租',
                'order_sn' => $machine->order_sn,
                'order_sub_sn' => $machine->order_sub_sn,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            if (!$result) throw new CommonException(CommonException::UDATE_ERROR);
            $result = UsersModel::where('user_id', $machine->user_id)->decrement('power', $machine->computing_power);
            if (!$result) throw new CommonException(CommonException::UDATE_ERROR);

            $machine->worth = 0;
            $machine->status = 0;
            $machine->output_storage = 0;
            $machine->save();
            DB::commit();
        } catch (CommonException $e) {
            DB::rollBack();
            throw new CommonException(CommonException::CUSTOMIZE_ERROR, $e->getMessage());
        }
        return true;
    }

    public function statistics($user_id)
    {
        if ($user_id <= 0)
            throw new CommonException(CommonException::USER_ID_ERROR);

        $times = 7;
        $output = [];
        $day = [];
        $amount = 0;
        for ($i = 0; $i < $times; $i++) {
            $time = time() - $i * 86400;
            $number = UsersMachineOutputModel::where('user_id', $user_id)->whereBetween('created_at', [strtotime(date('Y-m-d 0:0:0', $time)), strtotime(date('Y-m-d 23:59:59', $time))])->sum('output') ?? '0.00000000';
            $output[] = $number;
            $day[] = date('d', $time);
            $amount = bcadd($amount, $number, 8);
        }

        return [
            'amount' => $amount,
            'output' => array_reverse($output),
            'day' => array_reverse($day)
        ];
    }
}
