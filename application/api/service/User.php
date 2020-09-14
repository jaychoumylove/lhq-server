<?php

namespace app\api\service;

use app\api\model\CfgPanaceaTask;
use app\api\model\CfgWealActivityTask;
use app\api\model\CfgWelfare;
use app\api\model\RecPanaceaTask;
use app\api\model\RecWealActivityTask;
use app\api\model\Welfare;
use app\base\service\WxAPI;
use app\base\service\Common;
use app\api\model\User as UserModel;
use app\api\model\UserCurrency;
use Exception;
use think\Db;
use app\api\model\UserRelation;
use app\api\model\Rec;
use app\api\model\Cfg;
use app\api\model\CfgSignin;
use app\api\model\UserExt;
use think\Log;

class User
{
    /**
     * 获取用户openid等信息
     */
    public function wxGetAuth($code, $platform)
    {
        if ($platform == 'MP-WEIXIN') {
            // 微信小程序登录
            $wxApi = new WxAPI('miniapp');
            $res = $wxApi->code2session($code);
        } else if ($platform == 'MP-QQ') {
            // QQ小程序登录
            $wxApi = new WxAPI('qq');
            $res = $wxApi->code2session($code);
        }

        if (!isset($res['openid']) || !$res['openid']) {
            // 登录失败
            Common::res(['code' => 202, 'data' => null]);
        } else {
            return $res;
        }
    }
}
