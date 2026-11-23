<?php
declare (strict_types=1);

namespace app\command;

use app\controller\apps\ai\Ai;
use mysqli;
use think\console\Command;
use think\console\Input;

use think\console\Output;

class test extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('test')
            ->setDescription('测试');
    }

    protected function execute(Input $input, Output $output)
    {
        (new Ai($this->app))->Index();
    }
}
