<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/19
 * Time: 16:30
 */

namespace app\lib\exception;

use Exception;

class BaseException extends Exception
{
    // http 状态码
    public $code = 400;
    // 错误提示信息
    public $message = '参数错误';
    // 错误码
    public $errorCode = 10000;
    // 返回数据
    public $data = null;

    public function __construct(array $param = []){
        if (!is_array($param)) {
            return;
        }
        if (array_key_exists('code', $param)) {
            $this->code = $param['code'];
        }
        if (array_key_exists('message', $param)) {
            $this->message = $param['message'];
        }
        if (array_key_exists('errorCode', $param)) {
            $this->errorCode = $param['errorCode'];
        }
        if (array_key_exists('data', $param)) {
            $this->data = $param['data'];
        }
    }
}