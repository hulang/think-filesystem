<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use League\Flysystem\Visibility;
use Obs\ObsClient;
use think\helper\Arr;
use hulang\filesystem\Driver;
use yzh52521\Flysystem\Obs\ObsAdapter;
use yzh52521\Flysystem\Obs\PortableVisibilityConverter;

class Obs extends Driver
{
    /**
     * 创建适配器实例
     *
     * 本方法用于实例化 OBS (Object-Based Storage) 适配器,它将配置参数组装并传递给 ObsAdapter 类,以便于后续进行对象存储操作。方法内主要涵盖了配置参数的准备和适配器实例的创建
     *
     * @return ObsAdapter 返回一个 ObsAdapter 实例,用于后续的对象存储操作
     */
    protected function createAdapter()
    {
        // 从配置数组中提取基础配置信息
        $config = $this->config;
        $root = $this->config['root'] ?? '';
        $options = $this->config['options'] ?? [];

        // 创建 PortableVisibilityConverter 实例,用于转换目录可见性
        // 可见性默认设置为 PUBLIC,如果配置中未指定
        $portableVisibilityConverter = new PortableVisibilityConverter(
            $this->config['directory_visibility'] ?? $this->config['visibility'] ?? Visibility::PUBLIC
        );

        // 合并配置参数中的特定选项
        $config['is_cname'] ??= $this->config['is_cname'] ?? false;
        $config['token'] ??= $this->config['token'] ?? null;
        $config['bucket_endpoint'] = $this->config['bucket_endpoint'];
        $config['security_token'] = $this->config['security_token'];

        // 合并选项参数,确保特定的配置项被正确设置
        $options = array_merge($options, Arr::only($config, ['url', 'temporary_url', 'endpoint', 'bucket_endpoint']));

        // 创建并返回 ObsClient 实例,用于与 OBS 服务进行交互
        $obsClient = new ObsClient($config);

        // 最终返回 ObsAdapter 实例,完成适配器创建
        return new ObsAdapter($obsClient, $config['bucket'], $root, $portableVisibilityConverter, null, $options);
    }
}
