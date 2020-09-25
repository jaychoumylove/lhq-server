<?php

namespace app\api\controller;


use app\api\model\AnimalLottery;
use app\api\model\CfgAnimal;
use app\api\model\CfgAnimalLevel;
use app\api\model\Notice;
use app\api\model\User;
use app\base\controller\Base;
use app\base\model\Appinfo;
use app\base\service\WxAPI;
use Exception;
use think\Db;
use app\base\service\Common;
use app\api\model\CfgTaskgiftCategory;
use app\api\model\RecTaskgift;
use app\api\model\CfgTaskgift;
use app\api\model\CfgBadge;
use app\api\model\Prop;
use app\api\model\CfgHeadwear;
use app\api\model\UserStar;
use app\api\service\User as UserService;
use app\api\model\StarRank as StarRankModel;
use app\api\model\Fanclub;
use app\api\model\RecHour;
use app\api\model\PkUser;
use app\api\model\RecTask;
use app\api\model\BadgeUser;
use app\api\model\FanclubUser;
use app\api\model\RecPayOrder;
use think\File;
use think\Response;

class Test extends Base
{
    
    public function getToken()
    {
        echo Common::setSession(input('uid') / 1234);
    }
    
    public function getUid()
    {
        echo Common::getSession(input('token'));
    }

    public function s()
    {
        $user = User::get(680026);
        $notice = Notice::where('user_id', $user['id'])
            ->where('type', 1)
            ->find();

        $openid = $user['openid'];
        $data = [
            'openid'  => $openid,
            'balance' => $notice['extra']['balance'],
            'point'   => $notice['extra']['point'],
            'date'    => date('Y-m-d', strtotime($notice['create_time'])),
        ];

        (new WxAPI())->sendTemplateMini($data);
    }
}
