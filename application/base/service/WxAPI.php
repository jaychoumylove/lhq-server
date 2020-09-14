<?php

namespace app\base\service;

use app\base\model\Appinfo;
use CURLFile;
use think\Log;

/**服务端wx接口 */
class WxAPI
{
    private $apiHost;
    public function __construct($w = null)
    {
        $this->apiHost = 'api.weixin.qq.com';
        if (input('platform') == 'MP-QQ') {
            $this->apiHost = 'api.q.qq.com';
            $type = 'qq';
        } else if (input('platform') == 'APP') {
            $type = 'app';
        } else {
            $type = 'miniapp';
        }
        if (!$w) $w = $type;
        $this->appinfo = Common::getAppinfo($w);

//        $currentTime = time();
//        if ($this->appinfo['access_token_expire'] && $this->appinfo['access_token_expire'] <= $currentTime) {
//            // token已过期主动获取token
//            $this->getAccessToken();
//        }
    }

    public function request($url, $data = null)
    {
        $res = Common::request($url, $data);
        if (isset($res['errmsg'])) $errMsg = $res['errmsg'];
        else if (isset($res['errMsg'])) $errMsg = $res['errMsg'];

        if (isset($errMsg) && (strpos($errMsg, 'token') !== false || strpos($errMsg, '错误') !== false)) {
            // 更新access_token
            $oldAccessToken = $this->appinfo['access_token'];
            $this->getAccessToken();
            $url = str_replace($oldAccessToken, $this->appinfo['access_token'], $url);
            $res = Common::request($url, $data);
        }
        return $res;
    }

    /**
     * 检查更新accessToken
     * @return string access_token
     */
    public function getAccessToken()
    {
        $platform = input('platform');
        // 更新accessToken
        if ($platform == 'MP-QQ') {
            $url = 'https://' . $this->apiHost . '/api/getToken?grant_type=client_credential&appid=APPID&secret=APPSECRET';
        } else {
            $url = 'https://' . $this->apiHost . '/cgi-bin/token?grant_type=client_credential&appid=APPID&secret=APPSECRET';
        }
        $url = str_replace('APPID', $this->appinfo['appid'], $url);
        $url = str_replace('APPSECRET', $this->appinfo['appsecret'], $url);

        $res = $this->request($url);
        if (array_key_exists('access_token', $res) == false) {
            Log::error(sprintf('token请求出错,平台%s', $platform));
            Log::error(json_encode($res));
//            Common::res(['code' => 1, 'msg' => '系统忙，请重新进入']);
        }
        $this->appinfo['access_token'] = $res['access_token'];

        $diff = 200; // 200秒差值
        $expires = (int)bcsub($res['expires_in'], $diff);
        // 将新的token保存到数据库
        Appinfo::where(['id' => $this->appinfo['id']])->update([
            'access_token' => $res['access_token'],
            'access_token_expire' => date('Y-m-d H:i:s', time() + $expires),
        ]);
    }

    /**
     * 获得jsapi_ticket
     */
    public function getJsapiTicket()
    {
        if (!$this->appinfo['jsapi_ticket'] || time() > $this->appinfo['jsapi_ticket_expire']) {
            $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=' . $this->appinfo['access_token'] . '&type=jsapi';
            $res = $this->request($url);
            Appinfo::where('id', $this->appinfo['id'])->update([
                'jsapi_ticket' => $res['ticket'],
                'jsapi_ticket_expire' => time() + 7200
            ]);

            return $res['ticket'];
        } else {
            return $this->appinfo['jsapi_ticket'];
        }
    }

    /**
     * 小程序登录
     */
    public function code2session($js_code)
    {
        $url = 'https://' . $this->apiHost . '/sns/jscode2session?appid=APPID&secret=SECRET&js_code=JSCODE&grant_type=authorization_code';
        $url = str_replace('APPID', $this->appinfo['appid'], $url);
        $url = str_replace('SECRET', $this->appinfo['appsecret'], $url);
        $url = str_replace('JSCODE', $js_code, $url);

        return $this->request($url);
    }

    /**
     * 公众号授权
     * 通过code换取网页授权access_token
     * 该接口可能返回unionid
     */
    public function getAuth($code)
    {
        $url = 'https://' . $this->apiHost . '/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code';

        $url = str_replace('APPID', $this->appinfo['appid'], $url);
        $url = str_replace('SECRET', $this->appinfo['appsecret'], $url);
        $url = str_replace('CODE', $code, $url);

        return $this->request($url);
    }

    /**
     * 拉取获取用户信息(不需要用户关注公众号)
     * @param string $openid 用户的唯一标识
     */
    public function getUserInfo($openid, $access_token)
    {
        $url = 'https://' . $this->apiHost . '/sns/userinfo?access_token=' . $access_token . '&openid=' . $openid . '&lang=zh_CN';

        return $this->request($url);
    }

    /**
     * 公众号授权
     * 拉取用户信息
     * @param string $accessToken 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
     * @param string $openid 用户的唯一标识
     */
    public function getUserInfocgi($accessToken, $openid)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/user/info?access_token=' . $this->appinfo['access_token'] . '&openid=OPENID&lang=zh_CN';

        $url = str_replace('OPENID', $openid, $url);

        return $this->request($url);
    }

    /**统一下单API */
    public function unifiedorder($config)
    {
        if (input('platform') == 'MP-QQ') {
            $url = 'https://qpay.qq.com/cgi-bin/pay/qpay_unified_order.cgi';
        } else {
            $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        }
        $params = [
            'appid' => $this->appinfo['appid'],
            'mch_id' => $this->appinfo['paymchid'],
            'nonce_str' => Common::getRandCode(), // 随机字符串
            'body' => $config['body'], // 商品描述
            'out_trade_no' => $config['orderId'], // 商户订单号
            'total_fee' => $config['totalFee'] * 100, // 总金额 单位 分
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],
            'notify_url' => $config['notifyUrl'],
            'trade_type' => $config['tradeType'],
        ];
        if ($config['tradeType'] == 'JSAPI') {
            // trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识
            $params['openid'] = $config['openid'];
        }
        // 签名
        $params['sign'] = (new WxPay($this->appinfo['appid']))->makeSign($params);
        // 发送请求
        $xml = Common::toXml($params);
        $res = $this->request($url, $xml);
        return Common::fromXml($res);
    }

    /**
     * (主动)发送客服消息
     * customerServiceMessage.send
     * @param string $openid 用户openid
     * @param array $msgType
     * @param array $msgBody
     */
    public function sendCustomerMsg($openid, $msgType, $msgBody)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/message/custom/send?access_token=' . $this->appinfo['access_token'];

        $data = [
            'touser' => $openid,
            "msgtype" => $msgType,
            $msgType => $msgBody
        ];

        return $this->request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 新增临时素材
     */
    public function uploadMedia($filePath)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/media/upload?access_token=' . $this->appinfo['access_token'] . '&type=TYPE';
        $url = str_replace('TYPE', 'image', $url);

        $data = ['media' => new CURLFile($filePath, false, false)];

        return $this->request($url, $data);
    }

    /**
     * 获取临时素材
     * @param mixed $mediaId 媒体文件ID，即uploadVoice接口返回的serverID
     */
    public function getMedia($mediaId)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/media/get?access_token=' . $this->appinfo['access_token'] . '&media_id=MEDIA_ID';

        $url = str_replace('MEDIA_ID', $mediaId, $url);

        return $this->request($url);
    }


    /**
     * 新增其他类型永久素材
     */
    public function addMaterial($filePath)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/material/add_material?access_token=' . $this->appinfo['access_token'] . '&type=TYPE';

        $url = str_replace('TYPE', 'image', $url);

        $data = ['media' => new CURLFile($filePath, false, false)];
        return $this->request($url, $data);
    }

    /**使用公众号接口上传图片 */
    public function uploadimg($filePath)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/media/uploadimg?access_token=' . $this->appinfo['access_token'];

        $data = ['media' => new CURLFile($filePath, false, false)];
        return $this->request($url, $data);
    }

    /**
     * 获取小程序码，适用于需要的码数量较少的业务场景。
     * @param string $path 扫码进入的小程序页面路径
     */
    public function getwxacode($path = '/pages/index/index')
    {
        $url = 'https://' . $this->apiHost . '/wxa/getwxacode?access_token=' . $this->appinfo['access_token'];

        $data = ['path' => $path];
        return $this->request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取小程序码，适用于需要的码数量极多的业务场景，数量不限制。
     * @param string $path 扫码进入的小程序页面路径
     */
    public function getUnlimited($path = '/pages/index/index',$scene = '')
    {
        $url = 'https://' . $this->apiHost . '/wxa/getwxacodeunlimit?access_token=' . $this->appinfo['access_token'];

        $data['path'] = $path;
        $data['scene'] = $scene;
        return $this->request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 发送模板消息
     * method post
     */
    public function sendTemplate($datas)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/message/wxopen/template/send?access_token=' . $this->appinfo['access_token'];

        foreach ($datas as $data) {
            Common::requestAsync($url, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        // return $this->request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 发送模板消息
     * method post
     */
    public function sendTemplateSync()
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/message/wxopen/template/send?access_token=' . $this->appinfo['access_token'];

        $data = [
            "touser" => 'oj77y5LIpHuIWUU2kW8BHVP4goPc',
            "template_id" => "T54MtDdRAPe8kNNtt2tQlj7P7ut7yEe-F8-CaMrKcvw",
            "page" => "/pages/index/index",
            "form_id" => "b5664ec137d44c4c8332130d065a4e48",
            "data" => [
                "keyword1" => [
                    "value" => 100 . "元"
                ],
                "keyword2" => [
                    "value" =>  11 . "已成功解锁" . 100 . "元应援金，赶快邀请后援会入驻领取吧，活动进行中，最多可解锁1000元。"
                ]
            ],
            "emphasis_keyword" => "keyword1.DATA"
        ];

        // $this->requestAsync($url, json_encode($data, JSON_UNESCAPED_UNICODE));

        return $this->request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 检查一段文本是否含有违法违规内容。
     * 若接口errcode返回87014(内容含有违法违规内容)
     */
    public function msgCheck($content)
    {
        if (input('platform') == 'MP-QQ') {

            $url = 'https://' . $this->apiHost . '/api/json/security/MsgSecCheck?access_token=' . $this->appinfo['access_token'];
            $data = 'content=' . $content;
        } else {
            $url = 'https://' . $this->apiHost . '/wxa/msg_sec_check?access_token=' . $this->appinfo['access_token'];
            $data = json_encode([
                'content' => $content
            ], JSON_UNESCAPED_UNICODE);
        }
        $res = $this->request($url, $data);
        //微信
        if (isset($res['errcode']) && $res['errcode'] == 87014) Common::res(['code' => 1, 'msg' => '含有违法违规内容被屏蔽']);
        //if (isset($res['errcode']) && $res['errcode']) Common::res(['code' => 1, 'msg' => $res['errmsg']]);
        //QQ
        if (isset($res['errCode']) && $res['errCode'] == 87014) Common::res(['code' => 1, 'msg' => '含有违法违规内容被屏蔽']);
//        只有检测合法值为87014的情况检测出错
//        if (isset($res['errCode']) && $res['errCode']) Common::res(['code' => 1, 'msg' => $res['errMsg']]);
    }

    /**校验一张图片是否含有违法违规内容 */
    public function imgCheck($filePath)
    {
        if (input('platform') == 'MP-QQ') {
            
            $url = 'https://' . $this->apiHost . '/api/json/security/ImgSecCheck?access_token=' . $this->appinfo['access_token'];
        } else {
            
            $url = 'https://' . $this->apiHost . '/wxa/img_sec_check?access_token=' . $this->appinfo['access_token'];
        }
        
        $data['media'] = new CURLFile($filePath, false, false);
        $res = $this->request($url, $data);
        
        //微信
        if (isset($res['errcode']) && $res['errcode'] == 87014) Common::res(['code' => 1, 'msg' => '含有违法违规内容被屏蔽']);
        if (isset($res['errcode']) && $res['errcode']) Common::res(['code' => 1, 'msg' => $res['errmsg']]);
        //QQ
        if (isset($res['errCode']) && $res['errCode'] == 87014) Common::res(['code' => 1, 'msg' => '含有违法违规内容被屏蔽']);
        if (isset($res['errCode']) && $res['errCode']) Common::res(['code' => 1, 'msg' => $res['errMsg']]);
    
    }

    /**
     * 发送模板消息
     */
    public function sendMessageGzh($data)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $this->appinfo['access_token'];

        return $this->request($url, $data);
    }

    /**
     * 创建菜单(认证后的订阅号可用)
     *
     * @param array $data
     *            type可以选择为以下几种，其中5-8除了收到菜单事件以外，还会单独收到对应类型的信息。
     *            1、click：点击推事件
     *            2、view：跳转URL
     *            3、scancode_push：扫码推事件
     *            4、scancode_waitmsg：扫码推事件且弹出“消息接收中”提示框
     *            5、pic_sysphoto：弹出系统拍照发图
     *            6、pic_photo_or_album：弹出拍照或者相册发图
     *            7、pic_weixin：弹出微信相册发图器
     *            8、location_select：弹出地理位置选择器
     */
    public function createMenu($data)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' .  $this->appinfo['access_token'];
        return $this->request($url, $data);
    }
}
