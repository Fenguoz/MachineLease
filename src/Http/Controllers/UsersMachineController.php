<?php

namespace Fenguoz\MachineLease\Http\Controllers;

use App\Libraries\Send;
use Fenguoz\MachineLease\Exceptions\CommonException;
use Fenguoz\MachineLease\Exceptions\MachineException;
use Fenguoz\MachineLease\Services\UsersMachineService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UsersMachineController extends Controller
{
    use Send;

    public function getMachineList(Request $request, UsersMachineService $usersMachineService)
    {
        $params = [];
        $option = [];
        $params['user_id'] = (int) $request->get('user_id');
        $option['count'] = $request->input('count') ? (int) $request->input('count') : 10;
        $data = $usersMachineService->getMachineList($params, $option);
        return self::success($data);
    }

    public function getUserInfo(Request $request, UsersMachineService $usersMachineService)
    {
        $user_id = (int) $request->get('user_id');
        $data = $usersMachineService->getUserInfo($user_id);
        return self::success($data);
    }

    public function getMachineOutput(Request $request, UsersMachineService $usersMachineService)
    {
        $params = [];
        $option = [];
        $params['user_id'] = (int) $request->get('user_id');
        if ((int) $params['user_id'] <= 0)
            throw new CommonException(CommonException::USER_ID_ERROR);

        $params['machine_id'] = $request->input('machine_id') ? (int) $request->input('machine_id') : 0;
        $option['count'] = $request->input('count') ? (int) $request->input('count') : 10;
        $data = $usersMachineService->getMachineOutput($params, $option);
        return self::success($data);
    }


    public function machineList(Request $request, UsersMachineService $usersMachineService)
    {
        $params = [];
        $option = [];
        $option['count'] = $request->input('count') ? (int) $request->input('count') : 10;
        $data = $usersMachineService->getMachineList($params, $option);
        return self::success($data);
    }

    public function machineOutput(Request $request, UsersMachineService $usersMachineService)
    {
        $params = [];
        $option = [];
        $params['machine_id'] = $request->input('machine_id') ? (int) $request->input('machine_id') : 0;
        $option['count'] = $request->input('count') ? (int) $request->input('count') : 10;
        $data = $usersMachineService->getMachineOutput($params, $option);
        return self::success($data);
    }
}
