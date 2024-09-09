<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use hulang\filesystem\Driver;

class Sftp extends Driver
{
    /**
     * 创建SFTP适配器实例
     * 
     * 本方法负责根据配置参数初始化SFTP连接提供者(SftpConnectionProvider)和可见性转换器(PortableVisibilityConverter),
     * 并使用这些配置创建SFTP适配器(SftpAdapter)实例返回
     * 适配器模式用于将SFTP操作的细节封装起来,使得后续更换文件系统时代码修改更为简便
     * 
     * @return SftpAdapter 返回一个SftpAdapter实例,用于处理SFTP相关的文件操作
     */
    protected function createAdapter()
    {
        // 从配置数组创建SFTP连接提供者实例
        $provider = SftpConnectionProvider::fromArray($this->config);

        // 设置根目录,默认为'/'
        $root = $this->config['root'] ?? '/';

        // 根据配置中的权限设置创建可见性转换器实例,默认为空数组
        $visibility = PortableVisibilityConverter::fromArray(
            $this->config['permissions'] ?? []
        );

        // 返回新的SFTP适配器实例
        return new SftpAdapter($provider, $root, $visibility);
    }
}
