<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/28
 * Time: 19:19
 */

namespace app\api\validate;


class LoginValidate extends BaseValidate
{
    protected $rule = [
        'mobile' => 'require|isMobile',
        'password' => 'require|checkPwd',
        'code' => 'require|checkSMSCode'
    ];

    protected $message = [
        'mobile' => '手机号码格式不正确',
        'password' => '密码为6~16位数的数字或者字母',
        'code' => '验证码格式不正确'
    ];
}