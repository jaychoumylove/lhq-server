<?php

namespace app\api\service;

use app\api\model\CfgPanaceaTask;
use app\api\model\CfgWealActivityTask;
use app\api\model\FanclubUser;
use app\api\model\Rec;
use app\api\model\RecPanaceaTask;
use app\api\model\RecTaskactivity618;
use app\api\model\RecUserBackgroundTask;
use app\api\model\RecUserInvite;
use app\api\model\RecWealActivityTask;
use app\api\model\StarRank as StarRankModel;
use app\api\model\UserManor;
use think\Db;
use app\api\model\UserStar;
use app\api\service\User as UserService;
use app\base\service\Common;
use app\api\model\Cfg;
use app\api\model\UserExt;
use app\api\model\CfgUserLevel;
use app\api\model\User as UserModel;
use GatewayWorker\Lib\Gateway;
use app\api\model\Fanclub;
use app\api\model\Lock;
use app\api\model\PkUser;
use app\api\model\RecHour;
use app\api\model\RecTask;
use app\api\model\UserProp;
use app\api\model\Star as StarModel;
use app\api\model\StarBirthRank;
use app\api\model\Family;
use think\Exception;

class Star
{

    public function getRank($score, $field)
    {
        return StarRankModel::where($field, 'GT', $score)->count() + 1;
    }

    /**今天是否是该明星生日 */
    public static function isTodayBrith($starid)
    {
        $birthday = StarModel::where('id', $starid)->value('birthday');
        return $birthday == date('md');
    }

    /**
     * 打榜
     * @param integer $starid 
     * @param integer $hot 人气 
     * @param integer $uid 
     * @param integer $type 打榜类型：1送金豆 2送鲜花 3送旧豆
     * @param boolean $danmaku 是否推送打榜弹幕
     */
    public function sendHot($starid, $hot, $uid, $type, $danmaku = true, $is_blessing_bag = false)
    {
        if (date('H') == 0 && date('i') == 0 && date('s') < 5) {
            Common::res(['code' => 1, 'msg' => '打榜请稍后再试']);
        }

        if (Lock::getVal('week_end')['value'] == 1) {
            Common::res(['code' => 1, 'msg' => '榜单结算中，请稍后再试！']);
        }

        if (!$starid) Common::res(['code' => 32, 'msg' => '请先加入一个圈子']);
        if ($hot <= 0) Common::res(['code' => 36, 'msg' => '打榜的数值不正确']);

        // 当前粉丝等级
        $beforeLevel = CfgUserLevel::getLevel($uid);

        Db::startTrans();
        try {
            // 基础hot即用户送出的hot
            $basicHot = $hot;
            // 用户货币减少
            if ($type == 1) $update = ['coin' => -$basicHot, 'point' => $basicHot];
            else if ($type == 2) $update = ['flower' => -$basicHot, 'point' => $basicHot];
            else if ($type == 3) $update = ['old_coin' => -$basicHot, 'point' => $basicHot];

            if($is_blessing_bag==false){
                if ($type == 3) {
                    (new UserService)->change($uid, $update, '为爱豆打榜，旧豆-' . $basicHot);
                } else {
                    (new UserService)->change($uid, $update, '为爱豆打榜');
                }
            }else{
                Rec::addRec([
                    'user_id' => $uid,
                    'content' => '使用福袋爱豆人气+'.$basicHot
                ]);
            }

            // 计算所有额外的hot
            $extra = self::extraSendHot ($uid);

            $hotArray = [$basicHot];
            // 存储所有hot以便计算
            if ($extra['percent']) {
                $extraHot = bcmul ($basicHot, $extra['percent']);
                array_push ($hotArray, $extraHot);
            }
            if ((int)$extra['number']) {
                array_push ($hotArray, $extra['number']);
            }

            $hot = (int)array_sum ($hotArray);

            $myStarId = UserStar::getStarId($uid);
            if ($starid != $myStarId) {
                // 为其他爱豆打榜
                if ($type != 2) Common::res(['code' => 231, 'msg' => '请赠送鲜花']);
                StarBirthRank::change($uid, $starid, $hot);
            } else {
                // 用户贡献度增加
                UserStar::changeHandle($uid, 'pick', $starid, $hot,  $type);
                // 赠送鲜花时：占领封面小时榜贡献增加
                if ($type == 2) RecHour::change($uid, $hot, $starid);
                // 团战贡献增加
                PkUser::addHot($uid, $starid, $hot);
                // 粉丝团
                Fanclub::change($uid, $hot);
                
                // 家族贡献
                Family::change($uid, $hot);

                // 宝箱
                if ($type == 2) {
                    RecUserBackgroundTask::record($uid, $hot, RecUserBackgroundTask::FLOWER_SUM);
                    if (UserManor::checkBackgroundActive()) {
                        // 活动背景限定
                        RecUserBackgroundTask::record($uid, $hot, RecUserBackgroundTask::ACTIVE);
                    }
                }
            }

            FanclubUser::addActiveDragonBoatFestivalHot($uid,$hot);

            RecTaskactivity618::addOrEdit($uid, 7, $hot);

            RecTask::addRec($uid, [14, 15, 16, 17, 18], $hot);
            // 明星增加人气
            StarRankModel::change($starid, $hot, $type);

            RecWealActivityTask::setTask ($uid, $hot, CfgWealActivityTask::SUM_COUNT);
            RecPanaceaTask::setTask ($uid, $hot, CfgPanaceaTask::SUM_COUNT);

            if (isset($extraHot) && !!$extraHot) {
                $res = UserExt::extraHot ($uid, $extraHot, $starid);
                if (empty($res)) {
                    throw new Exception('更新失败，请稍后再试');
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            Common::res(['code' => 400, 'msg' => $e->getMessage()]);
        }

        $user = UserModel::where('id', $uid)->field('nickname,avatarurl')->find();
        // 打榜弹幕
        if ($danmaku) {
            try {
                Gateway::sendToGroup('star_' . $starid, json_encode([
                    'type' => 'sendHot',
                    'data' => [
                        'user' => $user,
                        'type' => $type,
                        'hot' => $basicHot
                    ]
                ], JSON_UNESCAPED_UNICODE));
            } catch (\Exception $e) {
            }

            if (isset($extraHot) && !!$extraHot) {
                try {
                    Gateway::sendToGroup ('star_' . $starid, json_encode ([
                        'type' => 'sendHot',
                        'data' => [
                            'user' => $user,
                            'type' => $type,
                            'hot'  => $extraHot
                        ]
                    ], JSON_UNESCAPED_UNICODE));
                } catch (\Exception $e) {
                }
            }

            if ((int)$extra['number']) {
                try {
                    Gateway::sendToGroup ('star_' . $starid, json_encode ([
                        'type' => 'sendHot',
                        'data' => [
                            'user' => $user,
                            'type' => $type,
                            'hot'  => (int)$extra['number']
                        ]
                    ], JSON_UNESCAPED_UNICODE));
                } catch (\Exception $e) {
                }
            }
        }

        // 打榜后等级
        $afterLevel = CfgUserLevel::getLevel($uid);
        if ($afterLevel >= 9 && $afterLevel != $beforeLevel) {
            try {
                // 推送socket消息
                // 恭喜【罗云熙】家【头像】【名字】升至【12核心粉】
                Gateway::sendToAll(json_encode([
                    'type' => 'sayworld',
                    'data' => [
                        'type' => 1,
                        'starname' => StarModel::where('id', $myStarId)->value('name'),
                        'avatarurl' => $user['avatarurl'],
                        'nickname' => $user['nickname'],
                        'level' => $afterLevel
                    ],
                ], JSON_UNESCAPED_UNICODE));
            } catch (\Exception $e) {
            }
        }
    }

    /**今日偷取数额上限 */
    public static function stealCountLimit($uid)
    {
        $cfg = Cfg::getCfg('steal_count_limit');

        // 加上可偷金豆增加卡的上限
        $prop_id = 1;
        $count = 1 + UserProp::where([
            'user_id' => $uid,
            'prop_id' => $prop_id,
            'use_time' => ['>=', strtotime(date("Y-m-d"), time())] // 今日使用的
        ])->count('id');
        return $cfg * $count;
    }

    /**偷金豆 */
    public function steal($starid, $uid, $hot)
    {
        UserExt::checkSteal($uid);
        $userExt = UserExt::where(['user_id' => $uid])->field('steal_times,steal_count')->find();
        if ($userExt['steal_times'] >= Cfg::getCfg('steal_limit')) {
            Common::res(['code' => 1, 'msg' => '今日偷取次数已达上限']);
        }

        if ($userExt['steal_count'] >= self::stealCountLimit($uid)) {
            Common::res(['code' => 1, 'msg' => '今日偷取数额已达上限']);
        }

        Db::startTrans();
        try {
            StarRankModel::where(['star_id' => $starid])->update([
                'week_hot' => Db::raw('week_hot-' . $hot),
                'month_hot' => Db::raw('month_hot-' . $hot),
            ]);

            (new UserService())->change($uid, [
                'coin' => $hot,
            ]);

            UserExt::where(['user_id' => $uid])->update([
                'steal_times' => Db::raw('steal_times+1'),
                'steal_count' => Db::raw('steal_count+' . $hot),
                'steal_time' => time(),
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            Common::res(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    public static function extraSendHot($user_id)
    {
        $percentArray = [];
        $numberArray = [];

        $status = Cfg::checkActiveByPathInBtnGroup (Cfg::WEAL_ACTIVE_PATH);
        if (true == $status) {
            $extra = UserExt::get (['user_id' => $user_id]);
            if ((float)$extra['lucky']) {
                $max = RecWealActivityTask::WEAL_ACTIVE_EXTRA_PERCENT;
                $lucky = (float)$extra['lucky'] > $max ? $max: (float)$extra['lucky'];
                $lucky = bcdiv ($lucky, 100, 4);
                array_push ($percentArray, $lucky);
            }
        }

        $percent = $percentArray ? array_sum ($percentArray): 0;
        $number = $numberArray ? (int)array_sum ($numberArray): 0;

        $percent = number_format ($percent, 4);

        $maxPercent = 100;
        if ($percent > $maxPercent) {
            // 百分比上限100%
            $percent = $maxPercent;
        }

        return compact ('percent', 'percentArray', 'number', 'numberArray');
    }

    /**
     * 获取计算热度总额
     * @param $user_id
     * @param $basicHot
     * @return float|int
     */
    public static function getExtraSendHotSum($user_id, $basicHot)
    {
        $data = self::extraSendHot ($user_id);

        /** @var float $percent */
        /** @var int $number */
        extract ($data);

        $hotArray = [$basicHot];
        // 存储所有hot以便计算
        if ($percent) {
            $extraHot = bcmul ($basicHot, $percent);
            array_push ($hotArray, $extraHot);
        }
        if ($number) {
            array_push ($hotArray, $number);
        }

        return array_sum ($hotArray);
    }

    public static function addInvite($star_id)
    {
        $star = (new StarModel())->where('id', $star_id)->find ();
        if (empty($star)) return false;

        $data = [
            'invite_sum' => bcadd ($star['invite_sum'], 1),
            'invite_count' => bcadd ($star['invite_count'], 1),
        ];

        $config = Cfg::getCfg (Cfg::INVITE_ASSIST);
        $inviteReward = false;
        if ($data['invite_count'] >= $config['idol_reward']['state']) {
            $data['invite_count'] = bcsub ($data['invite_count'], $config['idol_reward']['state']);
            $inviteReward = true;
        }

        $updated = StarModel::where('id', $star_id)->update($data);
        if (empty($updated)) return false;

        if ($inviteReward) {
            $hot = $config['idol_reward']['reward']['week_hot'];
            $rankData = [
                'week_hot' => Db::raw('week_hot+' . $hot),
//                'month_hot' => Db::raw('month_hot+' . $hot),
            ];
            StarRankModel::where('star_id', $star_id)->update($rankData);
            $res = RecUserInvite::add ($star_id, $config['idol_reward']['reward'], 'star');
            if (empty($res)) return false;
        }

        return true;
    }
}
