<?php

namespace Fenguoz\MachineLease\Exceptions;

class MachineException extends \Exception
{
    const MACHINE_ID_ERROR = 72200;

    static public $__names = array(
        self::MACHINE_ID_ERROR => 'MACHINE_ID_ERROR',
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
