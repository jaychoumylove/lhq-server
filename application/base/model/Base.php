<?php

namespace app\base\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;
use traits\model\SoftDelete;

class Base extends Model
{
    // 隐藏字段
    protected $hidden = ['update_time', 'delete_time'];

    protected function setCreateTimeAttr($value)
    {
        return date('Y-m-d H:i:s', $value);
    }
    protected function setUpdateTimeAttr($value)
    {
        return date('Y-m-d H:i:s', $value);
    }
    protected function setDeleteTimeAttr($value)
    {
        return date('Y-m-d H:i:s', $value);
    }


    // 软删除
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    protected $update = ['delete_time'];

    /**
     * 数据表清理
     * @param int $day   清除多少天之前的数据
     * @param int $count 条数
     */
    public static function clear($day = 30, $count = 10)
    {
        $clearTime = time() - 3600 * 24 * $day;
        static::where('1=1')->whereTime('create_time', '<', $clearTime)->limit($count)->delete(true);
    }

    /**
     * @param Model  $model
     * @param array  $ids
     * @param string $key
     * @param string $fields
     * @param array  $map
     * @return array
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function getDictList(Model $model, array $ids, $key, $fields = '*', $map = [])
    {
        $list = $model->where($key, 'in', $ids)
            ->where($map)
            ->field($fields)
            ->select ();
        if (is_object ($list)) $list = $list->toArray ();
        return array_column ($list, null, $key);
    }
}
