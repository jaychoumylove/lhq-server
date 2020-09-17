<?php

namespace app\api\model;

use app\api\service\User as UserService;
use app\base\model\Base;
use app\base\service\Common;
use think\Db;
use think\Exception;

class Task extends Base
{
    const SIGN      = 'SIGN';
    const INVITE    = 'INVITE';
    const VIDEO_KEY = 'VIDEO_KEY';
    const DAY_KEY   = 'DAY_KEY';

    public function getRewardAttr($value)
    {
        return json_decode($value, true);
    }

    public function getExtraAttr($value)
    {
        return json_decode($value, true);
    }

    public static function settle($user_id, $type)
    {
        $typeList = Task::column('type');
        if (in_array($type, $typeList) == false) {
            Common::res(['code' => 1, 'msg' => '请选择任务']);
        }

        $avatarurl = User::where('id',$user_id)->value('avatarurl');
        if(!$avatarurl) Common::res(['code' => 1, 'msg' => '请点击个人中心-头像完善个人信息']);

        switch ($type) {
            case Task::SIGN:
                $res = self::settleSign($user_id);
                break;
            case Task::INVITE:
                $res = self::settleInvite($user_id);
                break;
            case Task::DAY_KEY:
                $res = self::settleDayKey($user_id);
                break;
            case Task::VIDEO_KEY:
                $res = self::settleVideoKey($user_id);
                break;
            default:
                $res = null;
                break;
        }

        return $res;
    }

    public static function settleSign($user_id)
    {
        $userTask = UserTask::where('user_id', $user_id)
            ->where('task_type', Task::SIGN)
            ->find();

        $number      = 0;
        $currentTime = time();
        $currentDate = date('Y-m-d H:i:s', $currentTime);
        if ($userTask) {
            $number = $userTask['number'];
            if (date('Ymd', strtotime($userTask['last_settle_time'])) == date('Ymd', $currentTime)) {
                Common::res(['code' => 1, 'msg' => '你今天已经签到了']);
            }
        }

        $signNumber = bcadd($number, 1);
        $task       = Task::where('type', Task::SIGN)
            ->where('number', $signNumber)
            ->find();
        if (empty($task)) {
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        Db::startTrans();
        try {
            // 吸入任务记录
            // 更新用户钥匙、积分
            if (empty($userTask)) {
                UserTask::create([
                    'user_id'          => $user_id,
                    'task_type'        => Task::SIGN,
                    'number'           => 1,
                    'settle_num'       => 1,
                    'last_settle_time' => $currentDate
                ]);
            } else {
                $updated = UserTask::where('id', $userTask['id'])
                    ->where('task_type', Task::SIGN)
                    ->where('number', $userTask['number'])
                    ->update([
                        'number'           => $signNumber,
                        'settle_num'       => bcadd($userTask['settle_num'], 1),
                        'last_settle_time' => $currentDate
                    ]);
                if (empty($updated)) {
                    throw new Exception('更新任务出错');
                }
            }

            $point = $task['reward']['point'];

            (new UserService())->change($user_id,[
                'point'      => $point,
                'pure_point' => $point
            ],['type' => 1, 'content' => '每日签到贝壳']);


            Db::commit();
        } catch (\Throwable $throwable) {
            Db::rollback();
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        return $task['reward'];
    }

    public static function settleInvite($user_id)
    {
        $userTask = UserTask::where('user_id', $user_id)
            ->where('task_type', Task::INVITE)
            ->find();

        if (empty($userTask['number'])) {
            Common::res(['code' => 1, 'msg' => '还没有邀请好友哦']);
        }
        $task = Task::where('type', Task::INVITE)->find();
        if (empty($task)) {
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        $currentTime = time();
        $currentDate = date('Y-m-d H:i:s', $currentTime);

        Db::startTrans();
        try {
            $updated = UserTask::where('id', $userTask['id'])
                ->where('task_type', Task::INVITE)
                ->where('number', $userTask['number'])
                ->update([
                    'number'           => 0,
                    'settle_num'       => bcadd($userTask['settle_num'], $userTask['number']),
                    'last_settle_time' => $currentDate
                ]);
            if (empty($updated)) {
                throw new Exception('更新任务出错');
            }

            $keyNum       = $task['reward']['key_num'];
            $rewardKeyNum = (int)bcmul($keyNum, $userTask['number']);

            (new UserService())->change($user_id,[
                'key_num' => $rewardKeyNum,
            ],['type' => 2, 'content' => '邀请好友奖励']);

            Db::commit();
        } catch (\Throwable $throwable) {
            Db::rollback();
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        return ['key_num' => $rewardKeyNum];
    }

    public static function settleDayKey($user_id)
    {
        $userTask = UserTask::where('user_id', $user_id)
            ->where('task_type', Task::DAY_KEY)
            ->find();

        $number      = 0;
        $currentTime = time();
        $currentDate = date('Y-m-d H:i:s', $currentTime);
        if ($userTask) {
            $number = $userTask['number'];
        }

        $task = Task::where('type', Task::DAY_KEY)->find();
        if (empty($task)) {
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        if ($number >= $task['limit']) {
            Common::res(['code' => 1, 'msg' => '今日领取次数已用完']);
        }

        if ($userTask) {
            $lastSettleTime = strtotime($userTask['last_settle_time']);
            $diff           = bcsub($currentTime, $lastSettleTime);
            if ($diff < $task['reward']['second']) {
                Common::res(['code' => 1, 'msg' => '点击太快了']);
            }
        }

        $dayNumber = bcadd($number, 1);

        Db::startTrans();
        try {
            if (empty($userTask)) {
                UserTask::create([
                    'user_id'          => $user_id,
                    'task_type'        => Task::DAY_KEY,
                    'number'           => $dayNumber,
                    'settle_num'       => 1,
                    'last_settle_time' => $currentDate
                ]);
            } else {
                $updated = UserTask::where('id', $userTask['id'])
                    ->where('task_type', Task::DAY_KEY)
                    ->where('number', $number)
                    ->update([
                        'number'           => $dayNumber,
                        'settle_num'       => bcadd($userTask['settle_num'], 1),
                        'last_settle_time' => $currentDate
                    ]);
                if (empty($updated)) {
                    throw new Exception('更新任务出错');
                }
            }

            $rewardKeyNum = (int)$task['reward']['key_num'];

            (new UserService())->change($user_id,[
                'key_num' => $rewardKeyNum,
            ],['type' => 2, 'content' => '每日免费领取钥匙']);

            Db::commit();
        } catch (\Throwable $throwable) {
            Db::rollback();
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        return [
            'key_num' => $rewardKeyNum,
            'second'  => $task['reward']['second']
        ];
    }

    public static function settleVideoKey($user_id)
    {
        $userTask = UserTask::where('user_id', $user_id)
            ->where('task_type', Task::VIDEO_KEY)
            ->find();

        $number      = 0;
        $currentTime = time();
        $currentDate = date('Y-m-d H:i:s', $currentTime);
        if ($userTask) {
            $number = $userTask['number'];
        }

        $task = Task::where('type', Task::VIDEO_KEY)->find();
        if (empty($task)) {
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        if ($number >= $task['limit']) {
            Common::res(['code' => 1, 'msg' => '今日领取次数已用完']);
        }

        $dayNumber = bcadd($number, 1);

        Db::startTrans();
        try {
            if (empty($userTask)) {
                UserTask::create([
                    'user_id'          => $user_id,
                    'task_type'        => Task::VIDEO_KEY,
                    'number'           => $dayNumber,
                    'settle_num'       => 1,
                    'last_settle_time' => $currentDate
                ]);
            } else {
                $updated = UserTask::where('id', $userTask['id'])
                    ->where('task_type', Task::VIDEO_KEY)
                    ->where('number', $number)
                    ->update([
                        'number'           => $dayNumber,
                        'settle_num'       => bcadd($userTask['settle_num'], 1),
                        'last_settle_time' => $currentDate
                    ]);
                if (empty($updated)) {
                    throw new Exception('更新任务出错');
                }
            }

            $rewardKeyNum = $task['reward']['key_num'];

            (new UserService())->change($user_id,[
                'key_num' => $rewardKeyNum,
            ],['type' => 2, 'content' => '观看视频领取钥匙']);

            Db::commit();
        } catch (\Throwable $throwable) {
            Db::rollback();
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        return $task['reward'];
    }

    public static function addInvited($user_id)
    {
        UserTask::where('user_id', $user_id)
            ->where('task_type', Task::INVITE)
            ->update([
                'number' => Db::raw('number+1')
            ]);
    }

    public static function invitedInit($user_id)
    {
        // 注册拉新
        UserTask::create([
            'user_id' => $user_id,
            'task_type' => Task::INVITE,
            'last_settle_time' => date('Y-m-d H:i:s')
        ]);
    }
}
