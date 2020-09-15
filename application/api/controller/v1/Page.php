<?php

namespace app\api\controller\v1;


use app\api\model\Lottery;
use app\api\model\LotteryLog;
use app\api\model\UserBill;
use app\api\model\UserState;
use app\api\model\UserTask;
use app\base\controller\Base;
use app\base\service\Common;

class Page extends Base
{
    public function index()
    {
        // 首页
        // 奖池奖品信息
        // 抽取日志
        // 排行Top3
        $login = $this->checkLogin();
        $userState = null;
        $user = null;
        if ($login) {
            $userState = UserState::get(['user_id' => $this->uid]);
            $user = \app\api\model\User::where('id', $this->uid)->field('id,nickname,avatarurl')->find();
        }

        $lottery = Lottery::order([
                'index' => 'asc',
                'create_time' => 'desc'
            ])
            ->select();

        $log = LotteryLog::order('create_time', 'desc')
            ->limit(6)
            ->select();

        $top = UserState::order([
                'point' => 'desc',
                'update_time' => 'asc'
            ])
            ->limit(3)
            ->select();

        Common::res(['data' => [
            'log' => $log,
            'lottery' => $lottery,
            'top' => $top,
            'key_num' => $userState['key_num'],
            'user' => $user
        ]]);
    }

    public function friendRank()
    {
        // 裂变
        // 个人贡献信息
        // 好友贡献排行
        $page = input('page', 1);
        $size = input('size', 10);
        $state =  [
            'sum' => 0,
            'first' => 0,
            'second' => 0
        ];
        $login = $this->checkLogin();
        if (empty($login)) {
            Common::res(['data' => [
                'state' => $state,
                'list' => []
            ]]);
        }

        $this->getUser();

        $type = (int)input('type', 1);
        if (empty($type)) {
            Common::res(['code' => 1, 'msg' => '请选择查看类型']);
        }
        if ($type == 1) {
            $uids = UserState::where('spread_uid', $this->uid)->column('user_id');
        }
        if ($type == 2) {
            $spreadIds = UserState::where('spread_uid', $this->uid)->column('user_id');
            if (empty($spreadIds)) {
                $uids = [];
            } else {
                $uids = UserState::where('spread_uid', 'in', $spreadIds)->column('user_id');
            }
        }

        $userState = UserState::where('user_id', $this->uid)->find();
        $state = [
            'sum' => $userState['recommend_count'],
            'first' => $userState['first_count'],
            'second' => $userState['second_count']
        ];

        if (empty($uids)) {
            Common::res(['data' => [
                'state' => $state,
                'list' => []
            ]]);
        }

        $mul = [1 => 0.1, 2 => 0.05];
        $mulValue = $mul[$type];
        $minPoint = bcdiv(1, $mulValue);

        $list = UserState::with(['user'])
            ->field('pure_point,id,user_id')
            ->where('user_id', 'in', $uids)
            ->where('pure_point', '>=', $minPoint)
            ->order([
                'pure_point' => 'desc',
                'update_time' => 'desc'
            ])
            ->page($page, $size)
            ->select();

        if (is_object($list)) $list = $list->toArray();

        foreach ($list as $key => $value) {
            $value['point_count'] = bcmul($value['pure_point'], $mulValue);
            $list[$key] = $value;
        }

        Common::res(['data' => [
            'state' => $state,
            'list' => $list
        ]]);
    }

    public function rank()
    {
        // 排行 20名 积分desc
        $list = UserState::with(['user'])
            ->field('point,pure_point,id,user_id')
            ->order([
                'point' => 'desc',
                'pure_point' => 'desc',
                'update_time' => 'desc'
            ])
            ->limit(20)
            ->select();

        if (is_object($list)) $list = $list->toArray();

        Common::res(['data' => [
            'list' => $list
        ]]);
    }

    public function userInfo()
    {
        // 我的
        // 个人信息
        // 积分，钱包
        // 签到任务完成情况
        // 其余钥匙任务
        $login = $this->checkLogin();
        if (empty($login)) {
            Common::res();
        }

        $this->getUser();
        $userInfo = \app\api\model\User::getInfo($this->uid);
        $userState = UserState::where('user_id', $this->uid)
            ->field('user_id,balance,point,key_num')
            ->find();

        $task = \app\api\model\Task::all();
        if (is_object($task)) $task = $task->toArray();

        $signTask = array_filter($task, function ($item) {
            return $item['type'] == \app\api\model\Task::SIGN;
        });
        $otherTask = array_filter($task, function ($item) {
            return $item['type'] != \app\api\model\Task::SIGN;
        });

        $userTask = UserTask::where('user_id', $this->uid)->select();
        if (is_object($userTask)) $userTask = $userTask->toArray();
        $userTaskDict = array_column($userTask, null, 'task_type');

        $currentTime = time();
        $currentDate = date('Y-m-d H:i:s', $currentTime);
        $signCurrent = 1;
        $signAble = true;
        if (array_key_exists(\app\api\model\Task::SIGN, $userTaskDict)) {
            $userSignTask = $userTaskDict[\app\api\model\Task::SIGN];
            $signCurrent = bcadd($userSignTask['number'], 1);
            $settleDay = date('d', strtotime($userSignTask['last_settle_time']));
            if (date('d', $currentTime) != $settleDay) {
                $signAble = true;
            }
        }

        foreach ($signTask as $key => $value) {
            // 1 已签到
            // 0 可签到
            // -1 不可签到
            $status = -1;
            if ($value['number'] < $signCurrent) {
                $status = 1;
            }
            if ($value['number'] == $signCurrent) {
                $status = 1;
                if ($signAble) {
                    $status = 0;
                }
            }

            $value['status'] = $status;
            $signTask[$key] = $value;
        }

        foreach ($otherTask as $index => $item) {
            $number = 0;
            $ableSettle = false;
            if (array_key_exists($item['type'], $userTaskDict)) {
                $task = $userTaskDict[$item['type']];
                $number = $task['number'];

                if ($item['type'] == \app\api\model\Task::INVITE) {
                    $ableSettle = $number > 0;
                }

                if ($item['type'] == \app\api\model\Task::DAY_KEY) {
                    $lastSettle = strtotime($task['last_settle_time']);
                    $diff = bcsub($currentTime, $lastSettle);
                    $ableSettle = $diff > $item['reward']['second'];
                }

                if ($item['type'] == \app\api\model\Task::VIDEO_KEY) {
                    $ableSettle = $number < 20;
                }
            }

            $item['times'] = $number;
            $item['able_settle'] = $ableSettle;

            $otherTask[$index] = $item;
        }

        Common::res(['data' => [
            'user' => [
                'id' => $userInfo['id'],
                'nickname' => $userInfo['nickname'],
                'avatarurl' => $userInfo['avatarurl'],
                'point' => $userState['point'],
                'balance' => $userState['balance'],
                'key_num' => $userState['key_num'],
            ],
            'task' => [
                'sign' => $signTask,
                'other' => $otherTask
            ]
        ]]);
    }

    public function bill()
    {
        // 钱包
        $this->getUser();

        $userState = UserState::where('user_id', $this->uid)
            ->field('user_id,balance,point')
            ->find();

        Common::res(['data' => [
            'user_id' => $userState['user_id'],
            'balance' => $userState['balance'],
            'point' => $userState['point']
        ]]);
    }

    public function qrCode()
    {
        // 我的二维码
        $this->getUser();

        $userState = UserState::where('user_id', $this->uid)
            ->field('user_id,qrcode')
            ->find();

        Common::res(['data' => [
            'user_id' => $userState['user_id'],
            'qrcode' => $userState['qrcode']
        ]]);
    }

    public function withdrawLog()
    {
        // 提现记录
        $this->getUser();
        $page = input('page', 1);
        $size = input('size', 10);

        $list = UserBill::where('user_id', $this->uid)
            ->where('type', UserBill::WITHDRAW)
            ->page($page, $size)
            ->select();

        Common::res(['data' => $list]);
    }
}
