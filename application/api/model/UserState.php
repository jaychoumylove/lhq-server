<?php


namespace app\api\model;


class UserState extends \app\base\model\Base
{
    public function user()
    {
        return $this->hasOne('User', 'id', 'user_id')->field('id,nickname,avatarurl');
    }
}