<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/29
 * Time: 16:21
 */

namespace app\lib\exception;


class InfoException extends BaseException
{
    public $code = 404;
    public $message = '查询信息未找到';
    public $errorCode = 40000;
}