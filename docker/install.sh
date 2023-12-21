
apk add php7 php7-pdo_mysql php7-xml php7-xmlrpc php7-openssl php7-posix php7-pcntl php7-sqlite3 php7-pdo_sqlite php7-curl php7-json php7-session php7-phar php7-iconv php7-mbstring php7-fileinfo php7-exif php7-redis php7-gd php7-pecl-imagick-dev php7-pecl-imagick php7-mysqlnd php7-mysqli php7-ctype php7-gmp php7-redis composer unzip  php7-xmlreader php7-xmlwriter php7-dom php7-fpm

apk add php7-simplexml

apk add nginx

apk add redis

apk add rsync

apk add --no-cache tzdata
cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime

#创建nginx启动需要的目录
mkdir /run/nginx


#移动配置文件
mv /default.conf /etc/nginx/http.d/default.conf
mv /www.conf /etc/php7/php-fpm.d/www.conf
mv /php.ini /etc/php7/php.ini
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
