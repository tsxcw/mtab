#!/bin/sh


apk add php83 php83-pdo_mysql php83-xml php83-openssl php83-posix php83-pcntl php83-sqlite3 php83-pdo_sqlite php83-curl php83-session php83-phar php83-iconv php83-mbstring php83-fileinfo php83-exif php83-pecl-redis php83-gd php83-pecl-imagick php83-mysqlnd php83-mysqli php83-ctype php83-gmp unzip  php83-xmlreader php83-xmlwriter php83-dom php83-fpm

apk add php83-simplexml

apk add php83-bcmath

apk add php83-opcache

apk add php83-zip

apk add nginx

apk add bash

apk add redis

apk add rsync

apk add inotify-tools

# Alpine 3.24 的 php83 包只提供 php83 命令，不会生成 php 软链；
# 这里补一个 php 指向 php83，保证 start.sh 里的 think 命令与 env.php 正常执行
ln -sf /usr/bin/php83 /usr/local/bin/php

apk add --no-cache tzdata

cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime

# 安全加固配置：关闭 session 上传进度写入，开启严格会话模式（拒绝未初始化的 session id）
printf 'session.upload_progress.enabled = Off\nsession.use_strict_mode = 1\n' > /etc/php83/conf.d/99-mtab.ini

#创建nginx启动需要的目录
mkdir -p /run/nginx


#移动配置文件
mv /default.conf /etc/nginx/http.d/default.conf
mv /www.conf /etc/php83/php-fpm.d/www.conf
mv /nginx.conf /etc/nginx/nginx.conf
# 检查是否存在 .git 目录
if [ -d "/www/.git" ]; then
    rm -rf "/www/.git"
fi

# 检查是否存在 .ide 目录
if [ -d "/www/.idea" ]; then
    rm -rf "/www/.idea"
fi

if [ -d "/www/vendor/bin/" ]; then
    rm -rf "/www/vendor/bin/"
fi

rm -rf /www/docker/*
rm -r /www/docker
rm /www/Dockerfile

dos2unix /start.sh