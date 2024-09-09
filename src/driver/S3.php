<?php

declare(strict_types=1);

namespace hulang\filesystem\driver;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Visibility;
use think\helper\Arr;
use hulang\filesystem\Driver;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter as AwsS3PortableVisibilityConverter;

class S3 extends Driver
{
    /**
     * 创建S3适配器
     * 
     * 本方法负责根据配置参数初始化一个S3客户端适配器,用于后续的文件操作
     * 它主要包括配置S3客户端,设置默认的存储桶和可见性设置
     * 
     * @return AwsS3V3Adapter 返回一个配置好的S3适配器实例,用于后续的文件操作
     */
    protected function createAdapter()
    {
        // 格式化并获取S3配置
        $s3Config = $this->formatS3Config($this->config);
        // 获取配置中的root值,如果没有设置则默认为空字符串
        $root = (string) ($s3Config['root'] ?? '');
        // 根据配置初始化可见性转换器,如果没有设置visibility则默认为PUBLIC
        $visibility = new AwsS3PortableVisibilityConverter(
            $this->config['visibility'] ?? Visibility::PUBLIC
        );
        // 获取配置中的stream_reads值,如果没有设置则默认为false
        $streamReads = $s3Config['stream_reads'] ?? false;
        // 初始化S3客户端
        $client = new S3Client($s3Config);
        // 返回一个新的配置好的S3适配器实例
        return new AwsS3V3Adapter($client, $s3Config['bucket'], $root, $visibility, null, $this->config['options'] ?? [], $streamReads);
    }

    /**
     * 格式化S3配置信息
     * 
     * 此方法用于处理S3配置参数,确保配置的一致性和包含必要的信息
     * 它主要执行以下任务:
     * 1. 确保配置中包含指定的版本信息
     * 2. 从配置中提取AWS认证信息
     * 3. 移除配置中可能存在的安全敏感信息,如令牌
     *
     * @param array $config 原始的S3配置数组,应包含必要的配置信息,如AWS认证信息
     * @return array 格式化后的S3配置数组,移除了安全敏感信息并添加了必要的版本信息
     */
    protected function formatS3Config(array $config)
    {
        // 确保配置中包含版本信息,默认为'latest'
        $config += ['version' => 'latest'];

        // 如果配置中包含AWS认证信息,则提取它们
        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        // 移除配置中的安全敏感信息,如令牌
        return Arr::except($config, ['token']);
    }

    /**
     * 获取资源的临时URL
     * 
     * 本方法用于生成一个资源的临时URL,该URL在一段时间后失效这样做是为了保护资源不被长期公开访问
     * 临时URL的过期时间默认为1800秒(30分钟),或者根据配置中的expire选项来确定
     * 
     * @param string $path 资源路径,表示需要访问的具体文件或目录的路径
     * @return string 临时访问URL,包含资源路径和过期时间的完整URL
     */
    protected function getUrl(string $path)
    {
        // 创建一个DateTime对象,用于计算临时URL的过期时间
        $expireAt = new \DateTime();
        // 根据配置中的expire值或默认值(1800秒)计算过期时间,并设置到$expireAt对象中
        $expireAt->add(\DateInterval::createFromDateString(($this->config['expire'] ?? 1800) . ' seconds'));
        // 初始化League\Flysystem\Config对象,用于配置临时URL的选项
        $options = new \League\Flysystem\Config;
        // 调用adapter的temporaryUrl方法生成并返回临时URL
        return $this->adapter->temporaryUrl($path, $expireAt, $options);
    }
}
