<?php
/**
 * Upgrade 类占位定义（仅用于IDE代码提示）
 *
 * 该类实际由远程升级脚本运行时定义：
 * app/controller/admin/Index.php 的 updateApp() 会先下载远程 update_php
 * 到 runtime/update.php 并 require_once，随后 new \Upgrade() 使用远程类。
 * 本地此文件不会被加载，仅声明骨架以便 IDE 识别类与成员。
 */
class Upgrade
{
    //远程更新包下载地址
    public $update_download_url = '';

    //远程升级SQL地址
    public $update_sql_url = '';

    //启动升级任务
    public function run()
    {
        return true;
    }
}
