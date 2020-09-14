<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/29
 * Time: 16:08
 */

namespace app\api\controller\v1;


use app\api\controller\BaseController;
use app\api\model\User as UserModel;
use app\api\service\Token;
use app\api\validate\RegisterValidate;
use app\lib\exception\InfoException;
use app\lib\exception\SuccessMessage;

class User extends BaseController
{
    const Identity = 'user';

    public function Info()
    {
        $this->checkIdentity(self::Identity);
        $res = UserModel::get(Token::getCurrentTokenVar('uid'));

        if ($res) {
            throw new SuccessMessage([
                'data' => $res
            ]);
        }

        throw new InfoException([
            'message' => '用户信息未找到'
        ]);
    }

    public function register()
    {
        (new RegisterValidate())->goCheck();
    }

    public function getList(){
        $user = new UserModel();
        $list = $user->paginate(2,10);
        return json($list);
    }
}