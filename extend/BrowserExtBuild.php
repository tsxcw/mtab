<?php

class BrowserExtBuild
{
    protected $originSource = "";
    public $buildDir = "";
    public $zipDir = "";
    public $zipName = "";
    private $info = [];
    public $manifest = array(
        'name' => '',
        'description' => '',
        'version' => '',
        'manifest_version' => 3,
        'icons' => array(
            '64' => 'icon/64.png',
            '128' => 'icon/128.png',
            '192' => 'icon/192.png'
        ),
        'externally_connectable' => array(
            'matches' => array(
                '*://go.mtab.cc/*'
            )
        ),
        'background' => array(
            'service_worker' => 'src/background.js'
        ),
        'permissions' => array(
            'background',
            'cookies',
            'bookmarks',
            'favicon',
            'search'
        ),
        'action' => array(
            'default_icon' => 'icon/64.png',
            'default_title' => ''
        ),
        'host_permissions' => array(
            '*://go.mtab.cc/*'
        ),
        'chrome_url_overrides' => array(
            'newtab' => 'dist/index.html'
        )
    );

    function __construct($info)
    {
        $this->info = $info;
        $this->originSource = root_path('extend/browserExt');
        $this->buildDir = runtime_path('browserExt');
        $this->zipName = "browserExt.zip";
        $this->zipDir = public_path() . $this->zipName;
    }

    function runBuild()
    {
        if (is_dir($this->buildDir)) {
            $this->deleteDirectory($this->buildDir);
        }
        $this->copyDir($this->originSource, $this->buildDir);
        $this->copyDir(public_path() . "dist/", $this->buildDir . "/dist/");
        $this->delZip();
        $this->copyIcon();
        $this->renderManifest();
        $this->renderIndex();
        $this->renderInitJavascript();
        $this->createZipFromDir($this->buildDir, $this->zipDir);
        if (is_dir($this->buildDir)) {
            $this->deleteDirectory($this->buildDir);
        }
        return true;
    }

    function renderIndex()
    {
        $file = $this->buildDir . "dist/index.html";
        $f = file_get_contents($file);
        $option = [];
        $option['title'] = $this->info['ext_name'];
        $option['customHead'] = '<script src="../src/init.js"></script>';
        $option['description'] = $this->info['ext_description'];
        $option['favicon'] = "/icon/64.png";
        $option['keywords'] = '';
        $option['version'] = $this->info['ext_version'];
        $content = \think\facade\View::display($f, $option);
        file_put_contents($file, $content);
    }

    function renderInitJavascript()
    {
        $file = $this->buildDir . 'src/init.js';
        $f = file_get_contents($file);
        $host = explode(':', $this->info['ext_domain'])[0];
        $f = preg_replace("/extDomain/", $host, $f);
        $f = preg_replace('/extUrl/', $this->info['ext_protocol'] . "://" . $this->info['ext_domain'], $f);
        file_put_contents($file, $f);
    }

    function renderManifest()
    {
        $host = explode(":", $this->info['ext_domain'])[0];
        $this->manifest['version'] = $this->info['ext_version'];
        $this->manifest['name'] = $this->info['ext_name'];
        $this->manifest['description'] = $this->info['ext_description'];
        $this->manifest['action']['default_title'] = $this->info['ext_name'];
        $this->manifest['externally_connectable']['matches'] = ["*://{$host}/*"];
        $this->manifest['host_permissions'] = ["*://{$host}/*", '*://*.baidu.com/*', '*://*.qq.com/*'];
        file_put_contents(joinPath($this->buildDir, "manifest.json"), json_encode($this->manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    //处理logo问题
    function copyIcon()
    {
        if (!is_dir(joinPath($this->buildDir, 'icon'))) {
            mkdir(joinPath($this->buildDir, 'icon'));
        }
        if(!file_exists(joinPath(public_path(), $this->info['ext_logo_64']))){
            abort(0, '64px尺寸logo文件不存在');
        }
        copy(joinPath(public_path(), $this->info['ext_logo_64']), joinPath($this->buildDir, "icon/64.png"));
        if(!file_exists(joinPath(public_path(), $this->info['ext_logo_128']))){
            abort(0, '128px尺寸logo文件不存在');
        }
        copy(joinPath(public_path(), $this->info['ext_logo_128']), joinPath($this->buildDir, "icon/128.png"));
        if(!file_exists(joinPath(public_path(), $this->info['ext_logo_192']))){
            abort(0, '192px尺寸logo文件不存在');
        }
        copy(joinPath(public_path(), $this->info['ext_logo_192']), joinPath($this->buildDir, "icon/192.png"));
    }

    //删除升级包
    function delZip()
    {
        if (file_exists($this->zipDir)) {
            unlink($this->zipDir);
        }
    }

    function createZipFromDir($source_dir, $output_file_path)
    {
        $zip = new ZipArchive();
        if ($zip->open($output_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // 递归地添加目录中的文件和子目录到压缩包
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_dir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $name => $file) {
                // 跳过 "." 和 ".." 目录
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = mb_substr($filePath, mb_strlen(dirname($source_dir)) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            // 关闭压缩包
            $zip->close();
            return true;
        } else {
            abort(0, '无法创建压缩文件');
        }
    }

    // 递归复制目录及其内容
    function copyDir($source, $dest)
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }
        $files = scandir($source);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $src = $source . '/' . $file;
                $dst = $dest . '/' . $file;
                if (is_dir($src)) {
                    $this->copyDir($src, $dst);
                } else {
                    copy($src, $dst);
                }
            }
        }
    }

    //递归删除目录
    function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir("$dir/$file")) {
                    $this->deleteDirectory("$dir/$file");
                } else {
                    unlink("$dir/$file");
                }
            }
        }
        rmdir($dir);
    }
}