<?php

namespace app\api\model;

use app\base\model\Base;
use app\base\service\Common;
use think\Db;

class User extends Base
{
    public function UserStar()
    {
        return $this->hasOne('UserStar', 'user_id', 'id', [], 'LEFT');
    }
    public function UserExt()
    {
        return $this->hasOne('UserExt', 'user_id', 'id', [], 'LEFT');
    }

    public function achievement()
    {
        return $this->hasOne ('UserAchievementHeal', 'user_id', 'id');
    }

    public function Sprite()
    {
        return $this->hasOne('UserSprite', 'user_id', 'id', [], 'LEFT');
    }

    /**
     * 创建用户
     * @return integer uid 用户id
     */
    public static function searchUser($data)
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
                    'type' => isset($data['type']) ? $data['type'] : 0,
                ]);
                // UserCurrency
                $currency = [
                    'uid' => $user['id'],
                ];
                if (isset($data['type']) && $data['type'] == 1) {
                    // 1管理员
                    $currency['coin'] =  100000;
                    $currency['flower'] =  10000;
                    $currency['stone'] =  300;
                    $currency['trumpet'] = 100;
                } else {
                    $currency['coin'] =  100;
                    $currency['flower'] =  100;
                    $currency['stone'] =  3;
                    $currency['trumpet'] = 1;
                }
                UserCurrency::create($currency);

                // UserExt
                UserExt::create([
                    'user_id' => $user['id'],
                ]);

                // UserSprite
                UserSprite::create([
                    'user_id' => $user['id'],
                    'settle_time' => time(),
                ]);
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
            // UserCurrency
            UserCurrency::where('uid', $currentUid)->delete(true);
            // UserExt
            UserExt::where('user_id', $currentUid)->delete(true);
            // UserSprite
            UserSprite::where('user_id', $currentUid)->delete(true);

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

    /**创建虚拟用户 */
    public static function createVirtualUser($data)
    {
        $vrNickname = OtherFakeUser::where('1=1')->orderRaw('rand()')->value('nickname');
        $vrAvatar = OtherFakeUser::where('1=1')->orderRaw('rand()')->value('avatar');

        return self::searchUser([
            'platform' => $data['platform'],
            'openid' => $data['openid'],
            'unionid' => $data['unionid'],
            'nickname' => $vrNickname,
            'avatarurl' => $vrAvatar,
            'type' => 1 // 虚拟用户type
        ]);
    }

    /**创建机器人用户 */
    public static function createAndroid($vrNickname, $vrAvatar)
    {
        $rdCode = Common::getRandCode(24);

        return self::searchUser([
            'openid' => $rdCode,
            'unionid' => $rdCode,
            'nickname' => $vrNickname,
            'avatarurl' => $vrAvatar,
            'type' => 5 // 机器人用户type
        ]);
    }

    /**随机获取一个圈子内的机器人 */
    public static function getOneAndroid($starid, $limit_hot)
    {
        $uid = Db::name('user_star')->alias('s')->join('user u', 'u.id = s.user_id')
            ->where('u.type', 5)->where('s.star_id', $starid)->where('s.thisweek_count', '<', $limit_hot)
            ->orderRaw('rand()')->value('u.id');

        if (!$uid) Common::res(['code' => 1, 'msg' => '该圈子未找到Android']);

        return $uid;
    }
}
