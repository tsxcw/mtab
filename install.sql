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
    area        varchar(20) default '综合' null comment '专区',
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

