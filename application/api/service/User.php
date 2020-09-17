<?php

namespace app\api\service;

use app\api\model\Lock;
use app\api\model\UserState;
use app\base\service\WxAPI;
use app\base\service\Common;
use think\Db;
use app\api\model\Rec;
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

    /**
     * 货币变动
     * @param int $uid
     * @param array $currency 货币增减额
     * @param array $rec 日志存入 ['type' => 1, 'content' => ‘’]
     */
    public function change($uid, $currency, $rec = null)
    {
        //结榜时停止操作
        $lock1 = Lock::getVal('day_end');
        $lock2 = Lock::getVal('week_end');
        $lock3 = Lock::getVal('month_end');
        if($lock1['value'] == 1 || $lock2['value'] == 1 || $lock3['value'] == 1){
            Common::res(['code' => 2, 'msg' => '结榜中，请稍后']);
        }

        $userCurrency = UserState::get(['user_id' => $uid]);

        $update = [];
        foreach ($currency as $key => $value) {
            if ($value > 0) {
                // 增加
                $value = '+' . $value;
            } else if ($value < 0) {
                // 减少
                if ($userCurrency[$key] < $value / -1) {
                    // 货币不足
                    switch ($key) {
                        case 'point':
                            Common::res(['code' => 1, 'msg' => '贝壳不足']);
                            break;
                        case 'key_num':
                            Common::res(['code' => 1, 'msg' => '钥匙不足']);
                            break;

                        default:
                            # code...
                            break;
                    }
                }
            } else {
                continue;
            }
            $update[$key] = Db::raw($key . $value);
        }
        if (!$update) return;
        $updated = UserState::where(['user_id' => $uid])->update($update);
        if (empty($updated)) {
            Common::res(['code' => 1, 'msg' => '更新出错']);
        }

        if ($rec) {
            // 记录日志
            $recSave = ['user_id' => $uid, 'type' => $rec['type']];
            $recSave['key_num'] = isset($currency['key_num']) ? $currency['key_num'] : 0;
            $recSave['point'] = isset($currency['point']) ? $currency['point'] : 0;
            $recSave['content'] = isset($rec['content']) ? $rec['content'] : null;
            $recSave['target_user_id'] = isset($rec['target_user_id']) ? $rec['target_user_id'] : null;

            Rec::create($recSave);
        }
    }
}
