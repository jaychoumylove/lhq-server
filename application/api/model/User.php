<?php

namespace app\api\model;

use app\base\model\Base;
use app\base\service\Common;
use think\Db;

class User extends Base
{
    /**
     * 创建用户
     * @return integer uid 用户id
     */
    public static function searchUser($data, $referrer = 0)
    {
        Db::startTrans();
        try {
            if ($data['platform'] == 'APP') {
                $openidType = 'openid_app';
            } else if ($data['platform'] == 'H5') {
                $openidType = 'openid_h5';
            } else if ($data['platform'] == 'MP-WEIXIN' || $data['platform'] == 'MP-QQ') {
                $openidType = 'openid';
            }
            $user = self::get([$openidType => $data['openid']]);
            if (!$user) {
                // 创建新用户
                // User
                $user = self::create([
                    $openidType => isset($data['openid']) ? $data['openid'] : null,
                    'session_key' => isset($data['session_key']) ? $data['session_key'] : null,

                    'platform' => isset($data['platform']) ? $data['platform'] : null,
                    'model' => isset($data['model']) ? $data['model'] : null,
                ]);

                $stateData = [
                    'user_id' => $user['id'],
                    'qrcode' => '',
                    'key_num' => 1,
                ];

                if ($referrer) {
                    // 用户关系
                    $stateData['spread_uid'] = $referrer;
                }

                // 创建新
                UserState::create($stateData);
                if ($referrer) {
                    // 更新拉新人数
                    Task::addInvited($referrer);
                }

                Task::invitedInit($user['id']);
            } else {
                if (isset($data['session_key'])) {
                    self::where('id', $user['id'])->update(['session_key' => $data['session_key']]);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Common::res(['code' => 400, 'msg' => $e->getMessage()]);
        }

        return $user['id'];
    }

    /**保存用户信息 */
    public static function saveUserInfo($data)
    {
        if ($data['platform'] == 'APP') {
            $openidType = 'openid_app';
        } else if ($data['platform'] == 'H5') {
            $openidType = 'openid_h5';
        } else if ($data['platform'] == 'MP-WEIXIN' || $data['platform'] == 'MP-QQ') {
            $openidType = 'openid';
        }

        // 寻找是否有已存在的账号unionid相同但openid为空
        if (isset($data['unionid']) && $data['unionid']) {
            $optherPlatformUid = self::where('unionid', $data['unionid'])->where($openidType, 'null')->value('id');
        } else {
            $data['unionid'] = null;
        }
        $currentUid = self::where($openidType, $data['openid'])->value('id');
        if (isset($optherPlatformUid) && $optherPlatformUid) {
            // 在其他平台已有账号
            // 删除当前用户
            self::where('id', $currentUid)->delete(true);

            $currentUid = $optherPlatformUid;
        }

        $user = self::get($currentUid);
        $update = [
            $openidType => isset($data['openid']) ? $data['openid'] : null,
            'unionid' => isset($data['unionid']) ? $data['unionid'] : null,
        ];

        if ($data['platform'] == 'MP-WEIXIN' || $data['platform'] == 'MP-QQ' || !$user['nickname']) {
            // 如果是微信小程序或者用户没有授权则更新用户资料
            $update = array_merge($update, [
                'nickname' => isset($data['nickname']) ? $data['nickname'] : null,
                'avatarurl' => isset($data['avatarurl']) ? $data['avatarurl'] : null,
                'gender' => isset($data['gender']) ? $data['gender'] : null,
                'language' => isset($data['language']) ? $data['language'] : null,
                'city' => isset($data['city']) ? $data['city'] : null,
                'province' => isset($data['province']) ? $data['province'] : null,
                'country' => isset($data['country']) ? $data['country'] : null,
            ]);
            if(!$update['nickname']) unset($update['nickname']);
        }
        self::where('id', $currentUid)->update($update);
        return self::get($currentUid);
    }

    public static function getInfo($id, $field = 'id,avatarurl,nickname')
    {
        return self::where('id', $id)->field($field)->find();
    }
}
