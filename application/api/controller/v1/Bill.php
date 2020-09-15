<?php


namespace app\api\controller\v1;


use app\api\model\UserBill;
use app\api\model\UserState;
use app\base\service\Common;

class Bill extends \app\base\controller\Base
{
    public function withdraw()
    {
        // 提现
        $this->getUser();

        $number = (float)input('number', 0.00);
        if (empty($number)) {
            Common::res(['code' => 1, 'msg' => '请输入提现金额']);
        }

        UserBill::withdraw($this->uid, $number);

        Common::res();
    }

    public function lottery()
    {
        // 抽奖
        $this->getUser();

        $item = UserBill::lottery($this->uid);

        Common::res(['data' => $item]);
    }

    public function doubleLottery()
    {
        // 双倍抽奖埋点
        $this->getUser();
        $state = UserState::where('user_id', $this->uid)->find();
        $updated = UserState::where('user_id', $this->uid)
            ->where('double_lottery', $state['double_lottery'])
            ->update([
                'double_lottery' => bcadd($state['double_lottery'], 1)
            ]);

        if (empty($updated)) {
            Common::res(['code' => 1, 'msg' => '请稍后再试']);
        }

        Common::res();
    }
}