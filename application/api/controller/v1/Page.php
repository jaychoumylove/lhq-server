<?php

namespace app\api\controller\v1;


use app\api\model\Lottery;
use app\api\model\LotteryLog;
use app\api\model\UserState;
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
            $user = \app\api\model\User::get($this->uid);
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
            'user_state' => $userState,
            'user' => $user
        ]]);
    }

    public function friendRank()
    {
        // 裂变
        // 个人贡献信息
        // 好友贡献排行

        $type = input();
    }

    public function rank()
    {
        // 排行 20名 积分desc
    }

    public function userInfo()
    {
        // 我的
        // 个人信息
        // 积分，钱包
        // 签到任务完成情况
        // 其余钥匙任务
    }

    public function bill()
    {
        // 钱包
    }

    public function qrCode()
    {
        // 我的二维码
    }

    public function withdrawLog()
    {
        // 提现记录
    }
}
