<?php

declare(strict_types=1);

namespace hulang\filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use think\Cache;
use think\File;
use think\file\UploadedFile;
use think\helper\Arr;
use voku\helper\ASCII;

/**
 * Class Driver
 * @package hulang\filesystem
 * @mixin Filesystem
 */
abstract class Driver
{

    /** @var Cache */
    protected $cache;

    /** @var Filesystem */
    protected $filesystem;

    protected $adapter;

    /**
     * The Flysystem PathPrefixer instance.
     *
     * @var PathPrefixer
     */
    protected $prefixer;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    public function __construct(Cache $cache, array $config)
    {
        $this->cache = $cache;
        $this->config = array_merge($this->config, $config);

        $separator = $config['directory_separator'] ?? DIRECTORY_SEPARATOR;
        $this->prefixer = new PathPrefixer($config['root'] ?? '', $separator);

        if (isset($config['prefix'])) {
            $this->prefixer = new PathPrefixer($this->prefixer->prefixPath($config['prefix']), $separator);
        }

        $this->adapter = $this->createAdapter();
        $this->filesystem = $this->createFilesystem($this->adapter, $this->config);
    }

    abstract protected function createAdapter();

    /**
     * 根据配置创建Filesystem实例
     * 
     * 此方法主要用于根据传入的适配器和配置数组创建一个合适的Filesystem实例
     * 它允许将文件系统设置为只读,或者为路径添加前缀,并根据配置数组的特定参数配置Filesystem实例
     * 
     * @param FilesystemAdapter $adapter 文件系统适配器,用于与文件系统交互
     * @param array $config 配置数组,包含文件系统的配置信息,如读取模式和路径前缀等
     * @return Filesystem 返回配置好的Filesystem实例
     */
    protected function createFilesystem(FilesystemAdapter $adapter, array $config)
    {
        // 如果配置中设置为只读,创建并使用只读文件系统适配器包装原始适配器
        if ($config['read-only'] ?? false === true) {
            $adapter = new ReadOnlyFilesystemAdapter($adapter);
        }

        // 如果配置中设置了前缀,创建并使用路径前缀适配器包装原始适配器
        if (!empty($config['prefix'])) {
            $adapter = new PathPrefixedAdapter($adapter, $config['prefix']);
        }

        // 返回新的Filesystem实例,使用配置好的适配器和部分配置参数
        // 这些配置参数包括:directory_visibility,disable_asserts,temporary_url,url,visibility
        return new Filesystem($adapter, Arr::only($config, [
            'directory_visibility',
            'disable_asserts',
            'temporary_url',
            'url',
            'visibility',
        ]));
    }

    /**
     * 获取文件完整路径
     * 
     * 该方法接受一个相对路径作为参数,并返回一个完整的文件路径
     * 完整路径是通过前缀路径服务(prefixer)和提供的相对路径拼接而成
     * 此方法用于将应用程序中的相对文件路径转换为可用于文件操作的绝对路径
     * 
     * @param string $path 相对路径字符串,表示相对于某个基础路径的文件或目录位置
     * @return mixed|string 返回拼接前缀后的完整文件路径字符串
     */
    public function path(string $path): string
    {
        return $this->prefixer->prefixPath($path);
    }

    /**
     * 将给定的路径拼接到URL末尾
     * 
     * 该方法用于生成正确的URL格式,确保URL和路径可以完美拼接,不会出现多余的斜杠
     * 
     * @param string $url 基础URL,例如 "http://example.com"
     * @param string $path 要拼接的路径,例如 "resource"
     * 
     * @return mixed|string 拼接后的完整URL
     */
    protected function concatPathToUrl($url, $path)
    {
        // 移除URL末尾的斜杠并添加新的路径,确保路径正确拼接到URL上
        return rtrim($url, '/') . '/' . ltrim($path, '/');
    }

    /**
     * 判断指定路径的资源是否存在
     * 
     * 本方法主要用于检查存储系统中的某个路径是否存在对应的资源或文件
     * 它利用了底层的文件系统操作工具来实现这一功能
     * 
     * @param string $path 要检查的路径
     * @return mixed|bool 如果路径存在,则返回true;否则返回false
     */
    public function exists($path): bool
    {
        return $this->filesystem->has($path);
    }

    /**
     * 判断指定路径的文件或目录是否缺失
     *
     * 本函数通过调用 exists 函数判断指定路径的文件或目录是否存在
     * 并返回其逻辑相反的布尔值,即如果文件或目录不存在,则返回 true,反之返回 false
     *
     * @param string $path 待检查的文件或目录路径
     * @return bool 如果文件或目录缺失,则返回true,否则返回false
     */
    public function missing($path): bool
    {
        return !$this->exists($path);
    }

    /**
     * 检查文件是否存在于指定路径
     * 
     * @param string $path 要检查的文件路径
     * @return bool 文件是否存在
     */
    public function fileExists($path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    /**
     * 检查文件是否缺失
     *
     * 本方法通过调用 fileExists 方法来判断文件是否存在,返回文件缺失的状态
     * 如果文件不存在,则返回 true,表示文件缺失;如果文件存在,则返回 false,表示文件不缺失
     *
     * @param string $path 文件的路径
     * @return bool 文件是否缺失的布尔值
     */
    public function fileMissing($path): bool
    {
        return !$this->fileExists($path);
    }

    /**
     * 检查指定路径的目录是否存在
     * 
     * @param string $path 要检查的目录路径
     * @return mixed|bool 目录存在返回true,否则返回false
     */
    public function directoryExists($path)
    {
        // 调用FileSystem类的directoryExists方法检查目录是否存在
        return $this->filesystem->directoryExists($path);
    }

    /**
     * 检查目录是否缺失
     * 
     * 本函数通过调用 directoryExists 函数来检查指定路径下的目录是否存在
     * 如果目录存在,则返回 false,表示目录不缺失
     * 如果目录不存在,则返回 true,表示目录缺失
     * 
     * @param string $path 目录路径
     * @return mixed|bool 目录是否缺失
     */
    public function directoryMissing($path)
    {
        return !$this->directoryExists($path);
    }

    /**
     * 读取指定路径的文件内容
     *
     * 本方法尝试读取文件系统中指定路径的文件内容
     * 如果文件读取遇到问题,并且当前配置为抛出异常,则会抛出 UnableToReadFile 异常
     *
     * @param string $path 要读取的文件路径
     * @return mixed|string|null 文件内容字符串,如果读取成功;否则返回 null
     * @throws UnableToReadFile 如果文件无法读取,并且当前配置为抛出异常
     */
    public function get($path)
    {
        try {
            // 尝试从文件系统读取指定路径的文件内容
            return $this->filesystem->read($path);
        } catch (UnableToReadFile $e) {
            // 如果配置为抛出异常,则抛出 UnableToReadFile 异常
            throw_if($this->throwsExceptions(), $e);
        }
    }

    /**
     * 生成一个流式响应,用于下载或显示指定路径的文件
     *
     * @param string $path 文件的路径
     * @param string|null $name 可选,文件的名称,默认为null
     * @param array $headers HTTP头信息数组,默认为空数组
     * @param mixed|string|null $disposition 内容处置类型,可以是'inline'或'attachment',默认为'inline'
     * @return \Symfony\Component\HttpFoundation\StreamedResponse 返回一个流式响应对象
     */
    public function response($path, $name = null, array $headers = [], $disposition = 'inline')
    {
        // 创建一个新的流式响应对象
        $response = new StreamedResponse;

        // 如果头信息中没有Content-Type,则尝试根据文件路径获取MIME类型并设置
        if (!array_key_exists('Content-Type', $headers)) {
            $headers['Content-Type'] = $this->mimeType($path);
        }

        // 如果头信息中没有Content-Length,则尝试根据文件路径获取文件大小并设置
        if (!array_key_exists('Content-Length', $headers)) {
            $headers['Content-Length'] = $this->size($path);
        }

        // 如果头信息中没有Content-Disposition,则根据文件路径和名称生成Content-Disposition头信息
        if (!array_key_exists('Content-Disposition', $headers)) {
            $filename = $name ?? basename($path);

            $disposition = $response->headers->makeDisposition(
                $disposition,
                $filename,
                $this->fallbackName($filename)
            );

            $headers['Content-Disposition'] = $disposition;
        }

        // 替换响应的头信息
        $response->headers->replace($headers);

        // 设置响应的回调函数,该函数负责读取文件内容并传递给客户端
        $response->setCallback(function () use ($path) {
            $stream = $this->readStream($path);
            fpassthru($stream);
            fclose($stream);
        });
        // 返回流式响应对象
        return $response;
    }

    /**
     * 提供文件下载功能
     * 
     * 该方法主要用于让用户下载指定的文件
     * 它通过流的方式响应文件下载请求,可以有效地减少内存使用,特别适用于大文件的下载
     * 支持自定义下载文件的显示名称及额外的HTTP头信息
     * 
     * @param string $path 文件的路径,可以是本地路径或者一个可访问的URL
     * @param string|null $name 可选参数,指定下载时文件显示的名称,默认为null,即使用原文件名
     * @param mixed|array $headers 可选参数,一个包含HTTP头信息的数组,用于设置额外的响应头,默认为空数组
     * 
     * @return \Symfony\Component\HttpFoundation\StreamedResponse 返回一个StreamedResponse对象,该对象负责实际的文件流传输
     */
    public function download($path, $name = null, array $headers = [])
    {
        return $this->response($path, $name, $headers, 'attachment');
    }

    /**
     * 处理名称 fallbackName 方法
     * 将给定的名称转换为不包含任何 ASCII 转义字符的字符串
     * 主要用于清理和转换名称字段以备后续使用
     *
     * @param string $name 需要处理的名称
     * @return mixed|string 处理后的不含 ASCII 转义字符的名称
     */
    protected function fallbackName($name)
    {
        return str_replace('%', '', ASCII::to_ascii($name, 'en'));
    }

    /**
     * 根据给定的路径,获取文件或目录的可见性(公开或私有)
     *
     * @param string $path 文件或目录的路径
     * @return mixed|string 返回'public'表示公开,返回'private'表示私有
     */
    public function getVisibility($path)
    {
        // 检查路径的可见性,如果为PUBLIC,则返回'public'
        if ($this->filesystem->visibility($path) == Visibility::PUBLIC) {
            return 'public';
        }
        // 否则,返回'private'
        return 'private';
    }

    /**
     * 设置文件系统的文件或目录的可见性
     *
     * @param string $path 文件或目录的路径
     * @param string $visibility 新的可见性设置
     * @return mixed|bool 成功设置可见性返回true,失败返回false
     *
     * 该方法尝试将给定路径的可见性设置为指定的值如果设置过程中发生无法设置可见性的错误,
     * 并且该类被配置为抛出异常,则会抛出此异常;否则,当发生错误时返回false
     */
    public function setVisibility($path, $visibility)
    {
        try {
            // 尝试设置可见性
            $this->filesystem->setVisibility($path, $visibility);
        } catch (UnableToSetVisibility $e) {
            // 如果配置为抛出异常,则抛出捕获的异常;否则返回false
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        // 如果没有发生异常,则成功设置可见性,返回true
        return true;
    }

    /**
     * 在文件的开头添加内容
     *
     * 该方法尝试在指定文件的开头添加给定的数据.如果文件不存在,它将创建并写入数据
     * 如果文件存在,它将保留原有内容,并在新数据之后添加分隔符
     *
     * @param string $path 文件路径 该参数指定了要操作的文件位置
     * @param string $data 要写入的数据 该参数指定了要在文件中添加的内容
     * @param string $separator 分隔符 默认为换行符 可选参数,用于分隔原文件内容和新添加的内容
     * @return mixed|bool 返回操作是否成功
     */
    public function prepend($path, $data, $separator = PHP_EOL)
    {
        // 检查文件是否存在
        if ($this->fileExists($path)) {
            // 如果文件存在,将新数据添加到文件开头,并保留原内容
            return $this->put($path, $data . $separator . $this->get($path));
        }
        // 如果文件不存在,直接写入新数据
        return $this->put($path, $data);
    }

    /**
     * 向文件追加数据
     *
     * 如果文件存在,则在文件末尾追加指定的数据
     * 如果文件不存在,则创建文件并写入指定的数据
     * 使用指定的分隔符来区分追加的数据和文件原有内容,默认使用系统换行符
     *
     * @param string $path 文件路径,指定要操作的文件
     * @param string $data 要写入或追加的数据
     * @param string $separator 分隔符,用于区分文件原有内容和追加的内容,默认为系统换行符
     * @return mixed|bool 操作是否成功
     */
    public function append($path, $data, $separator = PHP_EOL)
    {
        // 检查文件是否存在
        if ($this->fileExists($path)) {
            // 文件存在时,追加数据
            return $this->put($path, $this->get($path) . $separator . $data);
        }
        // 文件不存在时,直接写入数据
        return $this->put($path, $data);
    }

    /**
     * 删除一个或多个文件或目录
     * 
     * 该方法接受单个文件路径或多个路径参数,路径可以是字符串或数组形式
     * 它会尝试删除提供的所有路径项,如果任何一项无法删除,则会记录错误并返回false
     * 
     * @param string|array $paths 要删除的文件或目录的路径,可以是单个路径字符串或路径数组
     * @return mixed|bool 删除操作是否成功,所有指定的文件或目录均成功删除则返回true,否则返回false
     */
    public function delete($paths)
    {
        // 将路径参数转换为数组,以便统一处理单个路径和多个路径的情况
        $paths = is_array($paths) ? $paths : func_get_args();

        // 初始化成功标志,假设所有路径都将成功删除
        $success = true;

        // 遍历所有路径尝试删除
        foreach ($paths as $path) {
            try {
                // 尝试删除文件或目录,如果无法删除将抛出异常
                $this->filesystem->delete($path);
            } catch (UnableToDeleteFile $e) {
                // 如果配置为抛出异常,则对捕获的异常重新抛出
                throw_if($this->throwsExceptions(), $e);
                // 设置成功标志为false,表示至少有一个路径未能成功删除
                $success = false;
            }
        }
        // 返回删除操作的成功与否
        return $success;
    }

    /**
     * 复制文件或目录从一个路径到另一个路径
     *
     * @param string $from 源文件或目录的路径
     * @param string $to 目标文件或目录的路径
     * @return mixed|bool 返回true表示复制成功,否则返回false
     */
    public function copy($from, $to)
    {
        try {
            // 使用Flysystem的copy方法来复制文件或目录
            $this->filesystem->copy($from, $to);
        } catch (UnableToCopyFile $e) {
            // 当捕获到无法复制文件的异常时,根据配置决定是否重新抛出异常或返回false
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        return true;
    }

    /**
     * 将文件或目录从一个位置移动到另一个位置
     *
     * @param string $from 移动前的路径
     * @param string $to 移动后的路径
     * @return mixed|bool 移动成功返回true,失败返回false
     */
    public function move($from, $to)
    {
        try {
            // 调用filesystem实例的move方法尝试移动文件或目录
            $this->filesystem->move($from, $to);
        } catch (UnableToMoveFile $e) {
            // 如果配置为抛出异常,则抛出捕获的异常;否则,返回false表示移动失败
            throw_if($this->throwsExceptions(), $e);
            return false;
        }

        // 文件或目录移动成功,返回true
        return true;
    }

    /**
     * 获取文件大小
     *
     * 此方法接受一个文件路径作为参数,并返回该文件的大小(以字节为单位)
     * 如果文件不存在或其他文件系统错误发生,则会抛出FilesystemException异常
     *
     * @param string $path 文件路径
     * @return mixed|int 文件大小(字节)
     * @throws FilesystemException 如果文件不存在或读取文件大小时发生错误
     */
    public function size($path)
    {
        return $this->filesystem->fileSize($path);
    }

    /**
     * 获取指定路径文件的MIME类型
     * 
     * 本函数尝试获取指定路径文件的MIME类型,如果无法获取,则根据配置决定是否抛出异常
     * MIME类型是表示文件格式的一种标准,例如:文本文件的MIME类型为"text/plain",图片文件可能有"image/jpeg"、"image/png"等MIME类型
     * 
     * @param string $path 文件路径,可以是本地路径或者网络路径,具体取决于文件系统实现
     * @return mixed|string|bool 成功时返回文件的MIME类型字符串,失败时返回false
     * 
     * 使用场景包括但不限于
     * - 需要根据文件类型执行不同操作时
     * - 验证上传文件类型是否符合预期时
     * 
     * 注意:当无法获取到MIME类型且配置为抛出异常模式时,会抛出UnableToRetrieveMetadata异常
     */
    public function mimeType($path)
    {
        try {
            // 调用文件系统服务的mimeType方法尝试获取MIME类型
            return $this->filesystem->mimeType($path);
        } catch (UnableToRetrieveMetadata $e) {
            // 如果配置为抛出异常模式,针对无法获取元数据的情况,进行异常抛出
            throw_if($this->throwsExceptions(), $e);
        }

        // 如果尝试获取MIME类型失败且没有配置为抛出异常,返回false
        return false;
    }

    /**
     * 获取文件或目录的最后修改时间
     *
     * 此方法主要用于获取指定路径文件或目录的最后修改时间
     * 它调用了底层文件系统提供的功能来获取时间戳
     *
     * @param string $path 需要查询的文件或目录的路径
     * @return mixed|int 返回文件或目录的最后修改时间,以时间戳形式表示
     */
    public function lastModified($path): int
    {
        return $this->filesystem->lastModified($path);
    }

    /**
     * 通过流读取指定路径的文件内容
     *
     * 此方法尝试打开并返回一个文件的读取流
     * 如果文件无法读取且当前配置为抛出异常时,将抛出一个异常
     * 如果文件正常读取,则返回一个流资源,该资源可用于读取文件内容
     *
     * @param string $path 要读取的文件路径
     *
     * @return mixed|resource|null 成功时返回一个流资源,读取失败时返回null
     *
     * {@inheritdoc}
     * @throws UnableToReadFile 如果文件无法读取且当前配置为抛出异常时
     */
    public function readStream($path)
    {
        try {
            return $this->filesystem->readStream($path);
        } catch (UnableToReadFile $e) {
            throw_if($this->throwsExceptions(), $e);
        }
    }

    /**
     * 使用流写入文件
     *
     * 该方法尝试将给定的流资源写入到指定的文件路径
     * 如果文件系统中已经存在相同路径的文件,写入操作将覆盖现有文件
     * 方法允许通过选项参数自定义写入行为和访问控制
     *
     * @param string $path 文件路径,包括文件名和可选的文件系统路径
     * @param resource $resource 流资源,用于读取并写入到文件系统
     * @param array $options 可选参数数组,用于控制写入操作.可能包括访问控制规则等
     *
     * @return mixed|bool 成功写入返回true,发生错误返回false
     *
     * {@inheritdoc}
     * @throws UnableToWriteFile 当写入操作因为文件系统权限或资源问题失败时抛出
     * @throws UnableToSetVisibility 当写入文件后尝试设置文件访问权限失败时抛出
     *
     * 注意:该方法内部处理所有潜在的文件写入和权限设置异常,确保在发生错误时能够优雅地回退或处理
     */
    public function writeStream($path, $resource, array $options = [])
    {
        try {
            // 尝试将流资源写入到文件系统指定路径
            $this->filesystem->writeStream($path, $resource, $options);
        } catch (UnableToWriteFile | UnableToSetVisibility $e) {
            // 根据配置决定是否抛出捕获的异常
            throw_if($this->throwsExceptions(), $e);
            // 如果不抛出异常,则返回false表示操作失败
            return false;
        }
        // 如果没有异常发生,表示操作成功,返回true
        return true;
    }

    /**
     * 获取本地URL
     * 
     * 此方法用于根据配置中的URL和给定的路径生成完整的URL
     * 如果配置中没有指定URL,则返回原始路径
     * 主要用于根据当前的配置信息,结合外部给定的路径,生成访问资源所需的完整URL这对于在有统一配置的情况下,
     * 根据路径动态生成访问地址非常有用如果配置中已经提供了URL,那么会将该URL与给定的路径拼接起来;否则,
     * 将直接返回给定的路径
     * 
     * @param string $path 要拼接到URL的路径这部分路径将被加到配置中给出的基础URL之后
     * 
     * @return mixed|string 完整的URL如果配置中没有提供URL,则返回原始路径
     */
    protected function getLocalUrl($path)
    {
        // 检查配置中是否提供了URL,如果提供了,则使用concatPathToUrl方法拼接配置中的URL和给定的路径
        if (isset($this->config['url'])) {
            return $this->concatPathToUrl($this->config['url'], $path);
        }

        // 如果配置中没有提供URL,直接返回原始路径
        return $path;
    }

    /**
     * 根据指定路径获取资源的URL
     *
     * 本方法尝试按照以下顺序获取URL
     * 1. 如果适配器对象($adapter)具有getUrl方法,则调用该方法
     * 2. 如果文件系统对象($filesystem)具有getUrl方法,则调用该方法
     * 3. 如果适配器是SftpAdapter或FtpAdapter的实例,则调用内部的getFtpUrl方法
     * 4. 如果适配器是LocalFilesystemAdapter的实例,则调用内部的getLocalUrl方法
     * 5. 如果本类具有getUrl方法,则调用该方法
     * 如果以上所有尝试均失败,则抛出RuntimeException
     *
     * @param string $path 资源路径
     * @return mixed|string 资源的URL
     * @throws \RuntimeException 如果无法获取URL
     */
    public function url(string $path): string
    {
        $adapter = $this->adapter;

        if (method_exists($adapter, 'getUrl')) {
            return $adapter->getUrl($path);
        } elseif (method_exists($this->filesystem, 'getUrl')) {
            return $this->filesystem->getUrl($path);
        } elseif ($adapter instanceof SftpAdapter || $adapter instanceof FtpAdapter) {
            return $this->getFtpUrl($path);
        } elseif ($adapter instanceof LocalFilesystemAdapter) {
            return $this->getLocalUrl($path);
        } elseif (method_exists($this, 'getUrl')) {
            return $this->getUrl($path);
        } else {
            throw new \RuntimeException('This driver does not support retrieving URLs.');
        }
    }

    /**
     * 获取FTP操作的URL
     * 
     * 该方法用于根据给定的路径和配置中的URL拼接出FTP操作的完整URL如果配置中没有指定URL,则直接返回给定的路径
     *
     * @param string $path FTP操作的路径
     * @return mixed|string FTP操作的完整URL或者给定的路径
     */
    protected function getFtpUrl($path)
    {
        // 初始化结果为给定的路径
        $result = $path;
        // 检查配置中是否设置了URL,如果设置了,则使用拼接方法将URL和路径拼接起来
        if (isset($this->config['url'])) {
            $result = $this->concatPathToUrl($this->config['url'], $path);
        }
        // 返回最终的URL
        return $result;
    }

    /**
     * 替换基础URL
     *
     * 该方法用于解析给定的URL并替换URI对象的基础URL部分
     * 它会保留原始URI的路径和查询参数,仅替换协议、主机和端口
     *
     * @param object $uri URI对象,其基础URL将被替换
     * @param string $url 新的基础URL,用于替换URI对象中的基础URL
     *
     * @return object 返回一个新的URI对象,基础URL部分已被替换
     */
    protected function replaceBaseUrl($uri, $url)
    {
        // 解析新的基础URL
        $parsed = parse_url($url);

        // 返回一个新的URI对象,使用解析后的URL信息替换原有的协议、主机和端口
        // 如果解析的URL中没有端口信息,则使用null替换
        return $uri
            ->withScheme($parsed['scheme'])
            ->withHost($parsed['host'])
            ->withPort($parsed['port'] ?? null);
    }

    /**
     * 获取当前实例所使用的Flysystem文件系统操作器
     *
     * 该方法返回一个Flysystem的FilesystemOperator实例,以便可以对文件系统进行操作
     *
     * @return \League\Flysystem\FilesystemOperator 文件系统操作器实例
     */
    public function getDriver()
    {
        return $this->filesystem;
    }

    /**
     * 获取当前实例所使用的文件系统适配器
     *
     * 该方法返回一个 \League\Flysystem\FilesystemAdapter 实例,该实例封装了实际进行文件操作的核心逻辑
     * 通过这个适配器,可以进行文件的读写、删除、复制等操作,而不需要关心这些操作是在本地文件系统、Amazon S3或是其他存储系统上执行
     *
     * @return \League\Flysystem\FilesystemAdapter 返回文件系统适配器实例
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * 保存文件
     * 
     * 此方法用于将文件保存到指定的路径
     * 它支持自定义文件名规则和传递额外的选项
     * 文件名规则可以是一个字符串、null、或者一个Closure对象,用于动态生成文件名
     * 
     * @param string $path 路径 保存文件的目录路径
     * @param File|string $file 文件 要保存的文件,可以是一个文件路径字符串或File对象
     * @param null|string|\Closure $rule 文件名规则 文件名的生成规则,可为空,默认为文件的哈希值
     * @param array $options 参数 额外的保存选项,例如存储类型或权限设置
     * @return mixed|bool|string 返回保存文件的结果,成功时返回文件名,失败时返回false
     */
    public function putFile(string $path, $file, $rule = null, array $options = [])
    {
        // 如果$file是字符串,则创建File对象,否则使用已有的File对象
        $file = is_string($file) ? new File($file) : $file;

        // 调用putFileAs方法,使用文件的哈希名作为文件名保存文件
        return $this->putFileAs($path, $file, $file->hashName($rule), $options);
    }

    /**
     * 指定文件名保存文件
     * 
     * 该方法用于将上传的文件按照指定的路径和文件名进行保存
     * 它首先打开文件以读取模式,然后使用该文件流进行上传保存操作
     * 保存成功后,会关闭打开的文件流,并返回保存的文件路径
     * 
     * @param string $path 路径 这是指定保存文件的目录路径
     * @param File $file 文件 这是上传的文件对象
     * @param string $name 文件名 这是指定的保存文件名
     * @param array $options 参数 这是可选的额外参数,用于特定的保存需求
     * @return mixed|bool|string 返回保存的文件路径或失败时返回false
     */
    public function putFileAs(string $path, File $file, string $name, array $options = [])
    {
        // 打开文件以读取模式
        $stream = fopen($file->getRealPath(), 'r');
        // 构造完整的保存路径
        $path = trim($path . '/' . $name, '/');

        // 尝试保存文件
        $result = $this->put($path, $stream, $options);

        // 如果文件流仍然打开,则关闭它
        if (is_resource($stream)) {
            fclose($stream);
        }

        // 根据保存结果返回路径或false
        return $result ? $path : false;
    }

    /**
     * 将数据写入指定路径的文件中
     *
     * 该方法支持多种类型的数据写入,包括字符串、资源、文件对象以及流接口对象
     * 写入操作的选项可以通过第三个参数进行设置,包括可见性等
     *
     * @param string $path 要写入的文件路径
     * @param mixed $contents 要写入的文件内容,可以是字符串、资源、实现了FileInterface的实例或StreamInterface的实例
     * @param mixed $options 写入操作的选项,可以是字符串(表示文件可见性)或数组类型
     *                       如果是字符串,则会被转换成一个包含'visibility'键的数组;
     *                       如果是数组或可转换为数组,则会直接使用
     *
     * @return mixed|bool 表示写入操作是否成功.如果操作中抛出了UnableToWriteFile或UnableToSetVisibility异常且$throwsExceptions为true,则会抛出异常
     * @throws UnableToWriteFile 如果写入文件时发生错误且$throwsExceptions为true
     * @throws UnableToSetVisibility 如果设置文件可见性时发生错误且$throwsExceptions为true
     */
    public function put($path, $contents, $options = [])
    {
        // 确保$options参数要么是字符串,要么是数组
        $options = is_string($options) ? ['visibility' => $options] : (array) $options;

        // 如果$contents是一个File或UploadedFile实例,则调用putFile方法处理
        if ($contents instanceof File || $contents instanceof UploadedFile) {
            return $this->putFile($path, $contents, $options);
        }

        try {
            // 如果$contents实现了StreamInterface接口,则使用writeStream方法写入数据
            if ($contents instanceof StreamInterface) {
                $this->writeStream($path, $contents->detach(), $options);
                return true;
            }

            // 根据$contents的类型选择合适的写入方法
            $res = is_resource($contents)
                ? $this->writeStream($path, $contents, $options) // 如果$contents是一个资源
                : $this->write($path, $contents, $options);       // 否则,直接写入内容
            // 如果写入失败,则返回false
            if ($res === false) {
                return false;
            }
        } catch (UnableToWriteFile | UnableToSetVisibility $e) {
            // 根据配置决定是否抛出异常
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        return true;
    }

    /**
     * 获取指定目录中的所有文件列表
     * 
     * 该方法使用递归方式获取目录中的文件
     * 如果指定了目录,则返回该目录及其子目录中的所有文件;如果未指定目录,则返回根目录及其子目录中的所有文件
     * 返回的列表按路径排序
     * 
     * @param string|null $directory 可选参数,指定要获取文件的目录路径.如果未提供,将从根目录开始
     * @param bool $recursive 指定是否递归获取目录中的文件,默认为 false,即不递归
     * @return mixed|array 返回包含所有文件路径的数组
     */
    public function files($directory = null, $recursive = false)
    {
        // 使用文件系统对象的listContents方法获取目录内容,如果目录参数为空,使用空字符串作为默认值
        // 然后过滤出文件,忽略目录
        // 对结果按路径进行排序,再提取每个文件的路径
        // 最后将结果转换为数组返回
        return $this->filesystem->listContents($directory ?? '', $recursive)
            ->filter(function (StorageAttributes $attributes) {
                return $attributes->isFile();
            })
            ->sortByPath()
            ->map(function (StorageAttributes $attributes) {
                return $attributes->path();
            })
            ->toArray();
    }

    /**
     * 获取指定目录下的所有文件
     * 
     * 本方法是文件操作的一个封装,旨在简化获取目录下所有文件的操作
     * 它接受一个可选的目录参数,如果未提供目录,将默认操作当前目录
     * 通过递归方式确保能够获取到指定目录下的所有文件,无论嵌套多深
     * 
     * @param string|null $directory 可选参数,指定要获取文件的目录路径如果未提供,默认为当前目录
     * @return mixed|array 返回包含目录下所有文件的数组
     */
    public function allFiles($directory = null)
    {
        return $this->files($directory, true);
    }

    /**
     * 获取目录中的所有子目录
     * 
     * 该方法用于列出指定目录(或当前目录,如果未提供目录参数)中的所有子目录
     * 如果设置了递归参数,则会递归地列出所有子目录
     * 
     * @param string|null $directory 要列出其子目录的目录路径.如果为null,则表示当前目录
     * @param bool $recursive 是否递归地列出子目录.默认为false
     * @return mixed|array 包含目录路径的数组
     */
    public function directories($directory = null, $recursive = false)
    {
        // 使用文件系统实例的listContents方法获取目录内容,如果(directory参数为null,则使用空字符串)
        // 然后过滤出其中的目录,并映射为目录路径数组,最后转换为原生数组
        return $this->filesystem->listContents($directory ?? '', $recursive)
            ->filter(function (StorageAttributes $attributes) {
                return $attributes->isDir();
            })
            ->map(function (StorageAttributes $attributes) {
                return $attributes->path();
            })
            ->toArray();
    }

    /**
     * 获取所有目录
     * 
     * 此方法用于获取指定目录下的所有子目录如果未指定目录,则使用默认目录
     * 
     * @param string|null $directory 可选参数,指定要搜索的目录
     * @return mixed|array 返回一个包含所有子目录的数组
     */
    public function allDirectories($directory = null)
    {
        // 调用私有方法directories,并将$directory参数和一个固定的true值传递给它
        return $this->directories($directory, true);
    }

    /**
     * 创建目录
     *
     * 该方法尝试在指定的路径创建一个目录
     * 如果由于某些原因(如权限不足)导致目录无法创建,或者无法设置目录的可见性,将会捕获异常
     * 在某些情况下,会选择抛出异常或返回false
     * 
     * @param string $path 需要创建的目录的路径
     * @return bool 如果目录创建成功或者异常被捕获并且不抛出,则返回true;否则返回false
     */
    public function makeDirectory($path)
    {
        try {
            // 尝试创建目录,这里可能会抛出异常
            $this->filesystem->createDirectory($path);
        } catch (UnableToCreateDirectory | UnableToSetVisibility $e) {
            // 当捕获到创建目录或设置目录可见性异常时,根据配置决定是否重新抛出异常,否则返回false
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        // 目录创建成功或异常被捕获后返回true
        return true;
    }

    /**
     * 删除一个目录及其内容
     *
     * 本函数尝试删除指定的目录
     * 如果目录删除成功,返回true;如果删除过程失败,且本函数被配置为抛出异常,则将异常抛出;否则返回false
     *
     * @param string $directory 待删除的目录路径
     * @return bool 目录删除成功返回true,否则返回false
     * @throws UnableToDeleteDirectory 当目录无法删除且本函数被配置为抛出异常时
     */
    public function deleteDirectory($directory)
    {
        try {
            // 调用filesystem实例的deleteDirectory方法删除目录
            $this->filesystem->deleteDirectory($directory);
        } catch (UnableToDeleteDirectory $e) {
            // 如果配置为抛出异常且当前异常是UnableToDeleteDirectory类型,则抛出异常
            throw_if($this->throwsExceptions(), $e);
            // 如果不抛出异常,则返回false表示删除失败
            return false;
        }
        // 目录删除成功,返回true
        return true;
    }

    /**
     * 判断是否抛出异常
     *
     * 该方法用于确定当前配置是否允许抛出异常
     * 它通过检查配置数组中的'throw' 键来实现
     * 如果该键存在且值为真,则返回 true,表示允许抛出异常;否则,返回 false,表示不允许抛出异常
     *
     * @return bool 返回一个布尔值,true 表示允许抛出异常,false 表示不允许抛出异常
     */
    protected function throwsExceptions(): bool
    {
        // 使用 ?? 运算符提供一个默认值(false)如果配置中未设置 'throw' 键
        return (bool) ($this->config['throw'] ?? false);
    }

    /**
     * 动态调用未定义的方法
     *
     * 该方法允许通过动态方法调用的方式访问Filesystem类中的方法
     * 它会捕获传入的方法名和参数,并将调用委托给Filesystem类的相应方法
     *
     * @param string $method 动态调用的方法名
     * @param array $parameters 调用方法时传递的参数数组
     *
     * @return mixed 返回Filesystem类中对应方法的执行结果
     */
    public function __call($method, $parameters)
    {
        return $this->filesystem->$method(...$parameters);
    }
}
