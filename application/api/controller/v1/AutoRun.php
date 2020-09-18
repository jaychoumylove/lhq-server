<?php


namespace app\api\controller\v1;


use app\api\model\Cfg;
use app\api\model\Lock;
use app\api\model\Notice;
use app\api\model\Rec;
use app\api\model\UserState;
use app\api\model\UserTask;
use Exception;
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

            $point2balance = Cfg::getCfg('point_balance');
            // 前三名额外奖励
            $topReward = Cfg::getCfg('top_three_bonus');
            $length = count($topReward);
            // 加入日志
            $userState = UserState::where('point', '>=', $point2balance['min_point'])
                ->order([
                    'point' => 'desc',
                    'pure_point' => 'desc',
                ])->select();
            if (is_object($userState)) $userState = $userState->toArray();
            $insertRec = [];
            $insertNot = [];
            $top = []; // 前三
            foreach ($userState as $key => $value) {
                $number = bcdiv($value['point'], $point2balance['exchange'], 1);
                $changeNumber = bcmul($number, $point2balance['exchange']);
                $addBalance = bcdiv($changeNumber, $point2balance['exchange'], 1);
                $item = [
                    'user_id' => $value['user_id'],
                    'content' => '每日积分换算',
                    'type' => 1,
                    'point' => -$changeNumber,
                    'before_point' => $value['point']
                ];

                array_push($insertRec, $item);
                if (count($top) < $length) {
                    array_push($top, $value['user_id']);
                }

                $noticeItem = [
                    'user_id' => $value['user_id'],
                    'content' => '每日积分换算，积分-'.$changeNumber.'，余额+'.$addBalance,
                    'type' => 1,
                    'extra' => json_encode([
                        'point' => -$changeNumber,
                        'balance' => $addBalance
                    ]),
                    'is_read' => 0
                ];
                array_push($insertNot, $noticeItem);
            }

            (new Rec())->insertAll($insertRec);
            (new Notice())->insertAll($insertNot);

            $exchangeMinPoint = bcmul($point2balance['exchange'], $point2balance['min_step']);
            UserState::where('point', '>=', $point2balance['min_point'])
                ->update([
                    'balance' => Db::raw('`balance` + ((`point` div '.$exchangeMinPoint.') * 0.1)'),
                    'point' => Db::raw('`point` - ((`point` div '.$exchangeMinPoint.')*'.$exchangeMinPoint.')'),
                ]);

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