<?php

namespace app\api\controller\v1;

use app\base\service\WxMsg;
use app\base\controller\Base;
use app\base\service\WxAPI;

class Notify extends Base
{

    private $wxMsg;
    public function receive()
    {
        $this->wxMsg = new WxMsg(input('appid'));

        $this->wxMsg->checkSignature();
        $msgFrom = $this->wxMsg->getMsg();
        $this->msgHandler($msgFrom);

        die('success');
    }

    private function msgHandler($msgFrom)
    {
        if($this->wxMsg->appinfo['type']=='gzh') $this->msgGzh($msgFrom);
        elseif($this->wxMsg->appinfo['type']=='miniapp') $this->msgMiniapp($msgFrom);
    }

    /**
     * 处理小程序到的消息
     * 并获取需要回复的消息
     */
    private function msgMiniapp($msg)
    {
        $Content = "您好：\n";

        //发送文本消息
        $ret = (new WxAPI(input('appid')))->sendCustomerMsg(
            $msg['FromUserName'],
            'text',
            [
                'content' => $Content
            ]
        );

        //发送公众号二维码
        $media_id = $this->wxMsg->getMediaId(ROOT_PATH . 'public/uploads/cust.jpg');
        $ret = (new WxAPI(input('appid')))->sendCustomerMsg(
            $msg['FromUserName'],
            'image',
            [
                'media_id' => $media_id
            ]
        );

        //发送活动图片
        $media_id = $this->wxMsg->getMediaId(ROOT_PATH . 'public/uploads/active.jpg');
        $ret = (new WxAPI(input('appid')))->sendCustomerMsg(
            $msg['FromUserName'],
            'image',
            [
                'media_id' => $media_id
            ]
        );
    }

    //公众号处理
    private function msgGzh($msg){
        $Content = "您好：\n";

        $this->wxMsg->autoSend($msg, 'text', [
            'Content' => $Content
        ]);
    }

}
