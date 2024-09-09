<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use hulang\filesystem\Driver;
use yzh52521\Flysystem\Oss\OssAdapter;

class Aliyun extends Driver
{
    /**
     * 创建OSS适配器实例
     *
     * 该方法负责实例化OssAdapter,并将当前类的配置信息传递给它
     * 主要用于内部实现存储或处理文件操作的适配层创建
     *
     * @return OssAdapter 返回一个使用当前配置初始化的OssAdapter实例
     */
    protected function createAdapter()
    {
        return new OssAdapter($this->config);
    }
}
