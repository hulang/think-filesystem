<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use Google\Cloud\Storage\StorageClient;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use hulang\filesystem\Driver;

class Google extends Driver
{
    /**
     * 创建Google云存储适配器
     *
     * 该方法负责实例化一个Google云存储的客户端,并根据配置信息设置存储桶和路径前缀
     * 它主要用于将文件存储到Google云存储中,使用适配器模式以便于在不同环境或配置下灵活使用
     *
     * @return GoogleCloudStorageAdapter 返回一个Google云存储适配器实例,用于后续的文件操作
     */
    protected function createAdapter()
    {
        // 创建Google云存储客户端,配置项目ID
        $storageClient = new StorageClient([
            'projectId' => $this->config['project_id'],
        ]);

        // 根据配置信息获取存储桶
        $bucket = $storageClient->bucket($this->config['bucket']);

        // 返回Google云存储适配器实例,设置存储桶和路径前缀
        return new GoogleCloudStorageAdapter($bucket, $this->config['prefix'] ?? '');
    }
}
