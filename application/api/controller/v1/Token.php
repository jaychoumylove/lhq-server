<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/29
 * Time: 15:53
 */

namespace app\api\controller\v1;


use app\api\controller\BaseController;
use app\api\service\UserToken;
use app\api\validate\LoginValidate;
use app\lib\exception\SuccessMessage;

class Token extends BaseController
{

    public function getUserToken($mobile, $password)
    {
        (new LoginValidate())->goCheck();

        $token = UserToken::get($mobile, $password);

        throw new SuccessMessage([
            'data' => ['token' => $token]
        ]);
    }

}