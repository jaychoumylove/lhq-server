<?php

namespace app\api\controller;


use app\api\model\AnimalLottery;
use app\api\model\CfgAnimal;
use app\api\model\CfgAnimalLevel;
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

    public function getUserQrCode()
    {
        $this->getUser();
//        $qrcode = UserState::where('user_id', $this->uid)->value('qrcode');
//        if (!$qrcode) {
//            if (Appinfo::where(['id' => 1])->value('access_token_expire') < date('Y-m-d H:i:s')) {
//                (new WxAPI())->getAccessToken();
//            }
//            $getQrcode = (new WxAPI())->getUnlimited('/pages/index/index','referrer='.$this->uid);
//            if (isset($getQrcode['errcode']) && $getQrcode['errcode'] != 0) Common::res(['code' => $getQrcode['errcode'], 'msg' => $getQrcode['errmsg']]);
//            $getQrcode   = base64_encode($getQrcode);
//            $qrcode = 'data:image/png;base64,'.$getQrcode;
//            UserState::where('user_id', $this->uid)->update([
//                'qrcode'=>$qrcode
//            ]);
//        }

        if (Appinfo::where(['id' => 1])->value('access_token_expire') < date('Y-m-d H:i:s')) {
            (new WxAPI())->getAccessToken();
        }
        $getQrcode = (new WxAPI())->getUnlimited('/pages/index/index','referrer='.$this->uid);
        if (isset($getQrcode['errcode']) && $getQrcode['errcode'] != 0) Common::res(['code' => $getQrcode['errcode'], 'msg' => $getQrcode['errmsg']]);
//        $getQrcode   = base64_encode($getQrcode);
//        $qrcode = 'data:image/png;base64,'.$getQrcode;
//        $image_parts = explode(";base64,", $qrcode);
//        $image_type_aux = explode("image/", $image_parts[0]);
//        $image_type = $image_type_aux[1];
//        $image_base64 = base64_decode($image_parts[1]);
        $file = ROOT_PATH . 'public' . DS . 'uploads' . DS . uniqid() . '.png';
        file_put_contents($file, $getQrcode);
        $this->uploadwX($file);
    }

    public function uploadwX($realPath)
    {
        // 上传到微信
        $gzh_appid = Appinfo::where(['type' => 'gzh','status'=>0])->value('appid');
        if(!$gzh_appid) Common::res(['code' => 1, 'msg' => '图片服务器不可用，请联系客服']);

        $res = (new WxAPI($gzh_appid))->uploadimg($realPath);
        if (isset($res['errcode']) && $res['errcode'] == 45009){//公众号达到日极限
            Appinfo::where(['appid' => $gzh_appid])->update(['status'=>-1]);
            Common::res(['code' => 1, 'msg' => '上传失败，请重试一次']);
        }

        //获取到地址才返回
        if(isset($res['url'])){
            $res['https_url'] = str_replace('http', 'https', $res['url']);
            unlink($realPath);
            Common::res(['data' => $res]);
        }

    }
}
