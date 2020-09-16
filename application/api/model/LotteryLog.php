<?php


namespace app\api\model;


class LotteryLog extends \app\base\model\Base
{
    public function user()
    {
        return $this->hasOne('User', 'id', 'user_id')->field('id,nickname,avatarurl');
    }

    public function getRewardAttr($value)
    {
        return json_decode($value, true);
    }
}