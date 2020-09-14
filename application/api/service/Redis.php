<?php

namespace app\api\service;

use think\cache\driver\Redis as ThinkRedis;
use think\Log;

class Redis extends ThinkRedis
{
    /**是否连接成功 */
    public $connectSuccess;
    public function __construct()
    {
        try {
            parent::__construct([
                'host' => '106.14.183.11'
            ]);
            $this->connectSuccess = true;
        } catch (\Throwable $th) {
            Log::record('redis连接出现了问题' . $th->getMessage(), 'error');
        }
    }

    /**
     * 加锁
     * @return boolean true 加锁成功，false 加锁失败
     */
    public function lock($name, $expire = 3)
    {
        if ($this->has($name)) {
            return false;
        } else {
            return $this->set($name, 'locked', $expire);
        }
    }

    /**解除锁 */
    public function unlock($name)
    {
        return $this->rm($name);
    }
}
