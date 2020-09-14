<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/28
 * Time: 19:22
 */

namespace app\lib\exception;


class SuccessMessage extends BaseException
{
    public $code = 200;
    public $message = 'OK';
    public $errorCode = 0;
}