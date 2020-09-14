<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/28
 * Time: 15:57
 */

namespace app\api\service;


use app\api\model\User;
use app\lib\enum\ScopeEnum;
use app\lib\exception\TokenException;

class UserToken extends Token
{
    public static function get($mobile, $password)
    {
        $user = User::check($mobile, $password);
        if (!$user) {
            throw new TokenException([
                'message' => '账户名或密码错误',
                'errorCode' => 10002
            ]);
        }

        $value = [
            'timestamp' => time(),
            'uid' => $user['id'],
            'scope' => ScopeEnum::User
        ];

        $result = self::saveCache($value);

        return $result;
    }

}