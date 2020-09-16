<?php

namespace app\api\controller\v1;

use app\api\model\UserState;
use app\base\controller\Base;
use app\api\model\User as UserModel;
use app\base\service\Common;
use think\Db;

class UserRank extends Base
{

    public function pointRankInfo()
    {
        $page = input('page', 1);
        $size = input('size', 20);
        $this->getUser();
        $res = self::pointRank($this->uid, $page, $size);
        $res['rankInfo'] = [];


        Common::res(['data' => $res]);
    }

    public static function pointRank($uid, $page, $size)
    {

        $res['list'] = UserState::with('user')->where('point', '>', 0)->field('id,user_id,point')->order('point desc,id desc')->page($page, $size)->select();
        $res['myInfo'] = UserModel::where('id', $uid)->field('id,nickname,avatarurl')->find();
        $res['myInfo']['point'] = UserState::where(['user_id' => $uid])->value('point');
        $res['myInfo']['rank'] = (UserState::where('point', '>', $res['myInfo']['point'])->order('point desc,id desc')->count()) + 1;

        return $res;
    }
}
