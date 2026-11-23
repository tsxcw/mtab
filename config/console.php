<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'repair' => 'app\command\repair',
        'repass' => 'app\command\repass',
        'upgrade' => 'app\command\upgrade',
        'test' => 'app\command\test',
        'backup' => "app\command\backup"
    ],
];
