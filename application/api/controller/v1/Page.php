<?php

namespace app\api\controller\v1;


use app\base\controller\Base;

class Page extends Base
{
    public function index()
    {
        // 首页
        // 奖池奖品信息
        // 抽取日志
        // 排行Top3
    }

    public function friendRank()
    {
        // 裂变
        // 个人贡献信息
        // 好友贡献排行
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
