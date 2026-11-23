<?php

namespace app\controller\apps\weather;

use app\model\CardModel;
use app\PluginsBase;

class Index extends PluginsBase
{
    public $gateway = '';

    function _initialize()
    {
        parent::_initialize();
        $this->gateway = CardModel::config("weather", "gateway", "https://devapi.qweather.com");
        //如果不是https://开头就重新修改为https开头
        if (!preg_match('/^https?:\/\//', $this->gateway)) {
            $this->gateway = 'https://' . $this->gateway;
        }
    }

    function ip(): \think\response\Json
    {
        $ip = getRealIp();
        $ipInfo = [
            'ipAddress' => $ip,
            'latitude' => 39.91,
            'longitude' => 116.41,
            'cityName' => "北京",
            'regionName' => "北京",
            'countryName' => "中国"
        ];
        return $this->success('ok', $ipInfo);
    }

    function setting()
    {
        $this->getAdmin();
        if ($this->request->isPost()) {
            $form = $this->request->post();
            CardModel::saveConfigs("weather", $form);
            return $this->success("保存成功");
        }
        if ($this->request->isPut()) {
            $form = CardModel::configs('weather');
            return $this->success('ok', $form);
        }

        return $this->fetch("setting.html");
    }

    function everyDay(): \think\response\Json
    {

        $apiKey = CardModel::config('weather', 'key');
        $location = $this->request->get("location", "101010100");
        try {
            $result = \Axios::http()->get($this->gateway . '/v7/weather/7d', [
                'query' => [
                    'location' => $location,
                ],
                "headers" => [
                    "X-QW-Api-Key" => $apiKey
                ]
            ]);
            if ($result->getStatusCode() === 200) {
                $json = \Axios::toJson($result->getBody()->getContents());
                if ($json && $json['code'] == "200") {
                    return $this->success($json['daily']);
                }
            }
        } catch (\Exception $e) {
        }
        return $this->error("数据获取错误");
    }

    function now(): \think\response\Json
    {

        $apiKey = CardModel::config('weather', 'key');
        $location = $this->request->get('location', '101010100');
        try {
            $result = \Axios::http()->get($this->gateway . '/v7/weather/now', [
                'query' => [
                    'location' => $location,
                ],
                "headers" => [
                    "X-QW-Api-Key" => $apiKey
                ]
            ]);
            if ($result->getStatusCode() === 200) {
                $json = \Axios::toJson($result->getBody()->getContents());
                if ($json && $json['code'] == '200') {
                    return $this->success($json['now']);
                }
            }
        } catch (\Exception $e) {

        }
        return $this->error('数据获取错误');
    }

    function locationToCity(): \think\response\Json
    {

        $location = $this->request->all('location', '101010100');
        $apiKey = CardModel::config('weather', 'key');
        try {
            $result = \Axios::http()->get("{$this->gateway}/geo/v2/city/lookup", [
                'query' => [
                    'location' => $location,
                ],
                "headers" => [
                    "X-QW-Api-Key" => $apiKey
                ]
            ]);
            if ($result->getStatusCode() === 200) {
                $json = \Axios::toJson($result->getBody()->getContents());
                if ($json && $json['code'] == '200') {
                    if (!empty($json['location'])) {
                        return $this->success($json['location'][0]);
                    }
                }
            }
            if ($result->getStatusCode() === 401 || $result->getStatusCode() === 403) {
                return $this->error("获取失败，请检查API或者API KEY是否正确");
            }
        } catch (\Exception $e) {
        }
        return $this->error('数据获取错误');
    }

    function citySearch(): \think\response\Json
    {
        $city = $this->request->post("city", "");
        $apiKey = CardModel::config('weather', 'key');
        if (trim($city)) {
            try {
                $result = \Axios::http()->get("{$this->gateway}/geo/v2/city/lookup", [
                    'query' => [
                        'location' => $city,
                        'key' => $apiKey,
                    ],
                    "headers" => [
                        "X-QW-Api-Key" => $apiKey
                    ]
                ]);
                if ($result->getStatusCode() === 200) {
                    $json = \Axios::toJson($result->getBody()->getContents());
                    if ($json && $json['code'] == '200') {
                        if (!empty($json['location'])) {
                            return $this->success($json['location']);
                        }
                    }
                }
                if ($result->getStatusCode() === 401 || $result->getStatusCode() === 403) {
                    return $this->error("获取失败，请检查API或者API KEY是否正确");
                }
            } catch (\Exception $e) {
            }
        }
        return $this->error('数据获取错误');
    }

    function ipV2(): \think\response\Json
    {
        $ip = getRealIp();
        $result = \Axios::http()->get("https://auth.mtab.cc/weather/ipLocation?ip={$ip}");
        if ($result->getStatusCode() === 200) {
            $json = \Axios::toJson($result->getBody()->getContents());
            if ($json && $json['code'] == 1) {
                return $this->success($json['data']);
            }
        }
        return $this->error('数据获取错误');
    }

    function citySearchV2(): \think\response\Json
    {
        $city = $this->request->post("city", "");
        $result = \Axios::http()->get("https://auth.mtab.cc/weather/citySearch?q={$city}");
        if ($result->getStatusCode() === 200) {
            $json = \Axios::toJson($result->getBody()->getContents());
            if ($json && $json['code'] == 1) {
                return $this->success($json['data']);
            }
        }
        return $this->error('数据获取错误');
    }

    function nowV2(): \think\response\Json
    {
        $cityId = $this->request->get("cityId", "");
        $result = \Axios::http()->get("https://auth.mtab.cc/weather/cityWeather?cityCode={$cityId}");
        if ($result->getStatusCode() === 200) {
            $json = \Axios::toJson($result->getBody()->getContents());
            if ($json) {
                return $this->success($json);
            }
        }
        return $this->error('数据获取错误');
    }
}