create table card
(
    id          int auto_increment
        primary key,
    name        varchar(200)  null,
    name_en     varchar(200)  null,
    status      int default 0 null,
    version     int default 0 null,
    tips        varchar(255)  null comment '说明',
    create_time datetime      null comment '添加时间',
    src         text          null comment 'logo',
    url         varchar(255)  null comment '卡片地址',
    `window`    varchar(255)  null comment '窗口地址',
    update_time datetime      null,
    install_num int default 0 null
)
    comment '卡片数据表';

create table config
(
    user_id int  null,
    config  json null
);

create index config_user_id_index
    on config (user_id);

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
    user_id     int      null,
    update_time datetime null comment '更新时间',
    link        json     null
);

create table link_folder
(
    id   int auto_increment comment 'id'
        primary key,
    name varchar(50)   null comment '分类名称',
    sort int default 0 null
)
    comment '标签链接分类';

create table linkstore
(
    id          int auto_increment
        primary key,
    name        varchar(255)               null,
    src         varchar(255)               null,
    url         varchar(255)               null,
    type        varchar(20) default 'icon' null,
    size        varchar(20) default '1x1'  null,
    create_time datetime                   null,
    hot         bigint      default 0      null,
    area        varchar(20) default ''     null comment '专区',
    tips        varchar(255)               null comment '介绍',
    domain      varchar(255)               null,
    app         int         default 0      null comment '是否app',
    install_num int         default 0      null comment '安装量',
    bgColor     varchar(30)                null comment '背景颜色',
    constraint linkStore_id_uindex
        unique (id)
);

create table note
(
    id          bigint auto_increment
        primary key,
    user_id     bigint        null,
    title       varchar(50)   null,
    text        text          null,
    create_time datetime      null,
    update_time datetime      null,
    weight      int default 0 null,
    constraint note_id_uindex
        unique (id)
);

create index note_user_id_index
    on note (user_id);

create table search_engine
(
    id          int auto_increment
        primary key,
    name        varchar(50)   null comment '名称',
    icon        varchar(255)  null comment '图标 128x128',
    url         varchar(255)  null comment '跳转url',
    sort        int default 0 null comment '排序',
    create_time datetime      null comment '添加时间',
    status      int default 0 null comment '状态 0=关闭 1=启用',
    tips        varchar(250)  null comment '搜索引擎介绍'
)
    comment '搜索引擎';

create table setting
(
    `keys` varchar(200) not null
        primary key,
    value  text         null
);

create table tabbar
(
    user_id     int      null,
    tabs        json     null,
    update_time datetime null
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

create table user_search_engine
(
    user_id int  not null
        primary key,
    list    json null,
    constraint user_search_engine_pk
        unique (user_id)
)
    comment '用户搜索引擎同步表';



INSERT INTO linkstore (name, src, url, type, size, create_time, hot, tips, domain, app, install_num) VALUES ('Bilibili', '/static/bilibili.png', 'https://bilibili.com', 'icon', '1x1', '2022-11-07 21:51:42', 0, 'Bilibili弹幕视频网站Acg网站', 'bilibili.com,www.bilibili.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, tips, domain, app, install_num) VALUES ('蓝易云', '/static/tsy.png', 'https://www.tsyvps.com/aff/IRYIGFMX', 'icon', '1x1', '2022-11-07 22:02:41', 0, '蓝易云-持证高性价比服务器', 'www.tsyvps.com,tsyvps.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, tips, domain, app, install_num) VALUES ('ImgUrl', '/static/imgurl.png', 'https://imgurl.ink', 'icon', '1x1', '2022-11-07 22:05:46', 0, 'ImgUrl图床，图片外链', 'imgurl.ink,www.imgurl.ink', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, tips, domain, app, install_num) VALUES ('微博', '/static/weibo.png', 'http://weibo.com/', 'icon', '1x1', '2022-11-07 23:37:22', 1, '微博-随时随地发现新鲜事', 'weibo.com,www.weibo.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, tips, domain, app, install_num) VALUES ('火山翻译', '/static/huoshanfanyi.png', 'https://translate.volcengine.com/translate', 'icon', '1x1', '2022-11-07 23:42:49', 1, '火山翻译-字节跳动旗下机器翻译品牌', 'translate.volcengine.com', 1, 1);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, tips, domain, app, install_num) VALUES ('腾讯云', '/static/tencentcloud.png', 'https://cloud.tencent.com/', 'icon', '1x1', '2022-11-10 16:25:51', 1, '腾讯云', 'cloud.tencent.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, tips, domain, app, install_num) VALUES ('阿里云', '/static/aliyun.png', 'https://www.aliyun.com/', 'icon', '1x1', '2022-11-10 17:30:17', 1, '阿里云', 'www.aliyun.com,aliyun.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, tips, domain, app, install_num) VALUES ('腾讯视频', '/static/txsp.png', 'https://v.qq.com/channel/choice?channel_2022=1', 'icon', '1x1', '2022-12-19 19:34:45', 0, '腾讯视频', 'v.qq.com', 0, 0);
INSERT INTO linkstore (name, src, url, type, size, create_time, hot, tips, domain, app, install_num) VALUES ('记事本', '/static/note.png', '/noteApp', 'icon', '1x1', '2023-06-14 21:13:15', 1,'记事本App', '/noteApp', 1, 3);


INSERT INTO search_engine (id, name, icon, url, sort, create_time, status, tips) VALUES (1, '百度', '/static/searchEngine/baidu.svg', 'https://www.baidu.com/s?wd={1}', 0, '2024-01-14 22:12:18', 1, '中国领先的搜索引擎和互联网公司，提供全球最大的中文搜索引擎服务，同时涵盖在线地图、贴吧、知道等多个互');
INSERT INTO search_engine (id, name, icon, url, sort, create_time, status, tips) VALUES (3, '必应', '/static/searchEngine/bing.svg', 'https://www.bing.com/search?q={1}', 99, '2024-01-14 23:20:03', 1, '微软推出的搜索引擎，以直观的界面和优质搜索结果而闻名，提供全球范围内的多语言搜索服务');
INSERT INTO search_engine (id, name, icon, url, sort, create_time, status, tips) VALUES (4, 'Google', '/static/searchEngine/google.svg', 'https://www.google.com/search?q={1}', 98, '2024-01-14 23:20:21', 1, 'Google：全球最大的搜索引擎，以卓越的搜索算法、广告服务和多样化的产品而著称，成为互联网信息检索');
INSERT INTO search_engine (id, name, icon, url, sort, create_time, status, tips) VALUES (5, '搜狗', '/static/searchEngine/sougou.svg', 'https://www.sogou.com/web?query={1}', 0, '2024-01-14 23:20:46', 1, '中国领先的搜索引擎，致力于提供智能搜索和语音输入技术，以及多元化的互联网服务，深受用户喜爱');
INSERT INTO search_engine (id, name, icon, url, sort, create_time, status, tips) VALUES (6, '360', '/static/searchEngine/360.svg', 'https://www.so.com/s?q={1}', 0, '2024-01-14 23:21:07', 1, '中国知名搜索引擎，注重用户隐私安全，提供全面的搜索服务，涵盖网页、图片、新闻等多个领域，致力于用户友');
INSERT INTO search_engine (id, name, icon, url, sort, create_time, status, tips) VALUES (7, '开发者搜索', '/static/searchEngine/baidudev.png', 'https://kaifa.baidu.com/searchPage?module=SEARCH&wd={1}', 0, '2024-01-14 23:21:45', 1, '专注于技术文档、API 和开发者资源的搜索引擎，为开发者提供快速准确的技术信息检索服务，支持多种编程');
INSERT INTO search_engine (id, name, icon, url, sort, create_time, status, tips) VALUES (8, 'B站', '/static/searchEngine/bilibiliico.png', 'https://search.bilibili.com/all?vt=21160573&keyword={1}', 0, '2024-01-14 23:21:57', 1, '中国弹幕视频平台，以二次元文化为特色，提供丰富的动画、游戏、音乐等内容，用户可通过弹幕互动分享观感。');
INSERT INTO search_engine (id, name, icon, url, sort, create_time, status, tips) VALUES (9, '微博', '/static/searchEngine/weiboico.png', 'https://s.weibo.com/weibo?q={1}', 0, '2024-01-14 23:22:12', 1, '中国社交媒体平台，用户可以发布短文、图片和视频，关注他人并互动评论，是实时新闻、话题讨论和社交分享的');
INSERT INTO search_engine (id, name, icon, url, sort, create_time, status, tips) VALUES (10, 'DuckDuckGo', '/static/searchEngine/DuckDuckGo.svg', 'https://duckduckgo.com/?t=h_&q={1}&ia=web', 96, '2024-01-15 21:37:44', 1, '注重隐私保护的搜索引擎，致力于不追踪用户个人信息，提供匿名、安全的搜索服务，受到关注的隐私倡导者青睐');