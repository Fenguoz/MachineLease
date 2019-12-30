<?php

namespace Fenguoz\MachineLease\Commands;

use Fenguoz\MachineLease\Models\MachineQuotesModel;
use Fenguoz\MachineLease\Models\WalletQueueModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class WalletSettle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:settle {client_id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '钱包队列结算';

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

        $usdt = 7;
        $reward = WalletQueueModel::where('status',0)->paginate(10);
        $token = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjU3NWYzNGMwNjA4MGM2YzhiOWU4MmVkYWY0MmZhYzlhMzE0YjVlNzVkNzZhNGJmNTJhZDcwM2MwNGMwOTY2ZDc1N2U3NmQyYTI3OGNkMDVmIn0.eyJhdWQiOiIxIiwianRpIjoiNTc1ZjM0YzA2MDgwYzZjOGI5ZTgyZWRhZjQyZmFjOWEzMTRiNWU3NWQ3NmE0YmY1MmFkNzAzYzA0YzA5NjZkNzU3ZTc2ZDJhMjc4Y2QwNWYiLCJpYXQiOjE1NzE5ODc0NDksIm5iZiI6MTU3MTk4NzQ0OSwiZXhwIjoxNjAzNjA5ODQ5LCJzdWIiOiIiLCJzY29wZXMiOltdfQ.ORVGDvo5Jy-VVjnB0u7Bc1DNyWaZ_iN36jS7LkPgJSqMoWP_t7Y1ZeF6oXZ-_IKy-ahOmS0GZZfMmmg3btub-RnYffvs7fzE-csb5aSRQh65loRMLPsS4aEYiPTjF0L-6Pu516O5hUMF10ppHsX_UzNnMrIdInio6O2Y1oZYtRiV6OxgBQ1c1kvCJVeEOnUCyYV4sXBUp1oZdHIfy-DW_GuIVka10ms5xS-M3nXp_JP3J_vPAy3CEgk6eMH99PP3AD-HJBjA3Zzj9hx1nw1hcv_kpC67J4g2sUc4m_taYlpSwLIxoiTDfsmHThlh6336OrrIkhTD0hk2pUoS8hE6PiPtq5wwZMMCH_6Ge_G-LGkIUJiSoaWzCmeCeLpuSKVXh84uNwwBLalXrrGuDjKi48UfizrOQExUjuaQY-N9ZDIxTTiazQwWUKMjt8CAt5iWbMLbasGBXKA5Emf5W5jCPgW8UqCn4-rb1JSPI8ereQVqp_f47k0CxJMLr37snGUJ--cTi7ejv5PmOXiGqQkgznyYS_cI4icWpco_ydqTfChegAD7CXxPpoBeHkGx8JvwXxK4HYSu6Tzht2EMFCWJUbsuG6TJkqt4rtJJLAa1ywLyBMJGj5MenCqkq_scH46T7tRtEwzGyyM-a1zhmZlcDSXujq6wCvHEf7ChMHZwGpY';
        foreach ($reward as $item) {
            try {
                switch($item->type_id){
                    case 1:
                        $remark = '矿机产出';
                        break;
                    case 2:
                        $remark = '管理津贴';
                        break;
                    case 3:
                        $remark = '矿机退租';
                        break;
                    default:
                        $remark = '奖励';
                }
                
                $client = new Client();
                $response = $client->post(env('MICRO_WALLET',null).'/post.update.balance', [
                    'headers' => [
                        'Authorization' => $token
                    ],
                    'query' => [
                        'user_id' => $item->user_id,
                        'coin_id' => $item->coin_id,
                        'money' => $item->money,
                        'type_id' => 0,
                        'remark' => $remark
                    ]
                ]);
                $data = json_decode((string) $response->getBody(), true);
                if ($data['code'] != 200) return self::error($data['message'], $data['code']);
                if($data['code'] == 200){
                    $result = WalletQueueModel::where(['id' => $item->id,'status' => 0])->update([
                        'status' => 1,
                        'trade_no' => $data['data']
                    ]);
                    echo 'Reward Success!';
                }
            } catch (ServerException $e) {
                echo "Reward Error:{$e->getMessage()}!";
                return self::error($e->getMessage(), $e->getCode());
            }
        }
    }

}
