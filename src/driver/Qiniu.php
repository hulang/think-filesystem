<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use Overtrue\Flysystem\Qiniu\QiniuAdapter;
use hulang\filesystem\Driver;

class Qiniu extends Driver
{
    /**
     * 创建七牛云存储适配器
     *
     * 该方法负责实例化并返回一个新的 QiniuAdapter 对象,该对象用于与七牛云存储进行交互
     * 它使用了当前实例的配置信息,包括访问密钥、秘密密钥、存储桶名称和域名
     *
     * @return QiniuAdapter 返回一个配置好的七牛云存储适配器实例
     */
    protected function createAdapter()
    {
        return new QiniuAdapter(
            $this->config['access_key'],
            $this->config['secret_key'],
            $this->config['bucket'],
            $this->config['domain']
        );
    }
}
