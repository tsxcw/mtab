<?php

declare(strict_types=1);

namespace app\command;

use app\model\SettingModel;
use Axios;
use GuzzleHttp\Exception\GuzzleException;
use think\console\Command;
use think\console\Input;

use think\console\input\Option;
use think\console\Output;

class upgrade extends Command
{
    private $authCode = null;

    protected function configure(): void
    {
        // 指令配置
        $this->setName('upgrade')->setDescription('程序升级')
            ->addOption('reset', '-r', Option::VALUE_NONE, '重新安装当前版本');
    }

    private function initAuth()
    {
        // 配置读取结果可能为 null，PHP 8.1+ 下 strlen(null) 会触发弃用错误，这里统一转成字符串
        $authCode = (string) SettingModel::Config('authCode', '', true);
        if (strlen($authCode) == 0) {
            $authCode = env('authCode', '');
        }
        $this->authCode = $authCode;
    }

    protected function execute(Input $input, Output $output): void
    {

        $reset = $input->getOption('reset');//如果存在-r参数,则强制重新安装到最新版本,忽略当前版本,尽管当前已经是最新版本.
        echo "您确实要更新程序吗？请输入[Y/N]: ";
        $response = trim(fgets(STDIN));
        if (strtoupper($response) === 'Y') {
            $this->upgrade($reset);
        } elseif (strtoupper($response) === 'N') {
            echo "取消更新程序。\n";
        } else {
            echo "无效的输入，请输入 Y 或 N。\n";
        }
    }

    function upgrade($reset)
    {
        $this->initAuth();
        $version = app_version;
        $app_version_code = $reset ? app_version_code - 1 : app_version_code;
        print_r("即将开始程序升级任务\n当前版本号:v{$version}\n");
        $result = Axios::http()->post('https://auth.mtab.cc/getUpGrade', [
            'timeout' => 30,
            'form_params' => [
                'authorization_code' => $this->authCode,
                'version_code' => $app_version_code,
            ]
        ]);
        if ($result->getStatusCode() == 200) {
            $json = json_decode($result->getBody()->getContents(), true);
            // 授权服务器返回非 JSON（如网关错误页）时，PHP 8 下访问 null 的数组下标会被错误处理器转成异常
            if (!is_array($json)) {
                print_r("授权服务器返回数据异常\n");
                return;
            }
            if (($json['code'] ?? null) === 1) {
                print_r("检测到新版本\n");
                $upGrade = new \Upgrade2();
                if (!empty($json['info']['update_zip'])) {
                    $upGrade->update_download_url = $json['info']['update_zip'];
                }
                if (!empty($json['info']['update_sql'])) {
                    $upGrade->update_sql_url = $json['info']['update_sql'];
                }
                try {
                    $upGrade->run(true); //启动任务
                    print_r("更新完毕\n");
                } catch (\Exception $e) {
                    print_r($e->getMessage() . "\n");
                }
            } else {
                print_r($json['msg'] . "\n");
            }
        } else {
            print_r("远程授权服务器连接失败\n");
        }
    }
}
