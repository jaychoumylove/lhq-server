<?php


namespace app\api\model;


use app\base\model\Base;
use app\base\service\Common;
use think\Db;
use think\Exception;

class UserBill extends Base
{
    const WITHDRAW = 'WITHDRAW';

    public static function withdraw($user_id, $number)
    {
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

    public static function lottery($uid)
    {
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
            $leftKeyNum    = bcsub($keyNum, 1);
            $number        = $item['number'];
            $doubleLottery = false;
            if ($state['double_lottery'] > 0) {
                // 如果有抽奖埋点就双倍
                $number        = bcmul($number, 2);
                $doubleLottery = true;
            }

            $update = [
                'key_num'    => $leftKeyNum,
                'point'      => bcadd($state['point'], $number),
                'pure_point' => bcadd($state['pure_point'], $number)
            ];

            if ($doubleLottery) {
                $update['double_lottery'] = bcsub($state['double_lottery'], 1);
            }

            $updated = UserState::where('id', $state['id'])->update($update);
            if (empty($updated)) {
                Common::res(['code' => 1, 'msg' => '更新状态出错']);
            }

            // 是否有上级
            if ($state['spread_uid'] > 0) {
               UserState::changePointWithSpread($state['spread_uid'], $number);
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
            'reward' => $item['reward']
        ];
    }
}