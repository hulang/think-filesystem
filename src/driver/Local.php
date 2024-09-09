<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use hulang\filesystem\Driver;

class Local extends Driver
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        'root' => '',
    ];

    /**
     * 创建并返回一个本地文件系统适配器实例
     *
     * 该方法负责根据配置参数初始化一个 LocalFilesystemAdapter,用于处理与本地文件系统的交互操作
     * 它设置了文件系统的可见性、锁定模式以及符号链接的处理方式
     *
     * @return LocalFilesystemAdapter 本地文件系统适配器实例
     */
    protected function createAdapter()
    {
        // 根据配置中的权限和可见性设置,转换为便携式可见性对象
        $visibility = PortableVisibilityConverter::fromArray(
            $this->config['permissions'] ?? [],
            $this->config['visibility'] ?? Visibility::PRIVATE
        );

        // 确定符号链接的处理方式：跳过或禁止
        $links = ($this->config['links'] ?? null) === 'skip'
            ? LocalFilesystemAdapter::SKIP_LINKS
            : LocalFilesystemAdapter::DISALLOW_LINKS;

        // 返回一个新的本地文件系统适配器实例
        return new LocalFilesystemAdapter(
            $this->config['root'], // 根目录路径
            $visibility, // 可见性设置
            $this->config['lock'] ?? LOCK_EX, // 文件锁定模式,默认为排他锁
            $links // 符号链接的处理方式
        );
    }
}
