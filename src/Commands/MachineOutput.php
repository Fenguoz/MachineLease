<?php

namespace Fenguoz\MachineLease\Commands;

use Exception;
use Fenguoz\MachineLease\Models\MachineQuotesModel;
use Fenguoz\MachineLease\Models\UsersMachineModel;
use Fenguoz\MachineLease\Models\UsersMachineOutputModel;
use Fenguoz\MachineLease\Models\UsersModel;
use Fenguoz\MachineLease\Models\WalletQueueModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MachineOutput extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machine:output {client_id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '矿机产出';

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
        bcscale(8);

        $time = time();
        $usdttocny = 7;
        $start_time = strtotime(date('Y-m-d', $time));
        $datetime = date('Y-m-d H:i:s', $time);
        //过期矿机
        $expire_machines = UsersMachineModel::where('expired_time', '<', $start_time)->where('status', 1)->get();
        $expire_data = [];
        foreach ($expire_machines as $machine) {
            $extend = 7 * 86400;
            if ($machine->expired_time + $extend < $start_time && $machine->type == 1) { //自动续租
                $expire_data['extend'][] = [
                    'machine_id' => $machine->id,
                    'cycle' => $machine->cycle,
                ];
                continue;
            }

            if ($machine->expired_time < $start_time && $machine->worth == 0) {
                $expire_data['expire'][] = [
                    'machine_id' => $machine->id,
                    'computing_power' => $machine->computing_power,
                    'type' => $machine->type,
                    'user_id' => $machine->user_id,
                ];
            }
        }

        $this->machine_expire_data_ext($expire_data);
        // runHook('machine_expire_data_ext',$expire_data);

        DB::beginTransaction();
        try {
            foreach ($expire_data as $type => $machine) {
                switch ($type) {
                    case 'extend':
                        foreach ($machine as $info) {
                            $result = UsersMachineModel::where('id', $info['machine_id'])->update([
                                'expired_time' => strtotime(date('Y-m-d 23:59:59', $time)) + $info['cycle'] * 3600,
                            ]);
                            if (!$result) throw new Exception('UserMachine Update');
                        }
                        break;
                    case 'expire':
                        foreach ($machine as $info) {
                            $result = UsersMachineModel::where('id', $info['machine_id'])->update([
                                'status' => 0
                            ]);
                            if (!$result) throw new Exception('UserMachine Update');

                            //算力更新
                            switch ($info['type']) {
                                case 1:
                                    $result = UsersModel::where('user_id', $info['user_id'])->decrement('power', $info['computing_power']);
                                    break;
                                case 3:
                                    $result = UsersModel::where('user_id', $info['user_id'])->decrement('reward_power', $info['computing_power']);
                                    break;
                                case 10:
                                    $result = UsersModel::where('user_id', $info['user_id'])->decrement('reward_team_power', $info['computing_power']);
                                    break;
                            }
                            if (!$result) throw new Exception('UserMachine Update');
                        }
                        break;
                }
            }
            echo "[{$datetime}] SUCCESS: Machine Expire \n";
            DB::commit();
        } catch (Exception $e) {
            echo "[{$datetime}] ERROR: Machine Output {$e->getMessage()} <Line:{$e->getLine()}>\n";
            DB::rollBack();
        }


        //正常矿机
        $settle_time = $start_time - 86400;
        $machines = UsersMachineModel::where('start_time', '<=', $settle_time)
            ->where('mark', '!=', date('Ymd', $time))
            ->where('status', 1)
            ->get();
        if (count($machines) == 0) exit("[{$datetime}] SUCCESS: Machine Output 暂无需处理项！\n");

        $machine_quotes = MachineQuotesModel::get();

        $quotes = [];
        foreach ($machine_quotes as $quote) {
            $quotes[$quote->currency_name] = [
                'rewards_per_unit' => $quote->real_rewards_per_unit,
                'rewards_per_unit_minute' => bcdiv($quote->real_rewards_per_unit, 24),
                'electricity' => $quote->electricity,
                'manage' => $quote->manage,
                'currency_usdt_worth' => $quote->currency_usdt_worth
            ];
        }

        $machine_data = [];
        foreach ($machines as $machine) {
            $remain_time = (int) ($machine->expired_time + 1 - $settle_time) / 3600;
            $run_hour = min(24, $remain_time);
            $output = bcmul(bcmul($quotes[$machine->machine_type]['rewards_per_unit_minute'], $run_hour), $machine->computing_power); // 挖矿产出
            $manage_fee = bcmul($output, $quotes[$machine->machine_type]['manage']); // 管理费
            $electricity_unit_price = bcdiv(bcmul($quotes[$machine->machine_type]['electricity'], $run_hour), bcmul($quotes[$machine->machine_type]['currency_usdt_worth'], $usdttocny)); //电价
            $electricity_fee = bcmul($electricity_unit_price, bcmul($machine->computing_power, $machine->power)); //电费 = 电价 * 矿机功耗 * 算力
            $real_output = max(0, bcsub($output, bcadd($manage_fee, $electricity_fee))); // 实际产出 = 挖矿产出 + (管理费 - 电费)

            $machine_data['machine_ids'][] = $machine->id;

            if ($machine->expired_time < $start_time) { //收益冻结
                $machine_data['machine_output_freeze'][$machine->id] = $real_output;
                continue;
            }

            $machine_data['machine_output'][] = [
                'machine_id' => $machine->id,
                'user_id' => $machine->user_id,
                'electricity_fee' => $electricity_fee,
                'manage_fee' => $manage_fee,
                'electricity' => $quotes[$machine->machine_type]['electricity'],
                'manage' => $quotes[$machine->machine_type]['manage'],
                'output' => $real_output,
                'run_hour' => $run_hour,
                'created_at' => $time,
                'updated_at' => $time,
            ];

            if ($real_output > 0) {
                $machine_data['machine_output_queue'][] = [
                    'user_id' => $machine->user_id,
                    'type_id' => 1,
                    'coin_id' => 14,
                    'money' => $real_output,
                    'remark' => '矿机产出',
                    'created_at' => time(),
                    'updated_at' => time(),
                    'order_sn' => $machine->order_sn,
                    'order_sub_sn' => $machine->order_sub_sn,
                ];
            }
        }

        $this->machine_output_data_ext($machine_data);
        // runHook('machine_output_data_ext',$machine_data);

        DB::beginTransaction();
        try {
            $result = UsersMachineOutputModel::insert($machine_data['machine_output']);
            if (!$result) throw new Exception('MachineOutput Insert');

            if (isset($machine_data['machine_output_freeze']) && !empty($machine_data['machine_output_freeze'])) {
                foreach ($machine_data['machine_output_freeze'] as $machine_id => $output) {
                    $result = UsersMachineModel::where('id', $machine_id)->increment('output_storage', $output);
                    if (!$result) throw new Exception('Machine Update');
                }
            }

            if (!empty($machine_data['machine_output_queue'])) {
                $result = WalletQueueModel::insert($machine_data['machine_output_queue']);
                if (!$result) throw new Exception('WalletQueue Insert');
            }

            $result = UsersMachineModel::where('expired_time', '>', $settle_time)
                ->whereIn('id', $machine_data['machine_ids'])
                ->where('start_time', '<=', $settle_time)
                ->where('mark', '!=', date('Ymd', $time))
                ->where('status', 1)
                ->update([
                    'mark' => date('Ymd', $time)
                ]);
            if (!$result) throw new Exception('UserMachine Update');
            echo "[{$datetime}] SUCCESS: Machine Output\n";
            DB::commit();
        } catch (Exception $e) {
            echo "[{$datetime}] ERROR: Machine Output {$e->getMessage()} <Line:{$e->getLine()}>\n";
            DB::rollBack();
        }
    }

    public function machine_expire_data_ext(&$data)
    {
    }

    public function machine_output_data_ext(&$data)
    {
    }
}
