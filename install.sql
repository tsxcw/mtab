create table config
(
    user_id int  null,
    config  json null
);

create table history
(
    id      bigint auto_increment
        primary key,
    user_id int  null,
    link    json null,
    constraint history_id_uindex
        unique (id)
)
    comment 'link历史数据';

create table link
(
    user_id int  null,
    link    json null
);

create table linkstore
(
    id          int auto_increment
        primary key,
    name        varchar(20)                null,
    src         varchar(255)               null,
    url         varchar(255)               null,
    type        varchar(20) default 'icon' null,
    size        varchar(20) default '1x1'  null,
    create_time datetime                   null,
    hot         bigint      default 0      null,
    area        varchar(20) default '' null comment '专区',
    tips        varchar(30)                null comment '介绍',
    domain      varchar(100)               null,
    app         int         default 0      null comment '是否app',
    install_num int         default 0      null comment '安装量',
    constraint linkStore_id_uindex
        unique (id)
);

create table note
(
    id          bigint auto_increment
        primary key,
    user_id     bigint      null,
    title       varchar(50) null,
    text        text        null,
    create_time datetime    null,
    update_time datetime    null,
    constraint note_id_uindex
        unique (id)
);

create index note_user_id_index
    on note (user_id);

create table setting
(
    `keys` varchar(200) not null
        primary key,
    value  text         null
);

create table tabbar
(
    user_id int  null,
    tabs    json null
)
    comment '用户页脚信息';

create table token
(
    id          bigint auto_increment
        primary key,
    user_id     int      null,
    token       tinytext null,
    create_time int      null,
    ip          tinytext null,
    user_agent  tinytext null,
    constraint token_id_uindex
        unique (id)
);

create table user
(
    id               int auto_increment
        primary key,
    mail             varchar(50)   null,
    password         tinytext      null,
    create_time      datetime      null,
    login_ip         varchar(100)  null comment '登录IP',
    register_ip      varchar(100)  null comment '注册IP',
    manager          int default 0 null,
    login_fail_count int default 0 null,
    login_time       datetime      null comment '登录时间',
    constraint user_id_uindex
        unique (id),
    constraint user_mail_uindex
        unique (mail)
);


create table link_folder
(
    id   int auto_increment comment 'id'
        primary key,
    name varchar(50)   null comment '分类名称',
    sort int default 0 null
)
    comment '标签链接分类';
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, area, tips, domain, app, install_num) VALUES ('Bilibili', '/static/bilibili.png', 'https://bilibili.com', 'icon', '1x1', '2022-11-07 21:51:42', 0, '娱乐,社交,推荐,资讯', 'Bilibili弹幕视频网站Acg网站', 'bilibili.com,www.bilibili.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, area, tips, domain, app, install_num) VALUES ('蓝易云', '/static/tsy.png', 'https://www.tsyvps.com/aff/IRYIGFMX', 'icon', '1x1', '2022-11-07 22:02:41', 0, '综合,开发,推荐', '蓝易云-持证高性价比服务器', 'www.tsyvps.com,tsyvps.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, area, tips, domain, app, install_num) VALUES ('ImgUrl', '/static/imgurl.png', 'https://imgurl.ink', 'icon', '1x1', '2022-11-07 22:05:46', 0, '推荐,综合,开发,在线工具', 'ImgUrl图床，图片外链', 'imgurl.ink,www.imgurl.ink', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, area, tips, domain, app, install_num) VALUES ('微博', '/static/weibo.png', 'http://weibo.com/', 'icon', '1x1', '2022-11-07 23:37:22', 1, '推荐,资讯,娱乐,社交', '微博-随时随地发现新鲜事', 'weibo.com,www.weibo.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, area, tips, domain, app, install_num) VALUES ('火山翻译', '/static/huoshanfanyi.png', 'https://translate.volcengine.com/translate', 'icon', '1x1', '2022-11-07 23:42:49', 1, '推荐,在线工具,效率', '火山翻译-字节跳动旗下机器翻译品牌', 'translate.volcengine.com', 1, 1);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, area, tips, domain, app, install_num) VALUES ('腾讯云', '/static/tencentcloud.png', 'https://cloud.tencent.com/', 'icon', '1x1', '2022-11-10 16:25:51', 1, '开发,推荐,综合', '腾讯云', 'cloud.tencent.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, area, tips, domain, app, install_num) VALUES ('阿里云', '/static/aliyun.png', 'https://www.aliyun.com/', 'icon', '1x1', '2022-11-10 17:30:17', 1, '推荐,开发', '阿里云', 'www.aliyun.com,aliyun.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, area, tips, domain, app, install_num) VALUES ('腾讯视频', '/static/txsp.png', 'https://v.qq.com/channel/choice?channel_2022=1', 'icon', '1x1', '2022-12-19 19:34:45', 0, '娱乐', '腾讯视频', 'v.qq.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, area, tips, domain, app, install_num) VALUES ('记事本', '/static/note.png', '/noteApp', 'icon', '1x1', '2023-06-14 21:13:15', 1, '系统,在线工具', '记事本App', '/noteApp', 1, 3);