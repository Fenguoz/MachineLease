<?php

namespace Fenguoz\MachineLease\Commands;

use Exception;
use Fenguoz\MachineLease\Models\MachineQuotesModel;
use Fenguoz\MachineLease\Models\UsersMachineModel;
use Fenguoz\MachineLease\Models\UsersMachineOutputModel;
use Illuminate\Console\Command;

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
        $datetime = date('Y-m-d H:i:s',$time);
        //过期矿机
        $expire_machines = UsersMachineModel::where('expired_time', '<', $time)->where('status', 1)->get();
        $expire_data = [];
        foreach ($expire_machines as $machine) {
            $extend = 7 + 60 * 60 * 24;
            if ($machine->expired_time + $extend < $time && $machine->type == 1) { //自动续租
                $expire_data['extend'][] = [
                    'machine_id' => $machine->id,
                    'cycle' => $machine->cycle,
                ];
                continue;
            }

            if ($machine->expired_time < $time) {
                $expire_data['expire'][] = $machine->id;
            }
        }

        // runHook('machine_expire_data_ext',$expire_data);

        try {
            foreach ($expire_data as $type => $machine) {
                switch ($type) {
                    case 'extend':
                        foreach ($machine as $info) {
                            $result = UsersMachineModel::where('id', $info['machine_id'])->update([
                                'expired_time' => strtotime(date('Y-m-d 23:59:59', $time + $info['cycle'] * 60 * 60 * 24)),
                            ]);
                            if (!$result) throw new Exception('UserMachine Update');
                        }
                        break;
                    case 'expire':
                        $result = UsersMachineModel::whereIn('id', $machine)->update([
                            'status' => 0
                        ]);
                        if (!$result) throw new Exception('UserMachine Update');
                        break;
                }
            }
            echo "[{$datetime}] SUCCESS: Machine Expire \n";
        } catch (Exception $e) {
            echo "[{$datetime}] ERROR: Machine Output {$e->getMessage()} <Line:{$e->getLine()}>\n";
        }
        

        //正常矿机
        $settle_time = $time - 60 * 60 * 24;
        $machines = UsersMachineModel::where('expired_time', '>', $settle_time)
            ->where('start_time', '<', $settle_time)
            ->where('mark', '!=', date('Ymd', $time))
            ->where('status', 1)
            ->get();
        if (count($machines) == 0) exit("[{$datetime}] SUCCESS: Machine Output 暂无需处理项！\n");

        $machine_quotes = MachineQuotesModel::get();

        $quotes = [];
        foreach ($machine_quotes as $quote) {
            $quotes[$quote->currency_name] = [
                'rewards_per_unit' => $quote->real_rewards_per_unit,
                'electricity' => $quote->electricity,
                'manage' => $quote->manage,
                'currency_usdt_worth' => $quote->currency_usdt_worth
            ];
        }

        $machine_data = [];
        foreach ($machines as $machine) {
            $usdttocny = 7;
            $output = bcmul($quotes[$machine->machine_type]['rewards_per_unit'], $machine->computing_power); // 挖矿产出
            $manage_fee = bcmul($output, $quotes[$machine->machine_type]['manage']); // 管理费
            $electricity_unit_price = bcdiv(bcmul($quotes[$machine->machine_type]['electricity'], 24), bcmul($quotes[$machine->machine_type]['currency_usdt_worth'], $usdttocny)); //电价
            $electricity_fee = bcmul($electricity_unit_price, bcmul($machine->computing_power, $machine->power)); //电费 = 电价 * 矿机功耗 * 算力
            $real_output = max(0,bcsub($output, bcadd($manage_fee, $electricity_fee))); // 实际产出 = 挖矿产出 + (管理费 - 电费)

            $machine_data['machine_output'][] = [
                "machine_id" => $machine->id,
                "user_id" => $machine->user_id,
                "electricity_fee" => $electricity_fee,
                "manage_fee" => $manage_fee,
                "electricity" => $quotes[$machine->machine_type]['electricity'],
                "manage" => $quotes[$machine->machine_type]['manage'],
                "output" => $real_output,
                "created_at" => $time,
                "updated_at" => $time,
            ];
            $machine_data['machine_ids'][] = $machine->id;
        }

        // runHook('machine_output_data_ext',$machine_data);

        try {
            $result = UsersMachineOutputModel::insert($machine_data['machine_output']);
            if (!$result) throw new Exception('MachineOutput Insert');

            $result = UsersMachineModel::where('expired_time', '>', $settle_time)
                ->whereIn('id',$machine_data['machine_ids'])
                ->where('start_time', '<', $settle_time)
                ->where('mark', '!=', date('Ymd', $time))
                ->where('status', 1)
                ->update([
                    'mark' => date('Ymd', $time)
                ]);
            if (!$result) throw new Exception('UserMachine Update');
            echo "[{$datetime}] SUCCESS: Machine Output\n";
        } catch (Exception $e) {
            echo "[{$datetime}] ERROR: Machine Output {$e->getMessage()} <Line:{$e->getLine()}>\n";
        }
    }
}
