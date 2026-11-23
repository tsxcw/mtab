#!/bin/bash

php-fpm83
nginx
nohup redis-server /opt/redis.conf > /dev/null 2>&1 &

lock_file="/move.lock"
source_dir="/www/"
destination_dir="/app"

if [ ! -e "$destination_dir" ]; then
    mkdir "$destination_dir"
fi

# 检查是否存在锁文件（首次启动才需要迁移）
if [ ! -e "$lock_file" ]; then
    chmod -R 777 "$source_dir"
    rsync -aL "$source_dir/" "$destination_dir"
    touch "$lock_file"
    rm -rf "$source_dir"
fi

# ✅ 无论是否首次启动，都设置权限（放到 if 外面）
# 只 chmod 不 chown：避免夺走宿主机用户的属主，777 已保证容器内可读写
chmod -R 777 "$destination_dir"

# ---------- 常驻权限修复 ----------
# 目的：用户在宿主机修改过文件后，属主/模式变化会导致容器内 nginx 无法删除/写入，
#       远程更新失败。这里以 root 常驻修复权限，保证挂载卷内文件始终可写。

# 全量修复
full_perm_fix() {
    chmod -R 777 "$destination_dir" 2>/dev/null
}

# 后台守护：
# 1) 事件驱动：inotify 监听内容变化，只修发生变化的子树（排除 attrib，避免自身 chmod 触发死循环）
# 2) 低频兜底：每 10 分钟全量修复一次，防止 inotify 丢事件
# 3) 强刷通道：Web 端更新前写 runtime/permfix.request，这里立即全量修复并回写 permfix.done
perm_fix_daemon() {
    (
        while true; do
            sleep 600
            full_perm_fix
        done
    ) &

    (
        while true; do
            inotifywait -q -r -m -e modify,create,delete,move --format '%w' "$destination_dir" 2>/dev/null |
            while read -r dir; do
                chmod -R 777 "$dir" 2>/dev/null
            done
            # inotify 异常退出（如句柄耗尽、目录被替换）后稍候重启监听
            sleep 5
        done
    ) &
}
perm_fix_daemon

# 每轮主循环检查更新前的强刷请求
check_permfix_request() {
    req="$destination_dir/runtime/permfix.request"
    done_file="$destination_dir/runtime/permfix.done"
    if [ -e "$req" ]; then
        rm -f "$req"
        full_perm_fix
        touch "$done_file"
        chmod 666 "$done_file" 2>/dev/null
    fi
}

if [ -e "/app/public/installed.lock" ]; then
    echo "检查数据库更新..."
    php "$destination_dir/think" repair
else
    php "/app/env.php"
fi

echo "php-fpm83, redis and nginx started"

while true; do
    check_permfix_request
    sleep 2
done
