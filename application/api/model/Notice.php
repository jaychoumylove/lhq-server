<?php


namespace app\api\model;


class Notice extends \app\base\model\Base
{
    public function getExtraAttr($value)
    {
        return json_decode($value, true);
    }

    public static function setNotice($user_id, $data, $type = 1, $is_read = 0)
    {
        self::create([
            'user_id' => $user_id,
            'extra' => $data,
            'type' => $type,
            'is_read' => $is_read,
        ]);
    }
}