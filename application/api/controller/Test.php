<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/19
 * Time: 10:16
 */

namespace app\api\controller;
use app\api\model\Test as TestModel;

class Test
{
    public function test($params)
    {
        $test = new TestModel();
        $test->TestErr();
        return 'test'.$params;
    }
}
