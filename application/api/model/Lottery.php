<?php


namespace app\api\model;


class Lottery extends \app\base\model\Base
{
    public function getRewardAttr($value)
    {
        return json_decode($value, true);
    }
}