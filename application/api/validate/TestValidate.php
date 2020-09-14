<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/19
 * Time: 11:25
 */

namespace app\api\validate;


class TestValidate extends BaseValidate
{
    protected $rule = [
        'id' => 'require',
        'name' => 'require|email',
        'mobile' => 'require|isMobile'
    ];

    protected $message = [
        'id' => 'id不能为空',
        'name' => 'name不能为空name不能为空',
        'mobile' => '手机号码格式不正确'
    ];

}