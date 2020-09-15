<?php

namespace app\api\model;

use app\base\model\Base;

class Task extends Base
{
    const SIGN = 'SIGN';
    const INVITE = 'INVITE';
    const VIDEO_KEY = 'VIDEO_KEY';
    const DAY_KEY = 'DAY_KEY';

    public function getRewardAttr($value)
    {
        return json_decode($value, true);
    }
}
