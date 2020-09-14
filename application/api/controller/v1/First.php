<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/19
 * Time: 10:53
 */

namespace app\api\controller\v1;


use app\api\validate\TestValidate;
use think\Controller;
use app\api\model\Test as TestModel;

class First extends Controller
{
    public function info($id,$name,$mobile)
    {
        (new TestValidate())->goCheck();
        $test = new TestModel();
        $test->TestErr();
        return $id.$name.$mobile;
    }
}