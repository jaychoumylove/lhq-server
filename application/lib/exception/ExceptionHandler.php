<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/19
 * Time: 16:06
 */

namespace app\lib\exception;

use Exception;
use think\exception\Handle;
use think\Log;
use think\Request;

class ExceptionHandler extends Handle
{
    private $code;
    private $message;
    private $errorCode;
    private $data;
    private $request_url;

    public function render(Exception $e)
    {
        if ($e instanceof BaseException) {
            $this->code = $e->code;
            $this->message = $e->message;
            $this->data = $e->data;
            $this->errorCode = $e->errorCode;
        } else {
            if (config('app_debug')) {
                return parent::render($e);
            } else {
                $this->code = 500;
                $this->message = '未知错误';
                $this->data = null;
                $this->errorCode = '999';
                // 记录日志
                self::recordLog($e->getMessage());
            }
        }

        $this->request_url = Request::instance()->url();

        $res = [
            'errorCode' => $this->errorCode,
            'message' => $this->message,
            'data' => $this->data,
            'request_url' => $this->request_url
        ];

        return json($res, $this->code);
    }

    private function recordLog($error)
    {
        Log::record($error, 'error');
    }
}