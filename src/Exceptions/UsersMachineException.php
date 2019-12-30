<?php

namespace Fenguoz\MachineLease\Exceptions;

class UsersMachineException extends \Exception
{
    const MACHINE_NOT_EXIST = 72301;
    const MACHINE_CANT_EXTEND = 72302;
    const NOT_PERMISSION = 72303;
    const MACHINE_GOODS_NOT_EXIST = 72304;

    static public $__names = array(
        self::MACHINE_NOT_EXIST => 'MACHINE_NOT_EXIST',
        self::MACHINE_CANT_EXTEND => 'MACHINE_CANT_EXTEND',
        self::MACHINE_GOODS_NOT_EXIST => 'MACHINE_GOODS_NOT_EXIST',
    );

    /**
     * CommonException constructor.
     * @param string $code
     * @param string $replace
     */
    public function __construct($code, $replace = '')
    {
        $message = self::$__names[$code];
        if (!empty($replace)) {
            if (is_string($replace)) {
                $message = $replace;
            }
            if (is_array($replace)) {
                foreach ($replace as $k => $v) {
                    $message = str_replace(':' . $k, $v, $message);
                }
            }
        }
        parent::__construct($message, $code);
    }

    /**
     * @return mixed
     */
    public function render()
    {
        return response()->json([
            'code' => $this->code,
            'message' => $this->message,
            'data' => []
        ]);
    }
}
