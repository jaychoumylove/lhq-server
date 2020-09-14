<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/19
 * Time: 16:40
 */

namespace app\api\model;

use Exception;

class Test
{
    public function TestErr()
    {
        try{
            $res = 1/0;
        }catch (Exception $ex)
        {
//            throw $ex;
        }
        return 'ok';
    }
}