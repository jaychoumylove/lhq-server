<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/28
 * Time: 18:54
 */

namespace app\lib\exception;


class TokenException extends BaseException
{
    public $code = 401;
    public $message = 'token无效或已过期';
    public $errorCode = 10002;
}