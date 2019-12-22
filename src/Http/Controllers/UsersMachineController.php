<?php

namespace Fenguoz\MachineLease\Http\Controllers;

use App\Libraries\Send;
use Fenguoz\MachineLease\Services\UsersMachineService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UsersMachineController extends Controller
{
    use Send;

    public function machineList(Request $request, UsersMachineService $usersMachineService)
    {
        $params = [];
        $option = [];
        $params['user_id'] = (int)$request->get('user_id');
        $option['count'] = $request->input('count') ? (int)$request->input('count') : 10;
        $data = $usersMachineService->getMachineList($params,$option);
        return self::success($data);
    }

    public function userInfo(Request $request, UsersMachineService $usersMachineService)
    {
        
    }

    public function machineOutput(Request $request, UsersMachineService $usersMachineService)
    {
        
    }
}
