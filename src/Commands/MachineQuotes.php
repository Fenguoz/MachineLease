<?php

namespace Fenguoz\MachineLease\Commands;

use Fenguoz\MachineLease\Models\MachineQuotesModel;
use Illuminate\Console\Command;

class MachineQuotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machine:quotes {client_id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '矿机行情';

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

        // 抓取 每日收益
        $url = "https://api-prod.poolin.com/api/public/v2/basedata/coins/block_stats";
        $json = file_get_contents($url);
        $data = json_decode($json, true);

        foreach ($data["data"] as $k => $v) {
            if ($k == "BTC") {
                $quotes = MachineQuotesModel::where('currency_name', $k)->first();
                if (!$quotes) continue;

                // 如果有值，比较两个值哪个小
                if ($quotes->real_rewards_per_unit == 0 || $quotes->real_rewards_per_unit > $v['rewards_per_unit'] || $this->isStreakDays($quotes->updated_at, time())) {
                    $quotes->real_rewards_per_unit = $v['rewards_per_unit'];
                }

                $quotes->rewards_per_unit = $v['rewards_per_unit'];
                $quotes->difficulty = bcdiv($v['difficulty'], '1000000000000', 2);
                $quotes->net_hash_one_week = round(bcdiv($v['net_hash_one_week'], '1000000000000000000', 3), 2);
                $quotes->updated_at = time();
                $quotes->save();
                echo "MachineQuotes:{$k} Update Success!";
            }
        }
    }

    //判断两天是否相连
    function isStreakDays(int $first_time, int $end_time)
    {
        $first_time = getdate($first_time);
        $end_time = getdate($end_time);
        if (($first_time['year'] === $end_time['year']) && ($end_time['yday'] - $first_time['yday'] === 1)) {
            return TRUE;
        } elseif (($end_time['year'] - $first_time['year'] === 1) && ($first_time['mon'] - $end_time['mon'] = 11) && ($first_time['mday'] - $end_time['mday'] === 30)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
}
