<?php

namespace Fenguoz\MachineLease\Services;

use App\Services\Service;
use Fenguoz\MachineLease\Exceptions\CommonException;
use Fenguoz\MachineLease\Exceptions\UsersMachineException;
use Fenguoz\MachineLease\Models\UsersMachineModel;

class UsersMachineService extends Service
{

    public function getMachineList(array $params = [], array $option = [])
    {
        $count = isset($option['count']) ? $option['count'] : 10;
        $data = UsersMachineModel::where($params)->paginate($count);
        if(!$data)
            throw new CommonException(CommonException::DATA_ERRPR);

        return $data;
    }
}
