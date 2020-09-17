<?php


namespace app\api\model;

use app\base\model\Base;
use app\api\service\User as UserService;

class UserState extends Base
{
    public function user()
    {
        return $this->hasOne('User', 'id', 'user_id')->field('id,nickname,avatarurl');
    }

    public static function changePointWithSpread($uid,$spread_uid, $number)
    {
        $firstNumber = bcmul($number, 0.1);

        (new UserService())->change($spread_uid,[
            'point'           => $firstNumber,
            'recommend_count' => $firstNumber,
            'first_count'     => $firstNumber,
        ],['type' => 1, 'content' => '直邀好友抽奖获得','target_user_id' => $uid]);

        $first_spread_uid  = UserState::where('user_id', $spread_uid)->value('spread_uid');
        if ($first_spread_uid > 0) {
            $secondNumber = bcmul($number, 0.05);

            (new UserService())->change($first_spread_uid,[
                'point'           => $secondNumber,
                'recommend_count' => $secondNumber,
                'first_count'     => $secondNumber,
            ],['type' => 1, 'content' => '扩散好友抽奖获得','target_user_id' => $uid]);

        }
    }
}