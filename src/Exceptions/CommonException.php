<?php

namespace Fenguoz\MachineLease\Exceptions;

class CommonException extends \Exception
{
    const UNKONW = 72100;
    const USER_ERROR = 72101;
    const DATA_ERRPR = 72102;

    static public $__names = array(
        self::UNKONW => 'UNKNOWN',
        self::USER_ERROR => 'USER_ERROR',
        self::DATA_ERRPR => 'DATA_ERRPR',
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
