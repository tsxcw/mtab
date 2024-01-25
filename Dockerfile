FROM alpine:3.13
LABEL describe="tushan-mtab"
LABEL author ="tushan<admin@mcecy.com>"

WORKDIR /

COPY ./docker/install.sh /install.sh
COPY ./docker/start.sh /start.sh
COPY ./docker/nginx.conf /nginx.conf
COPY ./docker/default.conf /default.conf
COPY ./docker/www.conf /www.conf
COPY ./docker/redis.conf /opt/redis.conf
COPY ./docker/php.ini /php.ini

COPY . /www

RUN chmod +x /install.sh && /install.sh && rm /install.sh


EXPOSE 6379 80 443 8080

CMD ["./start.sh"]

#构建全平台 docker buildx create --name mybuilder --driver docker-container --use
#构建全平台 docker buildx build --platform linux/amd64,linux/arm64,linux/arm/v7,linux/arm/v6,linux/amd64/v3,linux/386 -t itushan/mtab --push .
#构建本地镜像 docker build -t itushan/mtab .