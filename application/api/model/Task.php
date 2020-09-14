<?php

namespace app\api\model;

use app\base\model\Base;

class Task extends Base
{
    //

    public function TaskType()
    {
        return $this->belongsTo('TaskType', 'type', 'id');
    }
}
