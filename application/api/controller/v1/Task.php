<?php
namespace app\api\controller\v1;

use app\base\controller\Base;
use app\base\service\Common;

class Task extends Base
{
    public function settle()
    {
        // 完成任务
        $this->getUser();

        $type = input('type');

        $reward = \app\api\model\Task::settle($this->uid, $type);

        Common::res(['data' => $reward]);
    }
}
