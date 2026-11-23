<?php
declare(strict_types=1);

namespace app\command;

use DirectoryIterator;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use ZipArchive;

class backup extends Command
{
    private array $ignore = [];
    private string $sqlName = "";
    private string $zipName = "";
    private string $date = "";

    protected function configure(): void
    {
        $this->setName('backup')
            ->setDescription('系统备份与恢复工具')
            ->addArgument('name', Argument::OPTIONAL, '直接执行: database(数据库) / app(程序文件)');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->output = $output;
        $this->initIgnore();
        $this->date = date('YmdHis');
        $this->sqlName = "mTabBackupMysql-{$this->date}.sql";
        $this->zipName = "mTabBackupApp-{$this->date}.zip";

        $backupDir = root_path() . 'backup';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        // 兼容旧版直接传参模式
        $name = $input->getArgument('name');
        if ($name === 'database') {
            $this->mysqlDump();
            return;
        }
        if ($name === 'app') {
            $this->fileZip();
            return;
        }

        // 交互模式
        $this->interactive();
    }

    // ==================== 交互主流程 ====================

    private function interactive(): void
    {
        $this->printBanner();
        $action = $this->choice('请选择操作', [
            '1' => '备份数据',
            '2' => '恢复数据',
            '3' => '查看备份文件',
            '4' => '删除备份文件',
            '0' => '退出',
        ]);

        switch ($action) {
            case '1':
                $this->doBackup();
                break;
            case '2':
                $this->doRestore();
                break;
            case '3':
                $this->doList();
                break;
            case '4':
                $this->doDelete();
                break;
            case '0':
                $this->output->writeln('');
                $this->output->writeln('  <info>已退出，再见！</info>');
                $this->output->writeln('');
                break;
        }
    }

    // ==================== 备份 ====================

    private function doBackup(): void
    {
        $type = $this->choice('请选择备份类型', [
            '1' => '仅数据库',
            '2' => '仅程序文件',
            '3' => '全量备份（数据库 + 程序文件）',
            '0' => '返回上级',
        ]);

        if ($type === '0') {
            $this->interactive();
            return;
        }

        $this->output->writeln('');
        $this->output->writeln('  <comment>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</comment>');

        if ($type === '1' || $type === '3') {
            $this->mysqlDump();
        }
        if ($type === '2' || $type === '3') {
            $this->fileZip();
        }

        $this->output->writeln('');
        $this->output->writeln('  <comment>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</comment>');
        $this->output->writeln('  <info>备份完成！文件保存在 backup/ 目录下</info>');
        $this->pressAnyKey();
        $this->interactive();
    }

    // ==================== 恢复 ====================

    private function doRestore(): void
    {
        $files = $this->getBackupFiles();

        if (empty($files)) {
            $this->output->writeln('');
            $this->output->writeln('  <warning>backup/ 目录下没有找到备份文件</warning>');
            $this->pressAnyKey();
            $this->interactive();
            return;
        }

        // 分类备份文件
        $sqlFiles = [];
        $zipFiles = [];
        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match('/\.sql$/i', $basename)) {
                $sqlFiles[] = $file;
            } elseif (preg_match('/\.zip$/i', $basename)) {
                $zipFiles[] = $file;
            }
        }

        $restoreType = $this->choice('请选择恢复类型', [
            '1' => '恢复数据库' . (empty($sqlFiles) ? ' <comment>(无备份文件)</comment>' : ' <comment>(' . count($sqlFiles) . '个文件)</comment>'),
            '2' => '恢复程序文件' . (empty($zipFiles) ? ' <comment>(无备份文件)</comment>' : ' <comment>(' . count($zipFiles) . '个文件)</comment>'),
            '0' => '返回上级',
        ]);

        if ($restoreType === '0') {
            $this->interactive();
            return;
        }

        if ($restoreType === '1') {
            $this->restoreDatabase($sqlFiles);
        } else {
            $this->restoreFiles($zipFiles);
        }

        $this->pressAnyKey();
        $this->interactive();
    }

    private function restoreDatabase(array $sqlFiles): void
    {
        if (empty($sqlFiles)) {
            $this->output->writeln('');
            $this->output->writeln('  <warning>没有找到数据库备份文件（.sql）</warning>');
            return;
        }

        $selected = $this->choiceFile('选择要恢复的数据库备份', $sqlFiles);
        if ($selected === '__back__') {
            $this->doRestore();
            return;
        }

        $filePath = root_path() . 'backup/' . $selected;
        if (!file_exists($filePath)) {
            $this->output->writeln('  <error>文件不存在: ' . $selected . '</error>');
            return;
        }

        $this->output->writeln('');
        $this->output->writeln('  <warning>╔══════════════════════════════════════╗</warning>');
        $this->output->writeln('  <warning>║  警告：恢复数据库将覆盖当前数据！    ║</warning>');
        $this->output->writeln('  <warning>║  建议先备份当前数据。                ║</warning>');
        $this->output->writeln('  <warning>╚══════════════════════════════════════╝</warning>');

        $confirm = $this->ask('确认恢复？输入 yes 继续', 'no');
        if (strtolower(trim($confirm)) !== 'yes') {
            $this->output->writeln('  <info>已取消恢复。</info>');
            return;
        }

        $this->output->writeln('');
        $this->output->writeln('  <info>开始恢复数据库...</info>');
        $this->output->writeln('');

        try {
            $this->importSql($filePath);
            $this->output->writeln('');
            $this->output->writeln('  <info>数据库恢复完成！</info>');
        } catch (\Exception $e) {
            $this->output->writeln('  <error>恢复失败: ' . $e->getMessage() . '</error>');
        }
    }

    private function restoreFiles(array $zipFiles): void
    {
        if (empty($zipFiles)) {
            $this->output->writeln('');
            $this->output->writeln('  <warning>没有找到程序文件备份（.zip）</warning>');
            return;
        }

        $selected = $this->choiceFile('选择要恢复的程序文件备份', $zipFiles);
        if ($selected === '__back__') {
            $this->doRestore();
            return;
        }

        $filePath = root_path() . 'backup/' . $selected;
        if (!file_exists($filePath)) {
            $this->output->writeln('  <error>文件不存在: ' . $selected . '</error>');
            return;
        }

        $this->output->writeln('');
        $this->output->writeln('  <warning>╔══════════════════════════════════════════════╗</warning>');
        $this->output->writeln('  <warning>║  警告：恢复程序文件将覆盖当前项目中的文件！  ║</warning>');
        $this->output->writeln('  <warning>║  恢复过程不会覆盖 runtime、backup、.git 等目录 ║</warning>');
        $this->output->writeln('  <warning>╚══════════════════════════════════════════════╝</warning>');

        $confirm = $this->ask('确认恢复？输入 yes 继续', 'no');
        if (strtolower(trim($confirm)) !== 'yes') {
            $this->output->writeln('  <info>已取消恢复。</info>');
            return;
        }

        $this->output->writeln('');
        $this->output->writeln('  <info>开始恢复程序文件...</info>');

        try {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $excludeDirs = ['runtime', 'backup', '.git', '.idea', 'web', '.env', 'public/installed.lock'];
                $total = $zip->numFiles;
                $restored = 0;
                $skipped = 0;

                for ($i = 0; $i < $total; $i++) {
                    $name = $zip->getNameIndex($i);

                    // 跳过排除目录下的文件
                    $skip = false;
                    foreach ($excludeDirs as $dir) {
                        if ($name === $dir || strpos($name, $dir . '/') === 0 || strpos($name, $dir . '\\') === 0) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) {
                        $skipped++;
                        continue;
                    }

                    // 跳过目录条目
                    if (substr($name, -1) === '/') {
                        continue;
                    }

                    $targetPath = root_path() . $name;

                    // 确保目标目录存在
                    $dir = dirname($targetPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }

                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        file_put_contents($targetPath, $content);
                        $restored++;
                    }

                    // 进度显示
                    $percent = intval(($i + 1) / $total * 100);
                    echo "\r\033[K  <info>恢复进度: {$percent}% ({$i}/{$total})</info>";
                }

                $zip->close();
                $this->output->writeln('');
                $this->output->writeln("  <info>程序文件恢复完成！恢复 {$restored} 个文件，跳过 {$skipped} 个文件</info>");
            } else {
                $this->output->writeln('  <error>无法打开 zip 文件</error>');
            }
        } catch (\Exception $e) {
            $this->output->writeln('  <error>恢复失败: ' . $e->getMessage() . '</error>');
        }
    }

    // ==================== 查看备份 ====================

    private function doList(): void
    {
        $files = $this->getBackupFiles();

        if (empty($files)) {
            $this->output->writeln('');
            $this->output->writeln('  <warning>backup/ 目录下没有备份文件</warning>');
            $this->pressAnyKey();
            $this->interactive();
            return;
        }

        $this->output->writeln('');
        $this->output->writeln('  <info>╔════════════════════════════════════════════════════════════╗</info>');
        $this->output->writeln('  <info>║                      备份文件列表                          ║</info>');
        $this->output->writeln('  <info>╚════════════════════════════════════════════════════════════╝</info>');
        $this->output->writeln('');

        $index = 1;
        foreach ($files as $file) {
            $basename = basename($file);
            $size = $this->formatSize(filesize($file));
            $time = date('Y-m-d H:i:s', filemtime($file));
            $type = preg_match('/\.sql$/i', $basename) ? '[数据库]' : '[程序文件]';
            $this->output->writeln("  <info>{$index}</info>. {$type} {$basename}");
            $this->output->writeln("       大小: {$size}  |  时间: {$time}");
            $index++;
        }

        $this->output->writeln('');
        $this->pressAnyKey();
        $this->interactive();
    }

    // ==================== 删除备份 ====================

    private function doDelete(): void
    {
        $files = $this->getBackupFiles();

        if (empty($files)) {
            $this->output->writeln('');
            $this->output->writeln('  <warning>backup/ 目录下没有备份文件</warning>');
            $this->pressAnyKey();
            $this->interactive();
            return;
        }

        $this->output->writeln('');
        $this->output->writeln('  <info>请选择要删除的备份文件:</info>');
        $this->output->writeln('');

        $index = 1;
        foreach ($files as $file) {
            $basename = basename($file);
            $size = $this->formatSize(filesize($file));
            $type = preg_match('/\.sql$/i', $basename) ? '[数据库]' : '[程序文件]';
            $this->output->writeln("  <info>[{$index}]</info> {$type} {$basename} ({$size})");
            $index++;
        }

        $this->output->writeln('');
        $this->output->writeln('  <comment>[A]</comment> 删除全部');
        $this->output->writeln('  <comment>[0]</comment> 返回上级');

        $total = count($files);
        $selected = null;

        while (true) {
            $input = $this->ask('请输入选项编号', '');
            $input = trim($input);

            if ($input === '0' || strtolower($input) === '返回上级') {
                $selected = '__back__';
                break;
            }

            if (strtolower($input) === 'a' || $input === '删除全部') {
                $selected = '__all__';
                break;
            }

            $num = (int)$input;
            if ($num >= 1 && $num <= $total) {
                $selected = basename($files[$num - 1]);
                $this->output->writeln("  > {$selected}");
                break;
            }

            $this->output->writeln('  <error>无效选项，请重新输入</error>');
        }

        if ($selected === '__back__') {
            $this->interactive();
            return;
        }

        if ($selected === '__all__') {
            $this->output->writeln('');
            $this->output->writeln('  <warning>即将删除全部 ' . count($files) . ' 个备份文件！</warning>');
            $confirm = $this->ask('确认删除？输入 yes 继续', 'no');
            if (strtolower(trim($confirm)) !== 'yes') {
                $this->output->writeln('  <info>已取消。</info>');
                $this->pressAnyKey();
                $this->interactive();
                return;
            }
            $count = 0;
            foreach ($files as $file) {
                if (unlink($file)) {
                    $count++;
                }
            }
            $this->output->writeln("  <info>已删除 {$count} 个备份文件。</info>");
        } else {
            $filePath = root_path() . 'backup/' . $selected;
            if (file_exists($filePath)) {
                $confirm = $this->ask("确认删除 {$selected}？输入 yes 继续", 'no');
                if (strtolower(trim($confirm)) !== 'yes') {
                    $this->output->writeln('  <info>已取消。</info>');
                } else {
                    unlink($filePath);
                    $this->output->writeln("  <info>已删除: {$selected}</info>");
                }
            } else {
                $this->output->writeln('  <error>文件不存在</error>');
            }
        }

        $this->pressAnyKey();
        $this->interactive();
    }

    // ==================== 数据库导入 ====================

    /**
     * 使用 MySQLImport 库导入 SQL 文件
     * 库内部逐行读取并按分号切分执行，内存占用可控
     * @param string $filePath SQL 文件路径
     * @throws \Exception
     */
    private function importSql(string $filePath): void
    {
        $host     = env("DATABASE.HOSTNAME");
        $user     = env("DATABASE.USERNAME");
        $password = env("DATABASE.PASSWORD");
        $database = env("DATABASE.DATABASE");
        $port     = (int)env("DATABASE.HOSTPORT");

        $conn = new \mysqli($host, $user, $password, $database, $port);
        if ($conn->connect_error) {
            throw new \Exception("数据库连接失败: " . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');

        $this->output->writeln('  <info>开始执行导入...</info>');

        $import = new \MysqlImporter($conn);
        // 进度回调：通过文件字节百分比和已执行语句数显示进度
        $import->onProgress = function (int $count, ?float $percent): void {
            if ($percent !== null) {
                echo "\r\033[K  <info>导入进度: " . (int)$percent . "% ({$count} 条语句)</info>";
            } elseif ($count % 10 === 0) {
                echo "\r\033[K  <info>已执行 {$count} 条语句...</info>";
            }
        };
        $total = $import->load($filePath);
        $conn->close();
        echo "\r\033[K";

        $this->output->writeln("  <info>全部语句执行成功！共 {$total} 条语句</info>");
    }

    // ==================== 数据库导出 ====================

    /**
     * 使用 MySQLDumpUtil 库导出数据库
     * 库内部使用 MYSQLI_USE_RESULT 流式读取，内存占用可控
     */
    private function mysqlDump(): void
    {
        $host     = env("DATABASE.HOSTNAME");
        $user     = env("DATABASE.USERNAME");
        $password = env("DATABASE.PASSWORD");
        $database = env("DATABASE.DATABASE");
        $port     = (int)env("DATABASE.HOSTPORT");

        $conn = new \mysqli($host, $user, $password, $database, $port);
        if ($conn->connect_error) {
            throw new \Exception("数据库连接失败: " . $conn->connect_error);
        }

        $dump = new \MysqlDumper($conn);
        $this->output->writeln('  <info>开始导出数据库...</info>');
        $dump->save(root_path() . "/backup/{$this->sqlName}");
        $conn->close();
        $this->output->writeln("  <info>数据库导出完成: backup/{$this->sqlName}</info>");
    }

    // ==================== 文件打包 ====================

    private function fileZip(): void
    {
        $this->output->writeln('  <info>开始文件备份...</info>');
        $zip = new ZipArchive();
        $zipPath = root_path() . "backup/{$this->zipName}";
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->addFolderToZip($zip, root_path());
        $zip->close();
        $this->output->writeln("  <info>文件备份完成: backup/{$this->zipName}</info>");
    }

    private function scan(string $path): array
    {
        $files = [];
        if (!is_dir($path)) return $files;

        $dir = new DirectoryIterator($path);
        foreach ($dir as $item) {
            if ($item->isDot()) continue;

            $currentPath = $item->getPathname();

            // 检查是否在忽略列表中
            $shouldIgnore = false;
            foreach ($this->ignore as $ignorePath) {
                if (strpos($currentPath, $ignorePath) === 0) {
                    $shouldIgnore = true;
                    break;
                }
            }
            if ($shouldIgnore) continue;

            if ($item->isDir()) {
                $files = array_merge($files, $this->scan($currentPath));
            } else {
                $files[] = $currentPath;
            }
        }

        return $files;
    }

    private function addFolderToZip(ZipArchive $zip, string $path): void
    {
        $files = $this->scan($path);
        foreach ($files as $file) {
            $relativePath = $this->getRelativePath($file);
            $zip->addFile($file, $relativePath);
        }
    }

    private function getRelativePath(string $basePath): string
    {
        $basePath = realpath($basePath);
        $absolutePath = root_path();
        return preg_replace('/^' . preg_quote($absolutePath, '/') . '/', '', $basePath);
    }

    // ==================== 工具方法 ====================

    private function initIgnore(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $arr = ["/web", '.git', '.idea', '/runtime', '/backup', '/.env', '/public/installed.lock'];
        foreach ($arr as $item) {
            $this->ignore[] = joinPath(root_path(), $item);
        }
    }

    private function getBackupFiles(): array
    {
        $dir = root_path() . 'backup';
        if (!is_dir($dir)) return [];

        $files = [];
        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $item) {
            if ($item->isDot() || $item->isDir()) continue;
            if (preg_match('/\.(sql|zip)$/i', $item->getFilename())) {
                $files[] = $item->getPathname();
            }
        }

        // 按修改时间倒序
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    private function choice(string $question, array $options): string
    {
        $this->output->writeln('');
        $this->output->writeln("  <info>{$question}:</info>");
        $this->output->writeln('');

        $numericKeys = [];
        foreach ($options as $key => $label) {
            if (is_numeric($key)) {
                $this->output->writeln("  <info>[{$key}]</info> {$label}");
                $numericKeys[] = $key;
            } else {
                $this->output->writeln("  <comment>[{$key}]</comment> {$label}");
            }
        }

        $allKeys = array_keys($options);

        while (true) {
            $this->output->writeln('');
            $input = $this->ask('请输入选项编号', '');
            $input = trim($input);

            if (array_key_exists($input, $options)) {
                return $input;
            }

            // 尝试匹配数字序号
            $num = (int)$input;
            if ($num >= 1 && $num <= count($numericKeys)) {
                return $numericKeys[$num - 1];
            }

            $this->output->writeln('  <error>无效选项，请重新输入</error>');
        }
    }

    private function choiceFile(string $question, array $files): string
    {
        $this->output->writeln('');
        $this->output->writeln("  <info>{$question}:</info>");
        $this->output->writeln('');

        $index = 1;
        foreach ($files as $file) {
            $basename = basename($file);
            $size = $this->formatSize(filesize($file));
            $time = date('Y-m-d H:i:s', filemtime($file));
            $this->output->writeln("  <info>[{$index}]</info> {$basename}");
            $this->output->writeln("       大小: {$size}  |  时间: {$time}");
            $index++;
        }

        $this->output->writeln('');
        $this->output->writeln('  <comment>[0]</comment> 返回上级');

        $total = count($files);

        while (true) {
            $this->output->writeln('');
            $input = $this->ask('请输入选项编号', '');
            $input = trim($input);

            if ($input === '0' || strtolower($input) === '返回上级') {
                return '__back__';
            }

            $num = (int)$input;
            if ($num >= 1 && $num <= $total) {
                $selected = basename($files[$num - 1]);
                $this->output->writeln("  > {$selected}");
                return $selected;
            }

            $this->output->writeln('  <error>无效选项，请重新输入</error>');
        }
    }

    private function ask(string $question, string $default = ''): string
    {
        if ($default !== '') {
            $this->output->write("  <comment>{$question}</comment> [{$default}]: ");
        } else {
            $this->output->write("  <comment>{$question}</comment>: ");
        }

        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        $line = trim($line);
        return $line !== '' ? $line : $default;
    }

    private function pressAnyKey(): void
    {
        $this->output->writeln('');
        $this->output->write('  <comment>按回车键继续...</comment>');
        $handle = fopen("php://stdin", "r");
        fgets($handle);
        fclose($handle);
    }

    private function printBanner(): void
    {
        $this->output->writeln('');
        $this->output->writeln('  <info>╔══════════════════════════════════════════╗</info>');
        $this->output->writeln('  <info>║        mTab 系统备份与恢复工具           ║</info>');
        $this->output->writeln('  <info>╚══════════════════════════════════════════╝</info>');
        $this->output->writeln('');
    }
}
