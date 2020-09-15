<?php


namespace app\api\model;


use app\base\service\Common;

class UserState extends \app\base\model\Base
{
    public function user()
    {
        return $this->hasOne('User', 'id', 'user_id')->field('id,nickname,avatarurl');
    }

    public static function changePoint($user_id, $point)
    {
        $userState = UserState::get(['user_id' => $user_id]);
        $update = [
            'point' => bcadd($userState['point'], $point),
            'pure_point' => bcadd($userState['pure_point'], $point)
        ];
        $updated = UserState::where('id', $userState['id'])->update($update);
        if (empty($updated)) {
            Common::res(['code' => 1, 'msg' => '更新贝壳出错']);
        }
        if ($userState['spread_uid'] > 0) {
            UserState::changePointWithSpread($userState['spread_uid'], $point);
        }
    }

    public static function changePointWithSpread($spread_uid, $number)
    {
        $firstNumber = bcmul($number, 0.1);
        $firstState  = UserState::where('user_id', $spread_uid)->find();

        $firstUpdated = UserState::where('id', $firstState['id'])
            ->update([
                'point'           => bcadd($firstState['point'], $firstNumber),
                'recommend_count' => bcadd($firstState['recommend_count'], $firstNumber),
                'first_count'     => bcadd($firstState['first_count'], $firstNumber),
            ]);
        if (empty($firstUpdated)) {
            Common::res(['code' => 1, 'msg' => '更新直推人状态出错']);
        }

        if ($firstState['spread_uid'] > 0) {
            $secondNumber = bcmul($number, 0.05);
            $secondState  = UserState::where('user_id', $firstState['spread_uid'])->find();

            $secondUpdated = UserState::where('id', $secondState['id'])
                ->update([
                    'point'           => bcadd($secondState['point'], $secondNumber),
                    'recommend_count' => bcadd($secondState['recommend_count'], $secondNumber),
                    'second_count'    => bcadd($secondState['second_count'], $secondNumber),
                ]);
            if (empty($secondUpdated)) {
                Common::res(['code' => 1, 'msg' => '更新扩散人状态出错']);
            }
        }
    }
}