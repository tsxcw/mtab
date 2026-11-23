# 创建Card数据表

create table if not exists card
(
    id int auto_increment
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci comment '卡片数据表';


alter table card
    add name varchar(200) null;

alter table card
    add name_en varchar(200) null;

alter table card
    add status int default 0 null;

alter table card
    add version int default 0 null;

alter table card
    add tips varchar(255) null comment '说明';

alter table card
    add create_time datetime null comment '添加时间';

alter table card
    add src text null comment 'logo';

alter table card
    add url varchar(255) null comment '卡片地址';

alter table card
    add `window` varchar(255) null comment '窗口地址';

alter table card
    add update_time datetime null;

alter table card
    add install_num int default 0 null;

alter table card
    add setting varchar(200) null comment '设置页面的url';

alter table card
    add dict_option longtext null comment '配置的参数';

alter table card
    add constraint card_pk
        unique (name_en);

create index card_name_en_index
    on card (name_en);

#创建config数据表

create table if not exists config
(
    user_id int null
) character set utf8mb4
  collate utf8mb4_general_ci comment '用户配置数据表';

create index config_user_id_index
    on config (user_id);

alter table config
    add config longtext null;

# 创建file数据表

create table if not exists file
(
    id bigint auto_increment
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci comment '文件';

alter table file
    add path varchar(255) null;

alter table file
    add user_id int null;

alter table file
    add create_time datetime null;

alter table file
    add size double default 0 null comment '尺寸';

alter table file
    add mime_type varchar(100) null comment '文件类型';

alter table file
    add hash varchar(100) null comment '文件哈希';


#创建history数据表

create table if not exists history
(
    id bigint auto_increment
        primary key,

    constraint history_id_uindex
        unique (id)
) character set utf8mb4
  collate utf8mb4_general_ci comment 'link历史数据';

alter table history
    add user_id int null;

alter table history
    add link longtext null;

alter table history
    add tabbar longtext null comment 'tabbar备份数据';

alter table history
    add create_time datetime null comment '创建时间';


#创建link数据表

create table if not exists link
(
    user_id int null
) character set utf8mb4
  collate utf8mb4_general_ci;

create index link_user_id_index
    on link (user_id);


alter table link
    add update_time datetime null comment '更新时间';

alter table link
    add link longtext null;


#创建link_folder数据表

create table if not exists link_folder
(
    id int auto_increment comment 'id'
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci comment '标签链接分类';

alter table link_folder
    add name varchar(50) null comment '分类名称';

alter table link_folder
    add sort int default 0 null;

alter table link_folder
    add group_ids varchar(200) default '0' null comment '可见用户分组';

#创建link_store数据表

create table if not exists linkstore
(
    id int auto_increment
        primary key,
    constraint linkStore_id_uindex
        unique (id)
) character set utf8mb4
  collate utf8mb4_general_ci;

alter table linkstore
    add name varchar(255) null;

alter table linkstore
    add src varchar(255) null;

alter table linkstore
    add url text null;

alter table linkstore
    add type varchar(20) default 'icon' null;

alter table linkstore
    add size varchar(20) default '1x1' null;

alter table linkstore
    add create_time datetime null;

alter table linkstore
    add hot bigint default 0 null;

alter table linkstore
    add area varchar(20) default '' null comment '专区';

alter table linkstore
    add tips varchar(255) null comment '介绍';

alter table linkstore
    add domain varchar(255) null;

alter table linkstore
    add app int default 0 null comment '是否app';

alter table linkstore
    add install_num int default 0 null comment '安装量';

alter table linkstore
    add bgColor varchar(30) null comment '背景颜色';

alter table linkstore
    add vip int default 0 null comment '是否会员可见 0所有人 1=会员';

alter table linkstore
    add custom text null comment '自定义配置';

alter table linkstore
    add user_id int null comment '用户id';

alter table linkstore
    add status int default 1 null comment '状态 1=展示 0=待审核';

alter table linkstore
    add group_ids varchar(200) default '0' null comment '可见用户分组';


#创建note数据表

create table if not exists note
(
    id bigint auto_increment
        primary key,
    constraint note_id_uindex
        unique (id)
) character set utf8mb4
  collate utf8mb4_general_ci;

alter table note
    add user_id bigint null;

alter table note
    add title varchar(50) null;

alter table note
    add text longtext null;

alter table note
    add create_time datetime null;

alter table note
    add update_time datetime null;

alter table note
    add weight int default 0 null;


alter table note
    add sort int default 0 null;

create index note_user_id_index
    on note (user_id);


#创建search_engine数据表

create table if not exists search_engine
(
    id int auto_increment
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci comment '搜索引擎';

alter table search_engine
    add name varchar(50) null comment '名称';

alter table search_engine
    add icon varchar(255) null comment '图标 128x128';

alter table search_engine
    add url varchar(255) null comment '跳转url';

alter table search_engine
    add sort int default 0 null comment '排序';

alter table search_engine
    add create_time datetime null comment '添加时间';

alter table search_engine
    add status int default 0 null comment '状态 0=关闭 1=启用';

alter table search_engine
    add tips varchar(250) null comment '搜索引擎介绍';


#创建setting表

create table if not exists setting
(
    `keys` varchar(200) not null
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci;

alter table setting
    add value longtext null;

#创建tabbar数据表

create table if not exists tabbar
(
    id bigint auto_increment
        primary key,
    user_id int null,
    constraint tabbar_user_id_uindex
        unique (user_id)
) character set utf8mb4
  collate utf8mb4_general_ci comment '用户页脚信息';

alter table tabbar
    add tabs longtext null;

alter table tabbar
    add update_time datetime null;

#兼容旧版本tabbar表：补充自增主键、清理同一账号的重复数据、补充账号唯一约束
#重复执行时已存在的列/索引会报错，安装脚本已关闭 mysqli 异常，会跳过继续执行
alter table tabbar
    add column id bigint auto_increment primary key first;

delete t1
from tabbar t1
         inner join tabbar t2 on t1.user_id = t2.user_id and t1.id < t2.id;

alter table tabbar
    add unique index tabbar_user_id_uindex (user_id);

#创建token表

create table if not exists token
(
    id bigint auto_increment
        primary key,
    constraint token_id_uindex
        unique (id)
) character set utf8mb4
  collate utf8mb4_general_ci;

alter table token
    add user_id int null;

alter table token
    add token tinytext null;

alter table token
    add create_time int null;

alter table token
    add ip tinytext null;

alter table token
    add user_agent tinytext null;

alter table token
    add access_token varchar(200) null comment 'qq的令牌';


#创建user表

create table if not exists user
(
    id int auto_increment
        primary key,
    constraint user_id_uindex
        unique (id)
) character set utf8mb4
  collate utf8mb4_general_ci;

alter table user
    add avatar varchar(255) null comment '头像';

alter table user
    add mail varchar(50) null;

alter table user
    add password tinytext null;

alter table user
    add create_time datetime null;

alter table user
    add login_ip varchar(100) null comment '登录IP';

alter table user
    add register_ip varchar(100) null comment '注册IP';

alter table user
    add manager int default 0 null;

alter table user
    add login_fail_count int default 0 null;

alter table user
    add login_time datetime null comment '登录时间';

alter table user
    add qq_open_id varchar(200) null comment 'qq开放平台Id';

alter table user
    add nickname varchar(200) null comment '昵称';

alter table user
    add status int default 0 null comment '用户账号状态 0正常 1冻结';

alter table user
    add active date null comment '今日是否活跃';

alter table user
    add group_id bigint default 0 null;

alter table user
    add wx_open_id varchar(200) null comment '微信开放平台id';

alter table user
    add wx_unionid varchar(200) null comment '微信开放平台唯一id';

create index user_wx_open_id_index
    on user (wx_open_id);

create index user_wx_unionid_index
    on user (wx_unionid);


alter table user
    add constraint user_pk_2
        unique (qq_open_id);


#创建user_search_engine表

create table if not exists user_search_engine
(
    user_id int not null
        primary key,
    constraint user_search_engine_pk
        unique (user_id)
) character set utf8mb4
  collate utf8mb4_general_ci comment '用户搜索引擎同步表';

alter table user_search_engine
    add list longtext null;


#创建wallpaper表

create table if not exists wallpaper
(
    id int auto_increment
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci;

alter table wallpaper modify id int auto_increment comment '自增主键';

alter table wallpaper
    add type int null comment '1=folder；0=assets';

alter table wallpaper
    add folder int null comment '0';

alter table wallpaper
    add mime int default 0 null comment '文件类型0=images，1=video';

alter table wallpaper
    add url text null comment '图片地址';

alter table wallpaper
    add cover text null comment '封面';

alter table wallpaper
    add create_time datetime null;

alter table wallpaper
    add name varchar(200) null comment '标题';

alter table wallpaper
    add sort int default 999 null;


create table user_group
(
    id bigint auto_increment comment '自增ID',
    constraint user_group_pk
        primary key (id)
) character set utf8mb4
  collate utf8mb4_general_ci comment '用户分组';

alter table user_group
    add name varchar(50) not null comment '分组名称';

alter table user_group
    add create_time datetime null comment '创建时间';

alter table user_group
    add sort int default 0 null comment '排序';

alter table user_group
    add default_user_group int default 0 null comment '是否为默认用户注册的分组';

## 创建标签备忘录数据表

create table if not exists link_memo
(
    id bigint auto_increment
        primary key,
    constraint link_memo_id_uindex
        unique (id)
) character set utf8mb4
  collate utf8mb4_general_ci comment '标签备忘录';

alter table link_memo
    add user_id int null comment '用户id';

alter table link_memo
    add link_id varchar(100) null comment '标签id';

alter table link_memo
    add content longtext null comment '备忘录内容';

alter table link_memo
    add create_time datetime null comment '创建时间';

alter table link_memo
    add update_time datetime null comment '更新时间';

create index link_memo_user_id_index
    on link_memo (user_id);

create index link_memo_link_id_index
    on link_memo (link_id);

##创建结束


##卡片组件安装部分

# 创建待办内容数据表

create table if not exists plugins_todo
(
    id int auto_increment
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci comment '待办事项';


alter table plugins_todo
    add status int default 0 null comment '状态1=完成，0=未完成';

alter table plugins_todo
    add user_id int null;

alter table plugins_todo
    add create_time datetime null;

alter table plugins_todo
    add expire_time datetime null;

alter table plugins_todo
    add todo text(1000) null;

alter table plugins_todo
    add weight int null comment '重要程度 1-6 颜色划分';

alter table plugins_todo
    add folder varchar(20) null comment 'today=今天；week=最近七天；其他正常';

create index plugins_todo_user_id_index
    on plugins_todo (user_id);

# 创建待办文件夹数据表

create table if not exists plugins_todo_folder
(
    id int auto_increment,
    primary key (id)
) character set utf8mb4
  collate utf8mb4_general_ci comment 'todo分类';

alter table plugins_todo_folder
    add column user_id int null comment '用户';

alter table plugins_todo_folder
    add column name varchar(30) null;

alter table plugins_todo_folder
    add column create_time datetime null;

create index plugins_todo_folder_user_id_index
    on plugins_todo_folder (user_id);

# 创建AI聊天记录表

create table if not exists ai
(
    id bigint auto_increment
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci comment 'AI对话消息记录';

alter table ai
    add column message longtext null comment '消息';

alter table ai
    add column role varchar(100) null comment '角色';

alter table ai
    add column create_time datetime null comment '时间';

alter table ai
    add column dialogue_id bigint null comment '对话关联id';

alter table ai
    add column ai_id varchar(255) null comment 'ai的对话id';

alter table ai
    add column user_id int null comment '用户id';

alter table ai
    add reasoning_content longtext null comment '推理结果';

#创建对话记录表

create table if not exists dialogue
(
    id bigint auto_increment comment 'id'
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci comment '对话记录';

alter table dialogue
    add column title varchar(255) null comment '提取首次聊天记录内容作为标题';

alter table dialogue
    add column create_time datetime null comment '创建时间';

alter table dialogue
    add column mode_id int null comment '上次对话的模型的ID';

alter table dialogue
    add user_id int null;

-- 创建表
create table if not exists ai_model
(
    id bigint auto_increment comment 'id'
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci comment '模型ID';


-- 添加字段：name
alter table ai_model
    add column name varchar(255) null comment '模型名称';

-- 添加字段：tips
alter table ai_model
    add column tips varchar(255) null comment '模型介绍';

-- 添加字段：api_host
alter table ai_model
    add column api_host varchar(255) null comment 'api网关';

-- 添加字段：sk
alter table ai_model
    add column sk varchar(255) not null comment '密钥';

-- 添加字段：model
alter table ai_model
    add column model varchar(255) null comment '模型类型';

-- 添加字段：system_content
alter table ai_model
    add column system_content text null comment '默认的指令';

-- 添加字段：create_time
alter table ai_model
    add column create_time datetime null comment '创建时间';

-- 添加字段：user_id
alter table ai_model
    add column user_id int null comment '用户ID';

-- 添加字段：status
alter table ai_model
    add column status int default 1 null comment '状态1开启0禁用';

-- 添加字段：multimodal（多模态支持）
alter table ai_model
    add column multimodal int default 0 null comment '是否支持多模态1是0否';


-- 添加字段：images（图片附件）
alter table ai
    add column images text null comment '图片URL列表JSON';


-- 插入 '今天吃什么' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '今天吃什么',
       'food',
       3,
       '吃什么是个很麻烦的事情',
       '/plugins/food/static/ico.png',
       '/plugins/food/card',
       '/plugins/food/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'food');

-- 更新 '今天吃什么' 记录（如果已存在）
UPDATE card
SET name     = '今天吃什么',
    version  = 3,
    tips     = '吃什么是个很麻烦的事情',
    src      = '/plugins/food/static/ico.png',
    url      = '/plugins/food/card',
    `window` = '/plugins/food/window'
WHERE name_en = 'food';

-- 插入 '天气' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '天气',
       'weather',
       13,
       '获取您所在地的实时天气！',
       '/plugins/weather/static/ico.png',
       '/plugins/weather/card',
       '/plugins/weather/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'weather');

-- 更新 '天气' 记录（如果已存在）
UPDATE card
SET name     = '天气',
    version  = 13,
    tips     = '获取您所在地的实时天气！',
    src      = '/plugins/weather/static/ico.png',
    url      = '/plugins/weather/card',
    `window` = '/plugins/weather/window'
WHERE name_en = 'weather';

-- 插入 '电子木鱼' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '电子木鱼',
       'muyu',
       5,
       '木鱼一敲 烦恼丢掉',
       '/plugins/muyu/static/ico.png',
       '/plugins/muyu/card',
       '/plugins/muyu/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'muyu');

-- 更新 '电子木鱼' 记录（如果已存在）
UPDATE card
SET name     = '电子木鱼',
    version  = 5,
    tips     = '木鱼一敲 烦恼丢掉',
    src      = '/plugins/muyu/static/ico.png',
    url      = '/plugins/muyu/card',
    `window` = '/plugins/muyu/window'
WHERE name_en = 'muyu';

-- 插入 '热搜' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '热搜',
       'topSearch',
       15,
       '聚合百度，哔站，微博，知乎，头条等热搜！',
       '/plugins/topSearch/static/ico.png',
       '/plugins/topSearch/card',
       '/plugins/topSearch/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'topSearch');

-- 更新 '热搜' 记录（如果已存在）
UPDATE card
SET name     = '热搜',
    version  = 15,
    tips     = '聚合百度，哔站，微博，知乎，头条等热搜！',
    src      = '/plugins/topSearch/static/ico.png',
    url      = '/plugins/topSearch/card',
    `window` = '/plugins/topSearch/window'
WHERE name_en = 'topSearch';

-- 插入 '记事本' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '记事本',
       'noteApp',
       15,
       '快捷记录您的灵感',
       '/plugins/noteApp/static/ico.png',
       '/plugins/noteApp/card',
       '/noteApp'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'noteApp');

-- 更新 '记事本' 记录（如果已存在）
UPDATE card
SET name     = '记事本',
    version  = 15,
    tips     = '快捷记录您的灵感',
    src      = '/plugins/noteApp/static/ico.png',
    url      = '/plugins/noteApp/card',
    `window` = '/noteApp'
WHERE name_en = 'noteApp';

-- 插入 '每日诗词' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '每日诗词',
       'poetry',
       8,
       '精选每日诗词！',
       '/plugins/poetry/static/ico.png',
       '/plugins/poetry/card',
       '/plugins/poetry/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'poetry');

-- 更新 '每日诗词' 记录（如果已存在）
UPDATE card
SET name     = '每日诗词',
    version  = 8,
    tips     = '精选每日诗词！',
    src      = '/plugins/poetry/static/ico.png',
    url      = '/plugins/poetry/card',
    `window` = '/plugins/poetry/window'
WHERE name_en = 'poetry';

-- 插入 '日历' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '日历',
       'calendar',
       1,
       '日历',
       '/plugins/calendar/static/ico.png',
       '/plugins/calendar/card',
       '/plugins/calendar/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'calendar');

-- 更新 '日历' 记录（如果已存在）
UPDATE card
SET name     = '日历',
    version  = 1,
    tips     = '日历',
    src      = '/plugins/calendar/static/ico.png',
    url      = '/plugins/calendar/card',
    `window` = '/plugins/calendar/window'
WHERE name_en = 'calendar';

-- 插入 '待办事项' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '待办事项',
       'todo',
       8,
       '快捷添加待办事项',
       '/plugins/todo/static/ico.png',
       '/plugins/todo/card',
       '/plugins/todo/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'todo');

-- 更新 '待办事项' 记录（如果已存在）
UPDATE card
SET name     = '待办事项',
    version  = 8,
    tips     = '快捷添加待办事项',
    src      = '/plugins/todo/static/ico.png',
    url      = '/plugins/todo/card',
    `window` = '/plugins/todo/window'
WHERE name_en = 'todo';

-- 插入 '倒计时' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '倒计时',
       'countdown',
       8,
       '个性化自定义事件的倒计时组件',
       '/plugins/countdown/static/ico.png',
       '/plugins/countdown/card',
       '/plugins/countdown/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'countdown');

-- 更新 '倒计时' 记录（如果已存在）
UPDATE card
SET name     = '倒计时',
    version  = 8,
    tips     = '个性化自定义事件的倒计时组件',
    src      = '/plugins/countdown/static/ico.png',
    url      = '/plugins/countdown/card',
    `window` = '/plugins/countdown/window'
WHERE name_en = 'countdown';

-- 插入 '纪念日' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '纪念日',
       'commemorate',
       8,
       '个性化自定义事件的纪念日组件',
       '/plugins/commemorate/static/ico.png',
       '/plugins/commemorate/card',
       '/plugins/commemorate/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'commemorate');

-- 更新 '纪念日' 记录（如果已存在）
UPDATE card
SET name     = '纪念日',
    version  = 8,
    tips     = '个性化自定义事件的纪念日组件',
    src      = '/plugins/commemorate/static/ico.png',
    url      = '/plugins/commemorate/card',
    `window` = '/plugins/commemorate/window'
WHERE name_en = 'commemorate';

-- 插入 'AI助手' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT 'AI助手', 'ai', 1, '您的随身AI助手', '/plugins/ai/static/ico.png', '/plugins/ai/card', '/plugins/ai/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'ai');

-- 更新 'AI助手' 记录（如果已存在）
UPDATE card
SET name     = 'AI助手',
    version  = 1,
    tips     = '您的随身AI助手',
    src      = '/plugins/ai/static/ico.png',
    url      = '/plugins/ai/card',
    `window` = '/plugins/ai/window'
WHERE name_en = 'ai';

-- 插入 '图片格式转换' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '图片格式转换',
       'imageConversion',
       1,
       '批量将图片格式转为JPEG,PNG,WEBP等格式',
       '/plugins/imageConversion/static/ico.png',
       '/plugins/imageConversion/card',
       '/plugins/imageConversion/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'imageConversion');

-- 更新 '图片格式转换' 记录（如果已存在）
UPDATE card
SET name     = '图片格式转换',
    version  = 1,
    tips     = '批量将图片格式转为JPEG,PNG,WEBP等格式',
    src      = '/plugins/imageConversion/static/ico.png',
    url      = '/plugins/imageConversion/card',
    `window` = '/plugins/imageConversion/window'
WHERE name_en = 'imageConversion';


-- 插入 '金额换算' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '金额换算',
       'amountConversion',
       1,
       '将金额转为大写',
       '/static/app/amountConversion/ico.svg',
       '/plugins/amountConversion/card',
       '/plugins/amountConversion/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'amountConversion');

-- 更新 '金额换算' 记录（如果已存在）
UPDATE card
SET name     = '金额换算',
    version  = 1,
    tips     = '将金额转为大写',
    src      = '/static/app/amountConversion/ico.svg',
    url      = '/plugins/amountConversion/card',
    `window` = '/plugins/amountConversion/window'
WHERE name_en = 'amountConversion';

-- 插入 'JSON格式化' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT 'JSON格式化',
       'jsonFormat',
       1,
       'JSON格式化、压缩、校验与高亮',
       '/static/app/jsonFormat/ico.svg',
       '/plugins/jsonFormat/card',
       '/plugins/jsonFormat/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'jsonFormat');

-- 更新 'JSON格式化' 记录（如果已存在）
UPDATE card
SET name     = 'JSON格式化',
    version  = 1,
    tips     = 'JSON格式化、压缩、校验与高亮',
    src      = '/static/app/jsonFormat/ico.svg',
    url      = '/plugins/jsonFormat/card',
    `window` = '/plugins/jsonFormat/window'
WHERE name_en = 'jsonFormat';

# 创建公告栏数据表

create table if not exists plugins_announcement
(
    id int auto_increment
        primary key
) character set utf8mb4
  collate utf8mb4_general_ci comment '公告栏';

alter table plugins_announcement
    add title varchar(200) null comment '公告标题';

alter table plugins_announcement
    add content text null comment '公告内容';

alter table plugins_announcement
    add create_time datetime null comment '创建时间';

alter table plugins_announcement
    add status tinyint default 0 null comment '状态 0=草稿 1=已发布';

alter table plugins_announcement
    add top_time datetime null comment '置顶时间，不为空时置顶，按时间倒序排列';

-- 插入 '公告栏' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '公告栏',
       'announcement',
       1,
       '查看系统公告和通知',
       '/static/app/announcement/ico.png',
       '/plugins/announcement/card',
       '/plugins/announcement/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'announcement');

-- 更新 '公告栏' 记录（如果已存在）
UPDATE card
SET name     = '公告栏',
    version  = 1,
    tips     = '查看系统公告和通知',
    src      = '/static/app/announcement/ico.png',
    url      = '/plugins/announcement/card',
    `window` = '/plugins/announcement/window'
WHERE name_en = 'announcement';

-- 插入 '番茄钟' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '番茄钟',
       'pomodoro',
       1,
       '专注计时，提升效率',
       '/static/app/pomodoro/ico.png',
       '/plugins/pomodoro/card',
       '/plugins/pomodoro/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'pomodoro');

-- 更新 '番茄钟' 记录（如果已存在）
UPDATE card
SET name     = '番茄钟',
    version  = 1,
    tips     = '专注计时，提升效率',
    src      = '/static/app/pomodoro/ico.png',
    url      = '/plugins/pomodoro/card',
    `window` = '/plugins/pomodoro/window'
WHERE name_en = 'pomodoro';

-- 插入 '开发者工具箱' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '开发者工具箱',
       'devTools',
       1,
       '集成编码转换、加密哈希、正则测试、时间戳转换等20+开发工具',
       '/static/app/devTools/ico.svg',
       '/plugins/devTools/card',
       '/plugins/devTools/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'devTools');

-- 更新 '开发者工具箱' 记录（如果已存在）
UPDATE card
SET name     = '开发者工具箱',
    version  = 1,
    tips     = '集成编码转换、加密哈希、正则测试、时间戳转换等20+开发工具',
    src      = '/static/app/devTools/ico.svg',
    url      = '/plugins/devTools/card',
    `window` = '/plugins/devTools/window'
WHERE name_en = 'devTools';

-- 插入 '摸鱼倒计时' 记录（如果不存在）
INSERT INTO card (name, name_en, version, tips, src, url, `window`)
SELECT '摸鱼倒计时',
       'moyu',
       1,
       '距离下班还有多久，今天已经摸了多少钱',
       '/static/app/moyu/ico.png',
       '/plugins/moyu/card',
       '/plugins/moyu/window'
FROM dual
WHERE NOT EXISTS (SELECT 1
                  FROM card
                  WHERE name_en = 'moyu');

-- 更新 '摸鱼倒计时' 记录（如果已存在）
UPDATE card
SET name     = '摸鱼倒计时',
    version  = 1,
    tips     = '距离下班还有多久，今天已经摸了多少钱',
    src      = '/static/app/moyu/ico.png',
    url      = '/plugins/moyu/card',
    `window` = '/plugins/moyu/window'
WHERE name_en = 'moyu';
