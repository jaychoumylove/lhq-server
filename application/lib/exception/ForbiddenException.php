<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/28
 * Time: 21:33
 */

namespace app\lib\exception;


class ForbiddenException extends BaseException
{
    public $code = 401;
    public $message = '无权访问';
    public $errorCode = 10004;
}