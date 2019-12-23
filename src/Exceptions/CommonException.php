<?php

namespace Fenguoz\MachineLease\Exceptions;

class CommonException extends \Exception
{
    const UNKONW = 72100;
    const USER_ERROR = 72101;
    const DATA_ERRPR = 72102;
    const USER_ID_ERROR = 72103;
    const USER_ID_EMPTY = 72104;
    const USER_NOT_EXIST = 72105;

    static public $__names = array(
        self::UNKONW => 'UNKNOWN',
        self::USER_ERROR => 'USER_ERROR',
        self::DATA_ERRPR => 'DATA_ERRPR',
        self::USER_ID_ERROR => 'USER_ID_ERROR',
        self::USER_ID_EMPTY => 'USER_ID_EMPTY',
        self::USER_NOT_EXIST => 'USER_NOT_EXIST',
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
