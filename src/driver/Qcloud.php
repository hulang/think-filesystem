<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use Overtrue\Flysystem\Cos\CosAdapter;
use hulang\filesystem\Driver;

class Qcloud extends Driver
{
    /**
     * 创建CosAdapter实例
     * 
     * 该方法负责实例化CosAdapter,以便后续进行具体的Cos操作
     * 
     * @return CosAdapter 返回一个CosAdapter实例,用于后续的Cos操作
     */
    protected function createAdapter()
    {
        return new CosAdapter($this->config);
    }
}
