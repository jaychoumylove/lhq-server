<?php
/**
 * Created by PhpStorm.
 * User: 二狗蛋
 * Date: 2018/4/19
 * Time: 10:55
 */

namespace app\api\validate;

use app\lib\exception\ParameterException;
use think\Request;
use think\Validate;

class BaseValidate extends Validate
{
    public function goCheck()
    {
        $params = Request::instance()->param();

        if(!$this->check($params)){
            throw new ParameterException([
                'message' =>  $this->error
            ]);
        }
        return true;
    }

    protected function isMobile($mobile,$rule='',$data='',$fieid='')
    {
        $reg = '^1(3|4|5|7|8)[0-9]\d{8}$^';

        if(preg_match($reg,$mobile)) return true;

        return false;
    }

    protected function isNotEmpty($value,$rule='',$data='',$fieid='')
    {
        if(empty($value)) return false;

        return true;
    }

    protected function MustPositiveNumber($value,$rule='',$data='',$fieid ='')
    {
        if(empty($value)) return false;

        if(is_numeric($value) && $value > 0) return true;

        return false;
    }

    protected function isPositiveInteger($value,$rule='',$data='',$fieid ='')
    {
        if(empty($value)) return false;

        if(is_numeric($value) && is_int($value) && $value > 0 ) return true;

        return false;
    }

    protected function checkPwd($value,$rule='',$data='',$fieid ='')
    {
        $r1 = '^[a-z]$^';
        $r2 = '^[A-Z]$^';
        $r3 = '^[0-9]$^';
        if(preg_match($r1,$value) || preg_match($r2,$value) || preg_match($r3,$value)) return true;

        return false;
    }

    protected function checkSMSCode($value,$rule='',$data='',$fieid ='')
    {
        if(!is_numeric($value) && (strlen($value) != 6) && ($value < 0)) return false;

        return true;
    }
}