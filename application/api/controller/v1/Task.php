<?php
namespace app\api\controller\v1;

use app\api\model\UserAchievementHeal;
use app\base\controller\Base;
use app\base\service\Common;
use app\api\service\Task as TaskService;
use app\api\model\User;
use app\api\service\Star;
use app\api\model\Cfg;
use app\api\model\CfgBadge;
use app\api\model\CfgTaskgift;
use app\api\model\CfgTaskgiftCategory;
use app\api\model\RecTask;
use app\api\model\UserStar;

class Task extends Base
{

    public function index()
    {
        $type = $this->req('type', 'integer');
        $this->getUser();
        
        // 每日签到
        RecTask::checkIn($this->uid);
        
        if ($type == 2) {
            // 徽章
            $list = CfgBadge::getList($this->uid);
        } else {
            // 任务
            $list = (new TaskService())->checkTask($this->uid, $type);
        }
        
        Common::res([
            'data' => $list
        ]);
    }

    public function settle()
    {
        $task_id = $this->req('task_id', 'integer');
        $this->getUser();
        
        $earn = (new TaskService())->settle($task_id, $this->uid);
        Common::res([
            'data' => $earn
        ]);
    }

    /**
     * 提交微博链接
     */
    public function weibo()
    {
        $this->getUser();
        
        $weiboUrl = $this->req('weiboUrl', 'require');
        $type = $this->req('type', 'require');
        
        $taskService = new TaskService();
        $weiboUrl = $taskService->getWeiboUrl($weiboUrl);
        $taskService->saveWeibo($weiboUrl, $this->uid, $type);
        Common::res([]);
    }

    public function sharetext()
    {
        $this->getUser();
        $user = User::with([
            'UserStar' => [
                'Star' => [
                    'StarRank'
                ]
            ]
        ])->where([
            'id' => $this->uid
        ])->find();
        $rank = (new Star())->getRank($user['user_star']['star']['star_rank']['week_hot'], 'week_hot');
        $type = input('type', 0);
        // $text = "我正在为#APPNAME#STARNAME打榜，STARNAME已经获得了STARSCORE票，实时排名第STARRANK，wx搜索小程序“APPNAME”，加入STARNAME的圈子，一起用爱解锁最强福利！";
        if ($type == 0) {
            $text = Cfg::getCfg('weibo_share_text');
        } else 
            if ($type == 1) {
                $text = Cfg::getCfg('pyq_share_text');
            } else 
                if ($type == 2) {
                    $text = Cfg::getCfg('weibo_share_text');
                } else 
                    if ($type == 3) {
                        $text = Cfg::getCfg('pyq_share_text');
                    }
        // $text = "#STARNAME[超话]#今天我已为爱豆打榜，STARNAME加油，我爱你，我会每天支持你，
        // 不离不弃。爱STARNAME的伙伴们，一起来支持STARNAME吧？微信小程序搜索：APPNAME，夺取冠军福利，就等
        // 你了。现在STARNAME排名第STARRANK，获得了STARSCORE票。@APPNAME";
        
        $text = str_replace('STARNAME[超话]', $user['user_star']['star']['chaohua'] . '[超话]', $text);
        $text = str_replace('STARNAME', $user['user_star']['star']['name'], $text);
        
        $text = str_replace('DAYHOT', $user['user_star']['thisday_count'], $text);
        
        $text = str_replace('STARSCORE', $user['user_star']['star']['star_rank']['week_hot'], $text);
        $text = str_replace('STARRANK', $rank, $text);
        $text = str_replace('APPNAME', Cfg::getCfg('appname'), $text);
        
        // 活动
        $star_id = UserStar::where('user_id', $this->uid)->order('id desc')->value('star_id');
        // $activeInfo = UserStar::getActiveInfo($this->uid, $star_id);
        
        // if (isset($activeInfo['finishedFee'])) {
        // $text = str_replace('ACTIVE', '目前已经解锁' . $activeInfo['finishedFee'] . '元应援金', $text);
        // } else {
        // $text = str_replace('ACTIVE', '目前累计解锁' . $activeInfo['complete_people'] . '次', $text);
        // }
        
        // $text = str_replace('ACTIVE', '目前已经有' . $activeInfo['join_people'] . '人参与', $text);
        Common::res([
            'data' => [
                'share_text' => $text,
                'weibo_zhuanfa' => Cfg::getCfg('weibo_zhuanfa')
            ]
        ]);
    }

    public function badgeUse()
    {
        $badgeId = $this->req('badge_id', 'integer');
        
        $this->getUser();
        CfgBadge::badgeUse($badgeId, $this->uid);
        Common::res();
    }

    public function taskgiftCategory()
    {
        $this->getUser();
        $res = CfgTaskgiftCategory::getCategoryMore($this->uid);
        Common::res([
            'data' => $res
        ]);
    }

    public function taskGift()
    {
        $cid = $this->req('cid', 'integer');
        $this->getUser();

        $list = CfgTaskgift::where('category_id', $cid)->select();

        $res['list'] = CfgTaskgift::listHandle($cid, $list, $this->uid);
        $res['category'] = CfgTaskgiftCategory::getCategoryMore($this->uid);
        
        Common::res([
            'data' => $res
        ]);
    }

    public function taskGiftSettle()
    {
        $cid = $this->req('cid', 'integer');
        $task_id = $this->req('task_id', 'integer');
        $this->getUser();

        if ((int)$cid == CfgTaskgiftCategory::ACHIEVEMENT_ID) {
            $res = UserAchievementHeal::getAchievementReward($this->uid, $task_id);
            if (empty($res)) {
                Common::res (['code' => 1, 'msg' => '您还未达成领取条件哦']);
            }

            Common::res(['data' => $res]);
        } else {
            $awardsList = CfgTaskgift::where('category_id', $cid)->column('id,title,awards,count','id');
            (new TaskService())->taskGiftSettle($cid, $task_id, $awardsList, $this->uid);

            Common::res();
        }
    }
}
