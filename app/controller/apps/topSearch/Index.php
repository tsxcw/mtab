<?php

namespace app\controller\apps\topSearch;

use app\model\CardModel;
use app\PluginsBase;
use ErrorException;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Cache;

class Index extends PluginsBase
{
    protected $ttl = 300;

    function __construct()
    {
        parent::__construct(app());
        $res = CardModel::config('topSearch', 'conf');
        if ($res) {
            if (isset($res['ttl'])) {
                $this->ttl = (int)$res['ttl'];
            }
        }
    }

    function save(): \think\response\Json
    {
        $this->getAdmin();
        $list = $this->request->post("conf");
        CardModel::saveConfigs("topSearch", ['conf' => $list]);
        $this->clearRedisCache();
        return $this->success('保存成功');
    }

    function getConf(): \think\response\Json
    {
        $this->getAdmin();
        $res = CardModel::config("topSearch", "conf", false);
        if (!$res) {
            $res = [];
        }
        return $this->success('ok', $res);
    }

    function TopSearch(): \think\response\Json
    {
        $type = $this->request->get('type', 'baidu');
        switch ($type) {
            case 'baidu':
                return $this->baiduTopSearch();
            case 'bilibili':
                return $this->bilibili();
            case 'weibo':
                return $this->weibo();
            case 'zhiHu':
                return $this->zhiHu();
            case 'douyin':
                return $this->douyin();
            case 'toutiao':
                return $this->toutiao();
        }
        return $this->error('not type');
    }

    function zhiHu(): \think\response\Json
    {
        try {
            $c = Cache::get('zhiHuTopSearch');
            if ($c) {
                return $this->success('cache', $c);
            }
        } catch (ErrorException $e) {

        }

        $api = 'https://www.zhihu.com/api/v4/creators/rank/hot?domain=0&period=hour&limit=50&offset=0';
        $result = \Axios::http()->request('get', $api);
        $result = $result->getBody()->getContents();
        $result = json_decode($result, true);
        $arr = [];
        if (!empty($result['data'])) {
            foreach ($result['data'] as $value) {
                $arr [] = array(
                    'title' => $value['question']['title'],
                    'hot' => $value['reaction']['pv'],
                    'url' => $value['question']['url']
                );
            }
            Cache::set('zhiHuTopSearch', $arr, $this->ttl);
        }
        return $this->success($arr);
    }

    //百度热搜
    public function baiduTopSearch(): \think\response\Json
    {
        try {
            $c = Cache::get('baiduTopSearch');
            if ($c) {
                return $this->success('cache', $c);
            }
        } catch (ErrorException $e) {

        }

        $result = \Axios::http()->request('get', 'https://top.baidu.com/api/board?tab=realtime');
        $result = $result->getBody()->getContents();
        $result = json_decode($result, true);
        if ($result['success']) {
            $result = $result['data']['cards'][0];
        }
        $arr = [];
        $tn = CardModel::config('topSearch', 'conf', false);
        if ($tn && isset($tn['baiduCode'])) {
            $tn = $tn['baiduCode'];
        } else {
            $tn = false;
        }
        // 接口未返回 content 时兜底为空数组，避免后续遍历/插入时报错
        $list = $result['content'] ?? [];
        if (isset($result['topContent'])) {
            $top = $result['topContent'];
            if (!empty($top)) {
                array_unshift($list, $top[0]);
            }

            foreach ($list as $k => $v) {
                $url = urlencode($v['word']);
                if ($tn) {
                    $url .= '&tn=' . $tn;
                }
                $arr [] = array(
                    'title' => $v['word'],
                    'hot' => $v['hotScore'],
                    'url' => "https://www.baidu.com/s?wd={$url}"
                );
                Cache::set('baiduTopSearch', $arr, $this->ttl);
            }
        }

        return $this->success('new', $arr);
    }

    //哔哩哔哩热搜
    public function bilibili(): \think\response\Json
    {
        $arr = [];
        try {
            $c = Cache::get('bilibiliTopSearch');
            if ($c) {
                return $this->success('cache', $c);
            }
            $result = \Axios::http()->request('get', 'https://api.bilibili.com/x/web-interface/ranking?jsonp=jsonp?rid=0&type=all&callback=__jp0');
            $result = $result->getBody()->getContents();
            $result = json_decode($result, true);
            if ($result['code'] == 0) {
                $list = $result['data']['list'];
                if (!empty($list)) {
                    foreach ($list as $k => $v) {
                        if ($k == 90) {
                            break;
                        }
                        $arr [] = array(
                            'title' => $v['title'],
                            'hot' => $v['video_review'],
                            'url' => 'https://www.bilibili.com/video/' . $v['bvid']//$v['short_link'] ?? $v['short_link_v2']
                        );
                    }
                    Cache::set('bilibiliTopSearch', $arr, $this->ttl);
                }
            }

        } catch (GuzzleException $e) {
        }
        return $this->success($arr);
    }

    //哔哩哔哩热搜
    public function weibo(): \think\response\Json
    {
        try {
            $c = Cache::get('weiboTopSearch');
            if ($c) {
                return $this->success('cache', $c);
            }
        } catch (ErrorException $e) {

        }
        $result = \Axios::http()->request('get', 'https://weibo.com/ajax/side/hotSearch', [
            'headers' => [
                'referer' => 'https://weibo.com/hot/search',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
            ]
        ]);
        $result = $result->getBody()->getContents();
        $result = json_decode($result, true);
        $arr = [];
        if ($result['ok'] == 1) {
            $list = $result['data']['realtime'];
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    $arr [] = array(
                        'title' => $v['word'],
                        'hot' => $v['raw_hot'] ?? $v['num'],
                        'url' => 'https://s.weibo.com/weiboo?q=' . $v['word']
                    );
                }
                Cache::set('weiboTopSearch', $arr, $this->ttl);
            }
        }
        return $this->success($arr);
    }

    //哔哩哔哩热搜
    public function toutiao(): \think\response\Json
    {
        try {
            $c = Cache::get('toutiaoTopSearch');
            if ($c) {
                return $this->success('cache', $c);
            }
        } catch (ErrorException $e) {

        }
        $result = \Axios::http()->request('get', 'https://www.toutiao.com/hot-event/hot-board/?origin=toutiao_pc');
        $result = $result->getBody()->getContents();
        $result = json_decode($result, true);
        $arr = [];
        if ($result['status'] == 'success') {
            $list = $result['data'];
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    $arr [] = array(
                        'title' => $v['Title'],
                        'hot' => $v['HotValue'],
                        'url' => $v['Url']
                    );
                }
                Cache::set('toutiaoTopSearch', $arr, $this->ttl);
            }
        }
        return $this->success($arr);
    }

    function douyin()
    {
        try {
            $c = Cache::get('douyinTopSearch');
            if ($c) {
                return $this->success('cache', $c);
            }
        } catch (ErrorException $e) {

        }
        $result = \Axios::http()->request('get', 'https://www.iesdouyin.com/web/api/v2/hotsearch/billboard/word/?reflow_source=reflow_page');
        $result = $result->getBody()->getContents();
        $result = json_decode($result, true);
        $arr = [];
        $list = $result['word_list'];
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $arr [] = array(
                    'title' => $v['word'],
                    'hot' => $v['hot_value'] ?? $v['num'],
                    'url' => 'https://www.douyin.com/search/' . $v['word']
                );
            }
            Cache::set('douyinTopSearch', $arr, $this->ttl);
        }
        return $this->success($arr);
    }

    public function clearRedisCache(): \think\response\Json
    {
        Cache::delete('bilibiliTopSearch');
        Cache::delete('baiduTopSearch');
        Cache::delete('weiboTopSearch');
        Cache::delete('zhiHuTopSearch');
        Cache::delete('douyinTopSearch');
        return $this->success('刷新完毕');
    }
}