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
            $freeze = bcadd($state['freeze_balance'], $number, 2);

            UserBill::create([
                'user_id' => $user_id,
                'balance' => $balance,
                'number' => $number,
                'type' => self::WITHDRAW,
                'desc' => '提现:'.$number,
                'status' => 'WAIT'
            ]);

            $updated = UserState::where('user_id', $user_id)
                ->update([
                     'balance' => $balance,
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
}