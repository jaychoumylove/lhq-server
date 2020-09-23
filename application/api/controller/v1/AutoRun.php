<?php


namespace app\api\controller\v1;


use app\api\model\Cfg;
use app\api\model\Lock;
use app\api\model\Notice;
use app\api\model\Rec;
use app\api\model\UserState;
use app\api\model\UserTask;
use app\base\service\WxAPI;
use Exception;
use think\Db;
use think\Log;

class AutoRun extends \app\base\controller\Base
{
    public function index()
    {
        echo $this->dayHandle() . '</br>';
        echo $this->weekHandle() . '</br>';
        echo $this->monthHandle() . '</br>';
    }

    public function minuteHandle()
    {
        $lock = Lock::getVal('minute_end');

        if (time() - 60 < strtotime($lock['time'])) {
            return '本分钟已执行过';
        }

        // lock
        Lock::setVal('minute_end', 1);
        Db::startTrans();
        try {

            if (date('H') >= 8 && date('H') <= 10) {
                $notices = Notice::where('type', 1)
                    ->where('is_read', 0)
                    ->where('is_send', 0)
                    ->whereTime('create_time', 'today')
                    ->limit(400)
                    ->select();
                if (is_object($notices)) $notices = json_decode($notices, true);
                foreach ($notices as $notice) {
                    $isDone = Notice::where('id', $notice['id'])->update(['is_send' => 1]);
                    $openid = \app\api\model\User::where('id', $notice['user_id'])->value('openid');
                    if ($isDone) {
                        $data = [
                            'openid'  => $openid,
                            'balance' => $notice['extra']['balance'],
                            'point'   => $notice['extra']['point'],
                            'date'    => date('Y-m-d', strtotime($notice['create_time'])),
                        ];

                        (new WxAPI())->sendTemplateMini($data);
                    }
                }
            }

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
            if ($point2balance['auto']) {
                // 奖池奖金
                $allReward = Cfg::getCfg('bonus_pools');
                // 前三名额外奖励
                $topReward = Cfg::getCfg('top_three_bonus');
                $length    = count($topReward);
                // 加入日志
                $userState = UserState::where('point', '>=', $point2balance['min_point'])
                    ->order([
                        'point'      => 'desc',
                        'pure_point' => 'desc',
                    ])->select();
                if (is_object($userState)) $userState = $userState->toArray();

//                $exchangeMinPoint = bcmul($point2balance['exchange'], $point2balance['min_step']);
//                $sumPoint         = (int)array_sum(array_column($userState, 'point')); // 总贝壳数
//                $sumPoint         = bcdiv($sumPoint, $exchangeMinPoint) * $exchangeMinPoint; // 取整去零头 20394->20000
//                $leftReward       = (int)bcsub($allReward, array_sum($topReward)); // 去除前三奖金
//                $rate             = $leftReward / $sumPoint; // 计算积分奖金比例
                $rate = 1 / 10000; // 暂时替换成10000/1
                $insertRec        = [];
                $insertNot        = [];
                $top              = []; // 前三
                foreach ($userState as $key => $value) {
                    $number       = bcdiv($value['point'], $point2balance['exchange'], 1);
                    $changeNumber = bcmul($number, $point2balance['exchange']);
                    $addBalance   = bcmul($changeNumber, $rate, 1);
                    $item         = [
                        'user_id'      => $value['user_id'],
                        'content'      => '每日积分换算',
                        'type'         => 1,
                        'point'        => -$changeNumber,
                        'before_point' => $value['point']
                    ];

                    array_push($insertRec, $item);
                    $noticeBalance = $addBalance; // 通知消息把前三奖金合并一起了
                    if (count($top) < $length) {
                        $noticeBalance += $topReward[count($top)];
                        array_push($top, $value['user_id']);
                    }


                    $noticeItem = [
                        'user_id' => $value['user_id'],
                        'content' => '每日积分换算，积分-' . $changeNumber . '，余额+' . $noticeBalance,
                        'type'    => 1,
                        'extra'   => json_encode([
                            'point'   => -$changeNumber,
                            'balance' => $noticeBalance
                        ]),
                        'is_read' => 0
                    ];
                    array_push($insertNot, $noticeItem);

                    UserState::where('point', '>=', $point2balance['min_point'])
                        ->where('user_id', $value['user_id'])
                        ->update([
                            'balance' => Db::raw('`balance` + ' . $noticeBalance),
                            'point'   => Db::raw('`point` - ' . $changeNumber),
                        ]);
                }

                (new Rec())->insertAll($insertRec);
                (new Notice())->insertAll($insertNot);
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