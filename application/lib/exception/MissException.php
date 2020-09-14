<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/29
 * Time: 14:58
 */

namespace app\lib\exception;


class MissException extends BaseException
{
    public $code = 404;
    public $message = '404页面不存在';
    public $errorCode = 998;
}