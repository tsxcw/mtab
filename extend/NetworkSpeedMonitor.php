<?php

class NetworkSpeedMonitor
{

//获取内存使用情况
    public static function getMemoryUsage(): array
    {
        $memoryInfo = shell_exec('free -b');
        if ($memoryInfo !== false) {
            $lines = explode("\n", $memoryInfo);
            $memoryData = preg_split('/\s+/', $lines[1]);
            $totalMemory = $memoryData[1];
            $usedMemory = $memoryData[2];
            return ['total' => (double)$totalMemory, 'used' => (double)$usedMemory, 'percentage' => (double)number_format($usedMemory / $totalMemory * 100, 2)];
        } else {
            return ['total' => 0, 'used' => 0, 'percentage' => 0];
        }
    }


    /**
     * 格式化字节数为更人性化的显示方式
     *
     * @param int $bytes 字节数
     * @return string 格式化后的字符串
     */
    public static function formatBytes(int $bytes): string
    {
        $units = array('Byte', 'KB', 'MB', 'GB', 'TB');
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }
        return round($bytes, 2) . ' ' . $units[$index];
    }

    static function getDiskData(): array
    {
        $df_output = shell_exec('df -h');
        $lines = explode("\n", $df_output);
        $disk_usage = array();
        for ($i = 1; $i < count($lines); $i++) {
            if (empty(trim($lines[$i]))) {
                continue;
            }
            // 分割每一行的数据，以空格作为分隔符
            $data = preg_split('/\s+/', $lines[$i]);
            // 提取所需的信息，例如文件系统路径、总大小、已用空间、可用空间、使用率
            $filesystem = $data[0];
            $total_size = $data[1];
            $used_space = $data[2];
            $available_space = $data[3];
            $usage_percent = $data[4];
            $mounts = $data[5];
            if ($mounts == "/") {
                // 将信息存储到关联数组中
                $disk_usage[] = array(
                    'filesystem' => $filesystem,
                    'total_size' => $total_size,
                    'used_space' => $used_space,
                    'available_space' => $available_space,
                    'usage_percent' => (double)preg_replace("#%#", '', $usage_percent), //去掉内容中的%,
                    'mounted'=>$mounts
                );
            }
        }
        return $disk_usage;
    }
}


