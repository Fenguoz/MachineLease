<?php

namespace Fenguoz\MachineLease\Services;

use App\Services\Service;
use Fenguoz\MachineLease\Exceptions\CommonException;
use Fenguoz\MachineLease\Exceptions\MachineException;
use Fenguoz\MachineLease\Exceptions\UsersMachineException;
use Fenguoz\MachineLease\Models\LevelModel;
use Fenguoz\MachineLease\Models\UsersMachineModel;
use Fenguoz\MachineLease\Models\UsersMachineOutputModel;
use Fenguoz\MachineLease\Models\UsersModel;
use Illuminate\Support\Facades\DB;

class UsersMachineService extends Service
{
    public function getMachineList(array $params = [], array $option = [])
    {
        $count = isset($option['count']) ? $option['count'] : 10;
        $data = UsersMachineModel::where($params)->paginate($count);
        if (!$data)
            throw new CommonException(CommonException::DATA_ERRPR);

        foreach($data as $k => $v){
            $v->output_amount = UsersMachineOutputModel::where('machine_id',$v->id)->sum('output');
            $v->cycle_show = '3年';
            $v->expired_day = '30';
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
        if (!$user){
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
                switch ($type) {
                    case 'cert':
                        $status = DB::table('rryb_kuangchang.certification')->where('user_id', $user_id)->value('status') ?? 0;
                        $actual = ($status == 1 ? 1 : 0);
                        $title = '实名认证';
                        $description = '完成且通过实名认证';
                        break;
                    case 'self_buy':
                        $actual = $user->power;
                        $title = '自购';
                        $description = '购物矿机算力达到' . $rule . 'T';
                        break;
                    case 'invite_1':
                        $actual = $user->power;
                        $title = '直推实名数';
                        $description = '直推实名会员数达到' . $rule;
                        break;
                    case 'team_1':
                        $actual = $user->power;
                        $title = '伞下实名数';
                        $description = '伞下总实名会员数达到' . $rule;
                        break;
                    case 'team_2':
                        $actual = $user->power;
                        $title = '银牌会员数';
                        $description = '伞下银牌会员数数达到' . $rule;
                        break;
                    case 'team_3':
                        $actual = $user->power;
                        $title = '金牌会员数';
                        $description = '伞下金牌会员数数达到' . $rule . 'T';
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

        return true;
    }

    public function refund(int $user_id, int $machine_id)
    {
        if ($user_id <= 0)
            throw new CommonException(CommonException::USER_ID_ERROR);
        if ($machine_id <= 0)
            throw new MachineException(MachineException::MACHINE_ID_ERROR);

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
        for($i = 0;$i < $times;$i++){
            $time = time()-$i*86400;
            $number = UsersMachineOutputModel::where('user_id', $user_id)->whereBetween('created_at', [strtotime(date('Y-m-d 0:0:0', $time)), strtotime(date('Y-m-d 23:59:59', $time))])->sum('output') ?? '0.00000000';
            $output[] = $number;
            $day[] = date('d',$time);
            $amount = bcadd($amount,$number,8);
        }

        return [
            'amount' => $amount,
            'output' => array_reverse($output),
            'day' => array_reverse($day)
        ];
    }
}
