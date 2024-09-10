<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use hulang\filesystem\Driver;
use hulang\web\WebAdapter;

class Web extends Driver
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        // 磁盘路径对应的外部URL路径
        'domain' => 'https://img.zjjcl.cn',
        // 密钥ID
        'secret_id' => 'd73d84e14cf8cd0c53855c2bea35e95ecf00689b',
        // 密钥KEY
        'secret_key' => '5003aec07e6a2591ef2b9ed5b6e31555ab4bea33',
        // 储存桶名称
        'bucket' => 'file',
    ];

    /**
     * 创建适配器实例
     */
    protected function createAdapter()
    {
        return new WebAdapter($this->config);
    }

    /**
     * 获取文件访问地址
     * @param string $path 文件路径
     * @return mixed|string
     */
    public function getUrl(string $path)
    {
        if (isset($this->config['url'])) {
            return $this->concatPathToUrl($this->config['url'], $path);
        }

        return parent::url($path);
    }
}
