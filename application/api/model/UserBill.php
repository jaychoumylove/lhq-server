<?php


namespace app\api\model;


use app\api\service\User as UserService;
use app\base\model\Base;
use app\base\service\Common;
use think\Db;
use think\Exception;

class UserBill extends Base
{
    const WITHDRAW = 'WITHDRAW';

    /**
     * @param $user_id
     * @param $number
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function withdraw($user_id, $number)
    {
        $avatarurl = User::where('id',$user_id)->value('avatarurl');
        if(!$avatarurl) Common::res(['code' => 1, 'msg' => '请点击个人中心-头像完善个人信息']);

        $state = UserState::where('user_id', $user_id)->find();
        if ($state['balance'] < $number) {
            Common::res(['code' => 1, 'msg' => '余额不足']);
        }

        if ($state['balance'] < 0.3) {
            Common::res(['code' => 1, 'msg' => '余额少于0.3元']);
        }

        $hasWithdraw = UserBill::whereTime('create_time', 'today')
            ->where('user_id', $user_id)
            ->find();

        if ($hasWithdraw) {
            Common::res(['code' => 1, 'msg' => '今日已提现']);
        }

        Db::startTrans();
        try {
            // todo  微信发起转账
            $balance = bcsub($state['balance'], $number, 2);
            $freeze  = bcadd($state['freeze_balance'], $number, 2);

            UserBill::create([
                'user_id' => $user_id,
                'balance' => $balance,
                'number'  => $number,
                'type'    => self::WITHDRAW,
                'desc'    => '提现:' . $number,
                'status'  => 'WAIT'
            ]);

            $updated = UserState::where('user_id', $user_id)
                ->update([
                    'balance'        => $balance,
                    'freeze_balance' => $freeze
                ]);
            if (empty($updated)) {
                throw new Exception('更新冻结金额失败');
            }

            Db::commit();
        } catch (\Throwable $throwable) {
            Db::rollback();

            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }
    }

    /**
     * @param $uid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function lottery($uid)
    {

        $avatarurl = User::where('id',$uid)->value('avatarurl');
        if(!$avatarurl) Common::res(['code' => 1, 'msg' => '请点击个人中心-头像完善个人信息']);

        // 抽奖
        $state  = UserState::get(['user_id' => $uid]);
        $keyNum = (int)$state['key_num'];
        if (empty($keyNum)) {
            Common::res(['code' => 1, 'msg' => '钥匙不足']);
        }

        $pool = Lottery::where('able', 1)->select();
        if (is_object($pool)) $pool = $pool->toArray();

        $item = Common::lottery($pool);
        Db::startTrans();
        try {
            //
            $number        = $item['number'];
            $doubleLottery = false;
            if ($state['double_lottery'] > 0) {
                // 如果有抽奖埋点就双倍
                $number        = bcmul($number, 2);
                $doubleLottery = true;
            }

            $update = ['key_num' => -1 ,'point' => $number, 'pure_point' => $number,];
            if ($doubleLottery) {
                $update['double_lottery'] = -1;
            }

            (new UserService())->change($uid,$update,['type' => 1, 'content' => '消耗钥匙抽奖获得']);

            // 是否有上级
            if ($state['spread_uid'] > 0) {
               UserState::changePointWithSpread($uid,$state['spread_uid'], $number);
            }
            // 写入抽奖日志
            LotteryLog::create([
                'user_id' => $uid,
                'reward' => json_encode($item['reward'])
            ]);

            Db::commit();
        } catch (\Throwable $throwable) {
            Db::rollback();
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        return [
            'index'  => $item['index'],
            'number' => $number,
            'reward' => array_merge($item['reward'], ['desc' => '贝壳'.$number])
        ];
    }
}