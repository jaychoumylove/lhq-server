<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/28
 * Time: 16:08
 */

namespace app\lib\exception;


class WechatException extends BaseException
{
    public $code = 401;
    public $message = '微信错误';
    public $errorCode = 30000;
}