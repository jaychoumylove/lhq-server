<?php


namespace app\api\controller\v1;


use app\api\model\Lock;
use app\api\model\Rec;
use app\api\model\UserState;
use app\api\model\UserTask;
use think\Db;

class AutoRun extends \app\base\controller\Base
{
    public function minuteHandle()
    {
        $lock = Lock::getVal('minute_end');

        if (time()-60 < strtotime($lock['time'])) {
            return '本分钟已执行过';
        }

        // lock
        Lock::setVal('minute_end', 1);
        Db::startTrans();
        try {
            Db::commit();
        } catch (Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }

        // lock
        Lock::setVal('minute_end', 0);

        return '本分钟执行完毕';
    }

    public function dayHandle()
    {
        $lock = Lock::getVal('day_end');
        if (date('md', time()) == date('md', strtotime($lock['time']))) {
            return '本日已执行过';
        }
        // lock
        Lock::setVal('day_end', 1);

        Db::startTrans();
        try {
            // 签到清理
            UserTask::where('task_type', \app\api\model\Task::SIGN)
                ->where('number', '>=', 7)
                ->update(['number' => 0]);
            UserTask::where('task_type', \app\api\model\Task::DAY_KEY)
                ->where('number', '>', 0)
                ->update(['number' => 0]);
            UserTask::where('task_type', \app\api\model\Task::VIDEO_KEY)
                ->where('number', '>', 0)
                ->update(['number' => 0]);

            // 积分转化成余额
            // 加入日志
            $userState = UserState::where('point', '>=', 3000)
                ->order([
                    'point' => 'desc',
                    'pure_point' => 'desc',
                ])->select();
            if (is_object($userState)) $userState = $userState->toArray();
            $insertRec = [];
            $top = []; // 前三
            foreach ($userState as $key => $value) {
                $number = bcdiv($value['point'], 10000, 1);
                $changeNumber = bcmul($number, 10000);
//                $left = bcsub($value['point'], $changeNumber);
                $item = [
                    'user_id' => $value['user_id'],
                    'content' => '每日积分换算',
                    'type' => 1,
                    'point' => -$changeNumber,
                    'before_point' => $value['point']
                ];

                array_push($insertRec, $item);
                if (count($top) < 3) {
                    array_push($top, $value['user_id']);
                }
            }

            (new Rec())->insertAll($insertRec);

            UserState::where('point', '>=', 3000)
                ->update([
                    'balance' => Db::raw('`balance` + ((`point` div 1000) * 0.1)'),
                    'point' => Db::raw('`point` - ((`point` div 1000)*1000)'),
                ]);

            // 前三名额外奖励
            $topReward = [100,40,20];
            foreach ($top as $index => $item) {
                UserState::where('user_id', $item)->update([
                    'balance' => Db::raw('`balance` + '.$topReward[$index]),
                ]);
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }

        // lock
        Lock::setVal('day_end', 0);

        return '本日执行完毕';
    }

    public function weekHandle()
    {
        $lock = Lock::getVal('week_end');
        if (date('oW', time()) == date('oW', strtotime($lock['time']))) {
            return '本周已执行过';
        }

        // lock
        Lock::setVal('week_end', 1);
        Db::startTrans();
        try {
            Db::commit();
        } catch (Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }

        // lock
        Lock::setVal('week_end', 0);

        return '本分钟执行完毕';
    }

    public function monthHandle()
    {
        $lock = Lock::getVal('month_end');
        if (date('Ym', time()) == date('Ym', strtotime($lock['time']))) {
            return '本月已执行过';
        }

        Lock::setVal('month_end', 1);
        Db::startTrans();
        try {
            Db::commit();
        } catch (Exception $e) {
            Db::rollBack();
            return 'rollBack:' . $e->getMessage();
        }

        // lock
        Lock::setVal('month_end', 0);

        return '本分钟执行完毕';
    }
}