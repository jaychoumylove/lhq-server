<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/23
 * Time: 13:02
 */

namespace app\api\service;


use app\lib\enum\ScopeEnum;
use app\lib\exception\ForbiddenException;
use app\lib\exception\TokenException;
use think\Cache;
use think\Request;

class Token
{
    protected static function getIdentity($key)
    {
        $Identity_arr = [
            'user' => ScopeEnum::User,
            'other' => ScopeEnum::Other,
            'admin' => ScopeEnum::Admin
        ];

        if (array_key_exists($key, $Identity_arr)) {
            return $Identity_arr[$key];
        }

        throw new TokenException([
            'message' => '校验的身份不存在',
            'errorCode' => 10002
        ]);
    }

    protected static function createRandKey()
    {
        $randChar = getRandChar(32);
        $timestamp = time();

        return md5($randChar . $timestamp);
    }

    protected static function saveCache($value)
    {
        $key = self::createRandKey();
        $expire_in = config('setting.token_expire_in');
        $res = Cache::store('redis')->set($key, $value, $expire_in);
        if (!$res) {
            throw new TokenException([
                'message' => '服务器缓存异常',
                'errorCode' => '10003'
            ]);
        }

        return $key;
    }

    public static function getCurrentTokenVar($key)
    {
        $token = Request::instance()->header('token');

        $info = Cache::store('redis')->get($token);

        if (!$info || !is_array($info) || !array_key_exists($key, $info)) {
            throw new TokenException([
                'message' => 'Token无效或已过期'
            ]);
        }

        return $info[$key];
    }

    public static function authentication($auth)
    {
        $Identity = self::getIdentity($auth);

        $scope = self::getCurrentTokenVar('scope');

        if (!$scope) {
            throw new TokenException([
                'message' => '身份认证失败，请登陆'
            ]);
        }

        if ($scope != $Identity) {
            throw new ForbiddenException([
                'message' => '你无权访问'
            ]);
        }
    }

}