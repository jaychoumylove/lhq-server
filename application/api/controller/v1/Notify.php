<?php

namespace app\api\controller\v1;

use app\api\model\Cfg;
use app\api\model\GzhUser;
use app\api\model\Rec;
use app\api\model\StarRankPkactive;
use app\api\model\User as UserModel;
use app\api\model\UserExt;
use app\api\model\UserSprite;
use app\api\model\UserStar;
use app\api\service\User as UserService;
use app\base\service\WxMsg;
use app\base\controller\Base;
use app\base\service\WxAPI;
use app\api\model\StarRank;
use think\Db;
use think\Log;

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
        $Content = "你可能对以下内容感兴趣：\n";
        $Content .= "回复“签到”领取每日签到奖励\n";
        $Content .= "<a href='https://idolzone.cyoor.com/#/pages/charge/charge'>鲜花充值</a>\n";
        $Content .= "<a href='https://mp.weixin.qq.com/s/TJ9ARt8b-E12Rsh7BeanyA'>榜单福利</a>\n";
        $Content .= "<a href='https://mp.weixin.qq.com/s/9mK0ug4hXnSvQohI0AXRyg'>打榜攻略</a>\n\n";
        $Content .= "<a data-miniprogram-appid=\"wx3a69eb5e1b2a7fa9\" data-miniprogram-path=\"/pages/index/index\">点击此链接去打榜吧~</a>\n\n";

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

        $Content = '';

        if ($msg['MsgType'] == 'text' && isset($msg['Content']) && ($msg['Content'] == '1' || $msg['Content'] == '签到')) {
            $Content .= $this->signDay($msg);

//        } elseif ($msg['MsgType'] == 'text' && isset($msg['Content']) && ($msg['Content'] == '618' || $msg['Content'] == '618活动')) {
//            $Content .= $this->getGift618($msg);

//        } elseif ($msg['MsgType'] == 'text' && isset($msg['Content']) && ($msg['Content'] == '夏日福利')) {
//            $Content .= $this->getWealGift($msg);

        } elseif ($msg['MsgType'] == 'text' && isset($msg['Content']) && $msg['Content'] == '兑换粽子') {
            $Content .= $this->settlePkactive($msg);

        } elseif (isset($msg['Event']) && $msg['Event'] == 'CLICK' && $msg['EventKey'] == 'CLICK_kefu') { //按钮操作
            $Content .= " 【联系客服】\n请加客服（大白）微信：vpanfxcom\n请一定注明反馈的问题或者建议，否则可能会被忽略哦！";

        } elseif (isset($msg['Event']) && $msg['Event'] == 'CLICK' && $msg['EventKey'] == 'CLICK_lianxi') { //按钮操作
            $Content .= " 【商务合作】\n寻求合作及赞助可发送邮件：alben.liu@qq.com\n请一定注明公司、姓名、以及合作内容、品牌，否则可能会被忽略哦！";

        } elseif (isset($msg['Event']) && ($msg['Event'] == 'subscribe' || $msg['Event'] == 'unsubscribe')) { //关注取关
            $this->getUserId($msg);
        }

        $Content .= "你可能对以下内容感兴趣：\n";
        $Content .= "回复“签到”领取每日签到奖励\n";
        $Content .= "<a href='https://idolzone.cyoor.com/#/pages/charge/charge'>鲜花充值</a>\n";
        $Content .= "<a href='https://mp.weixin.qq.com/s/TJ9ARt8b-E12Rsh7BeanyA'>榜单福利</a>\n";
        $Content .= "<a href='https://mp.weixin.qq.com/s/9mK0ug4hXnSvQohI0AXRyg'>打榜攻略</a>\n\n";
        $Content .= "<a data-miniprogram-appid=\"wx3a69eb5e1b2a7fa9\" data-miniprogram-path=\"/pages/index/index\">点击此链接去打榜吧~</a>\n\n";

        $this->wxMsg->autoSend($msg, 'text', [
            'Content' => $Content
        ]);
    }

    /**
     * 公众号积分告白兑换
     * 只有领袖粉才有权限
     */
    private function settlePkactive($msg)
    {
        if(!Cfg::getStatus('redress_date')) return "活动未开启\n----------------------------\n\n";

        $user_id = $this->getUserId($msg);
        if(!$user_id) return "没有关联到用户，请先到小程序打榜！\n<a data-miniprogram-appid=\"wx3a69eb5e1b2a7fa9\" data-miniprogram-path=\"/pages/index/index\">点击此链接去打榜吧~</a>\n----------------------------\n\n";

        $star_id = UserStar::where('user_id', $user_id)->where('captain', 1)->value('star_id');
        if (!$star_id)  return "只有领袖粉才可以兑换！\n----------------------------\n\n";

        Db::startTrans();
        try {

            //更新pkactive表的结算时间
            $isDone = StarRankPkactive::where('star_id',$star_id)->where('settle_time',0)->update(['settle_time'=>time(),'settle_uid'=>$user_id]);
            if(!$isDone) return "粽子已经兑换过了！\n----------------------------\n\n";

            //查出粽子
            $score = StarRankPkactive::where('star_id',$star_id)->value('score');
            $update['week_hot'] = $score*100*10000;

            //增加爱豆周榜人气，并记录到日志
            StarRank::where('star_id',$star_id)->update(['week_hot'=>Db::raw('week_hot+' . $update['week_hot'])]);
            Rec::addRec([
                'user_id' => $user_id,
                'content' => "兑换粽子，爱豆周榜人气+{$update['week_hot']}"
            ]);

            Db::commit();
            return "兑换粽子成功，爱豆周榜人气+{$update['week_hot']}\n----------------------------\n\n";

        } catch (\Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }
    }

    /**
     * 公众号补偿
     */
    private function redress($msg)
    {
        if(!Cfg::getStatus('redress_date')) return "活动未开启\n----------------------------\n\n";

        $user_id = $this->getUserId($msg);
        if(!$user_id) return "没有关联到用户，请先到小程序打榜！\n<a data-miniprogram-appid=\"wx3a69eb5e1b2a7fa9\" data-miniprogram-path=\"/pages/index/index\">点击此链接去打榜吧~</a>\n----------------------------\n\n";

        $isSigned = UserExt::where('user_id', $user_id)->value('redress_time');
        if ($isSigned)  return "你已领取补偿！\n----------------------------\n\n";

        // 增加货币
        $sprite = UserSprite::getInfo($user_id);
        $update['coin'] = $sprite['total_speed_coin'] * 0 / 100;
        $update['stone'] = 0;

        Db::startTrans();
        try {
            (new UserService)->change($user_id, $update,'农场补偿');
            UserExt::where('user_id', $user_id)->update(['redress_time' => time()]);
            Db::commit();
            return "补偿成功，金豆+{$update['coin']}，钻石+{$update['stone']}\n----------------------------\n\n";

        } catch (\Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }
    }

    /**
     * 获取618福利
     */
    private function getGift618($msg)
    {
        $user_id = $this->getUserId($msg);
        if(!$user_id) return "没有关联到用户，请先到小程序打榜！\n<a data-miniprogram-appid=\"wx3a69eb5e1b2a7fa9\" data-miniprogram-path=\"/pages/index/index\">点击此链接去打榜吧~</a>\n----------------------------\n\n";

        $isGetGift = UserExt::where('user_id', $user_id)->value('is_blessing_gifts');
        if ($isGetGift>0) return "您已经领取过了！\n----------------------------\n\n";
        // 增加货币
        $update = ['coin'=>10000,'stone'=>2,'trumpet'=>3];
        Db::startTrans();
        try {
            $isDone = UserExt::where('user_id', $user_id)->where('is_blessing_gifts', 0)->update(['blessing_num' => Db::raw('blessing_num+1'),'is_blessing_gifts' =>1]);
            if(!$isDone) return "你已经领取过618礼包了\n----------------------------\n\n";
            (new UserService)->change($user_id, $update,'618福利领取');
            Db::commit();
            return "领取成功，金豆+{$update['coin']}，钻石+{$update['stone']}，喇叭+{$update['trumpet']},福袋+1\n----------------------------\n\n";

        } catch (\Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }
    }
    /**
     * 获取夏日福利
     */
    private function getWealGift($msg)
    {
        $user_id = $this->getUserId($msg);
        if(!$user_id) return "没有关联到用户，请先到小程序打榜！\n<a data-miniprogram-appid=\"wx3a69eb5e1b2a7fa9\" data-miniprogram-path=\"/pages/index/index\">点击此链接去打榜吧~</a>\n----------------------------\n\n";

        $isGetGift = UserExt::where('user_id', $user_id)->value('weal_receive');
        if ($isGetGift>0) return "您已经领取过了！\n----------------------------\n\n";
        // 增加货币
        $update = ['coin'=>10000,'stone'=>2,'trumpet'=>3];
        Db::startTrans();
        try {
            $isDone = UserExt::where('user_id', $user_id)->where('is_blessing_gifts', 0)->update(['bag_num' => Db::raw('bag_num+1'),'weal_receive' =>1]);
            if(!$isDone) {
                return "你已经领取过618礼包了\n----------------------------\n\n";
            }
            (new UserService)->change($user_id, $update,'618福利领取');
            Db::commit();
            return "领取成功，金豆+{$update['coin']}，钻石+{$update['stone']}，喇叭+{$update['trumpet']},福袋+1\n----------------------------\n\n";
        } catch (\Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }
    }

    /**
     * 公众号每日签到
     */
    private function signDay($msg)
    {
        $user_id = $this->getUserId($msg);
        if(!$user_id) return "没有关联到用户，请先到小程序打榜！\n<a data-miniprogram-appid=\"wx3a69eb5e1b2a7fa9\" data-miniprogram-path=\"/pages/index/index\">点击此链接去打榜吧~</a>\n----------------------------\n\n";

        $isSigned = UserExt::where('user_id', $user_id)->whereTime('gzh_signin_time', 'd')->value('id');
        if ($isSigned) return "你今天已经签到，明日再来！\n----------------------------\n\n";

        // 增加货币
        $update = ['coin'=>3000,'stone'=>3];
        Db::startTrans();
        try {
            (new UserService)->change($user_id, $update,'公众号签到');
            UserExt::where('user_id', $user_id)->update(['gzh_signin_time' => time()]);
            Db::commit();
            return "签到成功，金豆+{$update['coin']}，钻石+{$update['stone']}\n----------------------------\n\n";

        } catch (\Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }
    }

    // 获取用户id
    private function getUserId($msg)
    {
        $wxApi = new WxAPI(input('appid'));
        $res = $wxApi->getUserInfocgi($wxApi->appinfo['access_token'],$msg['FromUserName']);
        $user_id = isset($res['unionid']) ? UserModel::where(['unionid' => $res['unionid']])->value('id') : NULL;
        $subscribe = (int) !($msg['MsgType'] == 'event' && $msg['Event'] == 'unsubscribe');//关注还是取关
        GzhUser::gzhSubscribe(input('appid'), $user_id, $msg['FromUserName'], $subscribe);

        return $user_id;
    }

    public function createMenu()
    {
        $data = '{
            "button": [
                {
                    "name": "打榜应援",
                    "sub_button": [
                        {
                            "type": "miniprogram",
                            "name": "小程序打榜",
                            "url": "https://mp.weixin.qq.com/s/V-Zw-FDPKLKY4GJfBdZS7w",
                            "appid": "wx3a69eb5e1b2a7fa9",
                            "pagepath":"pages/open/open"
                        },
                        {
                            "type": "view",
                            "name": "网页打榜",
                            "url": "https://idolzone.cyoor.com"
                        },
                        {
                            "type": "view",
                            "name": "APP打榜",
                            "url": "https://m3w.cn/__uni__9bbb723"
                        }
                    ]
                },
                {
                    "type": "view",
                    "name": "鲜花充值",
                    "url": "https://idolzone.cyoor.com/#/pages/charge/charge"
                },
                {
                    "name": "联系我们",
                    "sub_button": [
                        {
                            "type": "click",
                            "name": "在线客服",
                            "key": "CLICK_kefu"
                        },
                        {
                            "type": "click",
                            "name": "联系我们",
                            "key": "CLICK_lianxi"
                        }
                    ]
                }
            ]
        }';

        dump((new WxAPI('wxc06a3e2ee711cddc'))->createMenu($data));//爱豆圈子公众号
//        dump((new WxAPI('wx3507654fa8d00974'))->createMenu($data));//爱豆数据助手
    }
}
