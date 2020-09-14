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
        } else if ($platform == 'H5') {
            // 微信授权网页登录
            $wxApi = new WxAPI('wx3507654fa8d00974');
            $res = $wxApi->getAuth($code);
            // code has been used
            if (isset($res['errcode']) && $res['errcode'] == 40163) Common::res(['msg' => '已登录']);
            // if (isset($res['unionid'])) {
            //     $res['openid'] = UserModel::where(['unionid' => $res['unionid']])->value('openid');
            //     if (!$res['openid']) Common::res(['code' => 202, 'msg' => '请先到同名小程序进行用户授权']);
            // } else {
            //     Log::record('未获取到用户信息，缺少unionid', 'error');
            //     Common::res(['code' => 202, 'msg' => '未获取到用户信息，缺少unionid']);
            // }
        }

        if (!isset($res['openid']) || !$res['openid']) {
            // 登录失败
            Common::res(['code' => 202, 'data' => null]);
        } else {
            return $res;
        }
    }

    /**
     * 货币变动
     * @param int $uid
     * @param array $currency 货币增减额
     * @param array $recContent 日志内容
     */
    public function change($uid, $currency, $recContent = '')
    {
        $userCurrency = UserCurrency::get(['uid' => $uid]);
        $update = [];
        foreach ($currency as $key => $value) {
            if ($value > 0) {
                // 增加
                $value = '+' . $value;
            } else if ($value < 0) {
                // 减少
                if ($userCurrency[$key] < $value / -1) {
                    if ($key == 'coin') {
                        Common::res(['code' => 1, 'msg' => '金豆不足']);
                    } else if ($key == 'flower') {
                        Common::res(['code' => 1, 'msg' => '鲜花不足']);
                    } else if ($key == 'stone') {
                        Common::res(['code' => 1, 'msg' => '钻石不足']);
                    } else if ($key == 'trumpet') {
                        Common::res(['code' => 1, 'msg' => '喇叭不足']);
                    } else if ($key == 'point') {
                        Common::res(['code' => 1, 'msg' => '积分不足']);
                    } else if ($key == 'old_coin') {
                        Common::res(['code' => 1, 'msg' => '旧豆不足']);
                    } else if ($key == 'panacea') {
                        Common::res(['code' => 1, 'msg' => '灵丹不足']);
                    }
                }
            } else {
                continue;
            }
            $update[$key] = Db::raw($key . $value);
        }
        if (!$update) return;
        UserCurrency::where(['uid' => $uid])->update($update);

        if ($recContent) {
            // 记录日志
            Rec::addRec([
                'user_id' => $uid,
                'content' => $recContent,

                'coin' => isset($currency['coin']) ? $currency['coin'] : 0,
                'flower' => isset($currency['flower']) ? $currency['flower'] : 0,
                'stone' => isset($currency['stone']) ? $currency['stone'] : 0,
                'trumpet' => isset($currency['trumpet']) ? $currency['trumpet'] : 0,
                'point' => isset($currency['point']) ? $currency['point'] : 0,
                'panacea' => isset($currency['panacea']) ? $currency['panacea'] : 0,

                'before_coin' => $userCurrency['coin'],
                'before_flower' => $userCurrency['flower'],
                'before_stone' => $userCurrency['stone'],
                'before_trumpet' => $userCurrency['trumpet'],
                'before_point' => $userCurrency['point'],
                'before_panacea' => $userCurrency['panacea'],
            ]);
        }

        if (false === strpos ($recContent, '赠送给')) {
            $wealMap = [
                'stone'  => CfgWealActivityTask::USE_STONE,
                'flower' => CfgWealActivityTask::USE_FOLLOWER,
                'point'  => CfgWealActivityTask::USE_POINT,
            ];
            $panaceaMap = [
                'stone'  => CfgPanaceaTask::USE_STONE,
                'flower' => CfgPanaceaTask::USE_FOLLOWER,
                'point'  => CfgPanaceaTask::USE_POINT,
            ];
            foreach ($currency as $key => $value) {
                if (array_key_exists ($key, $wealMap)) {
                    if ((int)$value < 0) {
                        $wealType = $wealMap[$key];
                        RecWealActivityTask::setTask ($uid, abs ($value), $wealType);
                    }
                }

                if (array_key_exists ($key, $panaceaMap)) {
                    if ((int)$value < 0) {
                        $panaceaType = $panaceaMap[$key];
                        RecPanaceaTask::setTask ($uid, abs ($value), $panaceaType);
                    }
                }
            }

            if (array_key_exists ('stone', $currency) && $currency['stone'] < 0) {
                CfgWelfare::setWelfare ($uid, CfgWelfare::STONE_WELFARE, abs ($currency['stone']));
            }
        }
    }

    public function getInvitAward($ral_user_id, $uid)
    {
        Db::startTrans();
        try {
            $res = UserRelation::where([
                'rer_user_id' => $uid,
                'ral_user_id' => $ral_user_id,
                'status' => 1,
            ])->update([
                'status' => 2,
            ]);            
            if (!$res) Common::res(['code' => 1,'msg'=>'你已经领取过了，不能重复领取']);

            $this->change($uid, Cfg::getCfg('invitAward'), '拉新奖励');

            Db::commit();
        } catch (Exception $e) {
            Db::rollBack();
            Common::res(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 连续签到
     */
    public function signin($uid)
    {
        // 判定签到
        $ext = UserExt::get(['user_id' => $uid]);

        if (date('Ymd', time()) == date('Ymd', $ext['signin_time'])) {
            // 今日已签到
            return ['signin_day' => $ext['signin_day']];
        }

        if (date('Ymd', $ext['signin_time']) == date("Ymd", strtotime("-1 day"))) {
            // 连续签到
            $ext['signin_day'] += 1;
        } else {
            // 第一天签到或中途断签
            $ext['signin_day'] = 1;
        }

        // 奖励数额
        $coin = CfgSignin::where('days', '<=', $ext['signin_day'])->order('days desc')->value('coin');

        UserExt::where(['user_id' => $uid])->update([
            'signin_day' => $ext['signin_day'],
            'signin_time' => time(),
        ]);

        (new User())->change($uid, [
            'coin' => $coin,
        ], [
            'type' => 10
        ]);

        return [
            'coin' => $coin,
            'signin_day' => $ext['signin_day']
        ];
    }
}
