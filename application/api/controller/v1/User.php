<?php

namespace app\api\controller\v1;

use app\api\model\CfgForbiddenWords;
use app\base\controller\Base;
use app\api\model\User as UserModel;
use app\base\service\Common;
use app\api\service\User as UserService;
use app\api\model\UserItem as UserItemModel;
use app\api\model\UserCurrency;
use app\api\model\UserStar;
use app\api\model\UserRelation;
use app\api\model\UserExt;
use app\api\model\Cfg;
use app\base\service\WxAPI;
use app\api\model\CfgSignin;
use app\api\model\CfgUserLevel;
use app\api\model\FanclubUser;
use Exception;
use GatewayWorker\Lib\Gateway;
use app\api\model\RecStarChart;
use think\Db;

class User extends Base
{
    /**
     * 用户登录
     * 获取到用户的openid
     */
    public function login()
    {
        // 登录code 小程序 公众号H5
        $code = $this->req('code');

        $res['platform'] = $this->req('platform', 'require', 'MP-WEIXIN'); // 平台
        $res['model'] = $this->req('model'); // 手机型号

        if ($code) {
            // 以code形式获取openid
            $res = array_merge($res, (new UserService())->wxGetAuth($code, $res['platform']));
        } else {
            $res['openid'] = $this->req('openid');
        }

        $uid = UserModel::searchUser($res);
        $token = Common::setSession($uid);

        Common::res(['msg' => '登录成功', 'data' => ['token' => $token, 'package' => $res]]);
    }

    /**保存用户手机号 */
    public function savePhone()
    {
        $this->getUser();
        $encryptedData = input('encryptedData','');
        $iv = input('iv','');

        $phoneNumber = input('phoneNumber',0);
        $phoneCode = input('phoneCode',0);
        
        if($encryptedData && $iv){//微信获得电话号码
            
            $appid = (new WxAPI())->appinfo['appid'];
            $sessionKey = UserModel::where('id', $this->uid)->value('session_key');
            // 解密encryptedData
            $res = Common::wxDecrypt($appid, $sessionKey, $encryptedData, $iv);
            if ($res['errcode']) Common::res(['code' => 1, 'msg' => $res['data']]);
            
        }else{
            
            $sms = json_decode(UserExt::where('user_id',$this->uid)->value('sms'),true);
            $phoneNumber = strpos($phoneNumber,'86')!==false && strpos($phoneNumber,'86')==0 ? substr($phoneNumber, -11) : $phoneNumber;
            if(isset($sms['phoneNumber']) && time()-$sms['sms_time']<=24*3600 && $sms['sms_code']==$phoneCode && $sms['phoneNumber']==$phoneNumber ){
                $res['data']['phoneNumber'] = $phoneNumber;
                
            } else Common::res(['code' => 1, 'msg' => '验证码不正确']);
        }
        
        Db::startTrans();
        try {
            UserModel::where('id',$this->uid)->update(['phoneNumber'=>$res['data']['phoneNumber']]);
        
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Common::res(['code' => 1, 'msg' => '该手机号码已存在']);
        }
        
        Common::res(['data' => ['phoneNumber' => $res['data']['phoneNumber']]]);
    }
    

    /**授权&保存用户信息 */
    public function saveInfo()
    {
        $type = $this->req('type', 'require', 0);
    
        if ($type == 0) {
            // 小程序授权
            // 解密形式
            $encryptedData = $this->req('encryptedData', 'require');
            $iv = $this->req('iv', 'require');
    
            $this->getUser();
    
            $appid = (new WxAPI())->appinfo['appid'];
            $sessionKey = UserModel::where('id', $this->uid)->value('session_key');
    
            // 解密encryptedData
            $res = Common::wxDecrypt($appid, $sessionKey, $encryptedData, $iv);
            if ($res['errcode']) Common::res(['code' => 1, 'msg' => $res['data']]);
    
            // 保存
            foreach ($res['data'] as $key => $value) {
                $saveData[strtolower($key)] = $value;
            }
        } else {
            // 公众号和app授权
            // 通过openid和access_token
            $openid = $this->req('openid', 'require');
            $access_token = $this->req('access_token', 'require');
            $res = (new WxAPI())->getUserInfo($openid, $access_token);
            if (isset($res['errcode'])) Common::res(['code' => 1, 'msg' => $res]);
    
            $saveData = $res;
            $saveData['avatarurl'] = $res['headimgurl'];
            $saveData['gender'] = $res['sex'];
        }
    
        $saveData['platform'] = $this->req('platform', 'require', 'MP-WEIXIN'); // 平台
    
        // 包含用户信息和unionid的数据集合
        $data = UserModel::saveUserInfo($saveData);
        $token = Common::setSession($data['id']);
        Common::res(['data' => ['userInfo' => $data, 'token' => $token]]);
    }

    public function getInfo()
    {
        $uid = input('user_id', null);
        if (!$uid) {
            $this->getUser();
            $uid = $this->uid;
        }

        $res = UserModel::where('id', $uid)->field('id,nickname,avatarurl,type,phoneNumber')->find();

        // 粉丝团
        $res['fanclub'] = FanclubUser::with('fanclub')->where('user_id', $uid)->find()['fanclub'];
        Common::res(['data' => $res]);
    }

    /**
     * 获取用户所有货币数量
     */
    public function getCurrency()
    {
        $this->getUser();
        $res = UserCurrency::getCurrency($this->uid);

        Common::res(['data' => $res]);
    }

    /**
     * 获取用户福袋信息
     */
    public function getBlessingBag()
    {
        $this->getUser();
        $res = UserExt::where('user_id',$this->uid)->field('blessing_num,lucky_value')->find();

        Common::res(['data' => $res]);
    }

    public function getStar()
    {
        $this->getUser();

        $res = UserStar::with('Star')->where(['user_id' => $this->uid])->order('id desc')->find();
        unset($res['star']['create_time']);
        Common::res(['data' => $res['star']]);
    }

    /**
     * 获取用户道具
     */
    public function getItem()
    {
        $this->getUser();

        $item = UserItemModel::getItem($this->uid);
        Common::res(['data' => $item]);
    }

    public function invitList()
    {
        $type = input('type', 0);
        $page = input('page', 1);
        $size = input('size', 10);

        $this->getUser();
        $res = UserRelation::fixByType($type, $this->uid, $page, $size);

        Common::res(['data' => [
            'list' => $res,
            'award' => Cfg::getCfg('invitAward'),
            'hasInvitcount' => UserRelation::with('User')->where(['rer_user_id' => $this->uid, 'status' => ['in', [1, 2]]])->count()
        ]]);
    }

    public function invitAward()
    {
        $ral_user_id = $this->req('ral_user_id', 'integer');
        $this->getUser();

        (new UserService())->getInvitAward($ral_user_id, $this->uid);
        Common::res([]);
    }

    /**
     * 绑定推送客户端id
     */
    public function bindClientId()
    {
        $client_id = input('client_id');
        if (!$client_id) Common::res(['code' => 100]);

        $this->getUser();

        Gateway::bindUid($client_id, $this->uid);
        Common::res([]);
    }

    public function stealTime()
    {
        $this->getUser();
        $res = UserExt::get(['user_id' => $this->uid]);
        $leftTime = json_decode($res['left_time']);
        foreach ($leftTime as &$value) {
            $time =  Cfg::getCfg('stealLimitTime') - (time() - $value);
            if ($time < 0) {
                $time = 0;
            }
            $value = $time;
        }
        Common::res(['data' => $leftTime]);
    }
    
    public function sayworld()
    {
        $content = $this->req('content', 'require');

        $this->getUser();
        $user = UserModel::where('id', $this->uid)->field('type,nickname,avatarurl')->find();
        
        //禁言
        if ($user['type'] == 2) {
            Common::res(['code' =>1,'msg' => '您已被禁言']);
        }
        
        //记录喊话
        $userStar = UserStar::where('user_id', $this->uid)->find();
        $openTime = $userStar['open_time'];
        $currentTime = time();
        if ($openTime && $openTime > $currentTime) {
            $msg = sprintf('你已被禁言预计解封时间：%s', date('Y-m-d H:i:s', $openTime));
            Common::res(['code' => 1, 'msg' => $msg]);
        }

        // 检测发言内容
        RecStarChart::verifyWord($content);

        $starid = $userStar['star_id'];
        RecStarChart::create([
            'user_id' => $this->uid,
            'star_id' => $starid,
            'content' => $content,
            'type' => 1,
            'create_time' => time(),
        ]);        
        
        // 扣除喇叭
        (new UserService())->change($this->uid, [
            'trumpet' => -1
        ], '喊话');

        // 没有广告语 推送socket消息
        if(CfgForbiddenWords::noAds($content)) Gateway::sendToAll(json_encode([
            'type' => 'sayworld',
            'data' => [
                'avatarurl' => $user['avatarurl'],
                'content' => $content,
                'nickname' => $user['nickname'],
            ],
        ], JSON_UNESCAPED_UNICODE));

        Common::res();
    }

    /**退出圈子 */
    public function exit()
    {
        $this->getUser();
        UserStar::exit($this->uid);
        Common::res([]);
    }

    public function neverQuit()
    {
        $this->getUser();
        $res = UserStar::neverQuit($this->uid);
        if (empty($res)) {
            Common::res (['code' => 1, 'msg' => '请稍后再试']);
        }
        Common::res();
    }

    public function signin()
    {
        $this->getUser();

        $cfg = CfgSignin::all();

        $res = (new UserService())->signin($this->uid);
        $res['cfg'] = $cfg;

        Common::res(['data' => $res]);
    }

    /**礼物兑换金豆 */
    public function recharge()
    {
        $item_id = input('item_id');
        $num = input('num');
        if (!$item_id || !$num || $num < 0) Common::res(['code' => 100]);
        $this->getUser();

        UserItemModel::recharge($this->uid, $item_id, $num);

        Common::res([]);
    }

    /**加好友 */
    public function addFriend()
    {
        $user_id = input('user_id');
        if (!$user_id || $user_id == 'undefined') Common::res(['code' => 100]);

        $this->getUser();

        UserRelation::addFriend($this->uid, $user_id);

        Common::res();
    }

    /**删好友 */
    public function delFriend()
    {
        $user_id = input('user_id');
        if (!$user_id || $user_id == 'undefined') Common::res(['code' => 100]);

        $this->getUser();

        UserRelation::delFriend($this->uid, $user_id);
        Common::res();
    }

    /**送给他人 */
    public function sendToOther()
    {
        $user_id = $this->req('user_id', 'integer');
        $num = $this->req('num', 'integer');
        $type = $this->req('type', 'require');
        $this->getUser();

        UserCurrency::sendToOther($this->uid, $user_id, $num, $type);
        Common::res();
    }


    public function sendItemToOther()
    {
        $user_id = input('user_id');
        $item_id = input('item_id'); // 礼物id
        if (!$user_id || !$item_id || $user_id == 'undefined') Common::res(['code' => 100]);

        $num = input('num', 1);
        $this->getUser();

        UserItemModel::sendItemToOther($this->uid, $user_id, $num, $item_id);
        Common::res();
    }

//    public function forbidden()
//    {
//        $user_id = $this->req('user_id', 'integer');
//        $this->getUser();
//        if (UserStar::getStarId($user_id) != UserStar::getStarId($this->uid)) Common::res(['code' => 1]);
//
//        $type = 2;
//
//        UserModel::where('id', $user_id)->update(['type' => $type]);
//        Common::res();
//    }
/** 禁言时间加载*/
    public function biddenTime(){
        $res=Db::name('cfg_forbidden')->where('delete_time','null')->select();
        Common::res(['data'=>$res]);
    }
    /**禁言 */
    public function forbidden()
    {
        $user_id = $this->req('user_id', 'integer');
        $time = input('time', 0);
        $times=time()+$time;
        $this->getUser();
        if (UserStar::getStarId($user_id) != UserStar::getStarId($this->uid)) Common::res(['code' => 1]);

        // 封禁
        $isDone = Db::name('user_star')->where('user_id', $user_id)->update(['open_time' => $times]);
        if($isDone) Common::res(['msg' => '封禁成功']);

    }

    /**团战积分 */
    public function extraCurrency()
    {
        $this->getUser();
        $res['score'] = 0; //round(Db::name('pk_user_rank')->where('uid', $this->uid)->order('id desc')->value('score') / 10000);
        Common::res(['data' => $res]);
    }

    /**点赞 */
    public function like()
    {
        $user_id = $this->req('user_id', 'integer');
        $this->getUser();

        UserExt::like($this->uid, $user_id);
        Common::res();
    }

    public function level()
    {
        $this->getUser();
        $user_id = $this->req('user_id', 'integer');

        $count = UserStar::where('user_id', $user_id)->order('id desc')->value('total_count');
        $res['level'] = CfgUserLevel::where('total', '<=', $count)->max('level');
        $nextCount = CfgUserLevel::where('total', '>', $count)->order('level asc')->value('total');
        $res['gap'] = $nextCount - $count;
        if ($res['gap'] < 0) $res['gap'] = 0;
        Common::res(['data' => $res]);
    }

    public function edit()
    {
        $this->getUser();
        if(UserModel::where('id', $this->uid)->value('type')==3) Common::res(['code' => 1, 'msg' => '暂停修改用户信息，钻石未扣除']);
        $res['avatarurl'] = $this->req('avatar', 'require');
        $res['nickname'] = $this->req('nickname', 'require');
        (new WxAPI())->msgCheck($res['nickname']);//非法词检测

        Db::startTrans();
        try {
            (new UserService)->change($this->uid, ['stone' => -100], '修改个人信息');
            UserModel::where('id', $this->uid)->update($res);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Common::res(['code' => 400, 'msg' => $e->getMessage()]);
        }

        Common::res();
    }
}
