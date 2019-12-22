<?php

namespace Fenguoz\MachineLease\Commands;

use App\Exceptions\Common\CommonException;
use App\Models\Order\OrderSkuModel;
use Exception;
use Fenguoz\MachineLease\Models\OrderQueueModel;
use Fenguoz\MachineLease\Models\UsersMachineModel;
use Illuminate\Console\Command;

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
                    'start_time' => strtotime(date('Y-m-d 0:0:0', time() + 60 * 60 * 24)),//次日生效
                    'expired_time' => strtotime(date('Y-m-d 23:59:59', time() + $sku_info[0]['cycle'] * 60 * 60 * 24)),
                    'power' => $sku_info[0]['power'],
                    'computing_power' => $sku->buy_nums,
                    'cycle' => $sku_info[0]['cycle'],
                    'machine_type' => 'BTC',
                    'created_at' => $time,
                    'updated_at' => $time,
                ];
            }

            try {
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
                    'Authorization' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjJmNWI5ZGM4OWE3YjlmZjhkOGVkNjJmZGQxODIxMjIyYzg2MmU3MTRiNWI0MTg4ZDZkNWRjZGQzMTk5MjI5NzVmYjMzOTgwMmYzZTY2OTBjIn0.eyJhdWQiOiIxIiwianRpIjoiMmY1YjlkYzg5YTdiOWZmOGQ4ZWQ2MmZkZDE4MjEyMjJjODYyZTcxNGI1YjQxODhkNmQ1ZGNkZDMxOTkyMjk3NWZiMzM5ODAyZjNlNjY5MGMiLCJpYXQiOjE1NzY4MzIwNTksIm5iZiI6MTU3NjgzMjA1OSwiZXhwIjoxNTc3NDM2ODU5LCJzdWIiOiI0NiIsInNjb3BlcyI6WyIqIl19.h1LEkXgxX7NvYO65rQm1C05XN0Ey_jwYmG6lkprVuVogFzO8PjgO-4Hv2dblnz-sswOx5MN_7ad9nsreFxYS5_jKCG-C1WJW8lJuBBICqgsXDOyRVfSUWCoSwwNo7RI35bCSbXXXuB6Kv303Z0J4sGHwB2cPO--m5rTnMr-Xqx17ryZsdHlvPfOdOPgUNVkvquWAU-tqnQRa5DsV-8npYHH9EmfW77TfmYjCORr2cArMn4BgflKhkDouq_XaEPN2WoD0HJWiJ8ISx3JXQQWsV8OPgIJpk9fB3mVIRGql3AqsmMwfaseYBeBvHE8MWr-3AJGZ89PfxWtksG6pgSq_Qw3_ilT_5_GAsiYVqK_wXaPOFsg69QzjrDamPIyD0grqYPIY32uI3JlyztUeDJFtJ-XE1FcrBU_ooIb8XrQEIhz2qoxlNbd0Et6Z9p3Xv0KMuvoxtiSB9OqfK-QOQXQkFZZlwb5si-cHyMQ-M8oi88aBX9vBXeL_x0XN8fpStvFMPJ8ctMY3Ju_OpTE9bWfG89ThKw1vp0uRlXFJjSpfzy350Xr0umuJTUT1UGjuQ3oHArNsYuhjGuLjOwgC_Qtp-HG31yHhH8_8gb3p5EbTHmnYezDu6mLrtPs6f4KPkn0x4SvmmtGRFYYMzNLSpnXt61Qcu9GgyvgM11zd5CaMSBs'
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
}
