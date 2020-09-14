<?php

namespace app\api\controller\v1;

use app\base\controller\Base;
use app\api\model\User as UserModel;
use app\base\service\Common;
use app\api\service\User as UserService;
use app\base\service\WxAPI;
use Exception;
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

        if($encryptedData && $iv){//微信获得电话号码
            
            $appid = (new WxAPI())->appinfo['appid'];
            $sessionKey = UserModel::where('id', $this->uid)->value('session_key');
            // 解密encryptedData
            $res = Common::wxDecrypt($appid, $sessionKey, $encryptedData, $iv);
            if ($res['errcode']) Common::res(['code' => 1, 'msg' => $res['data']]);
        } else {
            Common::res(['code' =>1, 'msg' => '请选择微信用户']);
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
        Common::res(['data' => $res]);
    }
}
