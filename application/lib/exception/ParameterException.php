<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/19
 * Time: 16:34
 */

namespace app\lib\exception;

class ParameterException extends BaseException
{
    public $code = 401;
    public $message = '参数错误';
    public $errorCode = 10001;
}