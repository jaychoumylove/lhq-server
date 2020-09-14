<?php

namespace app\common\model;

use app\base\model\Base;
use think\Model;

class ArticleExtra extends Base
{
    //
    public function user()
    {
        return $this->belongsTo('User','user_id','id')->field('id,nickname,avatarurl');
    }
}
