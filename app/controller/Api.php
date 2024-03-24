<?php

namespace app\controller;

use app\BaseController;
use app\model\ConfigModel;
use app\model\LinkModel;
use app\model\SettingModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPHtmlParser\Dom;
use think\facade\Cache;
use think\facade\Filesystem;
use think\facade\View;
use think\helper\Str;

class Api extends BaseController
{
    public function site(): \think\response\Json
    {
        $auth = false;
        if ($this->Setting('authCode', env('authCode', false), true)) {
            $auth = true;
        }
        return $this->success("ok", [
            'email' => $this->Setting('email', ''),
            'qqGroup' => $this->Setting("qqGroup", ''),
            'beianMps' => $this->Setting("beianMps", ''),
            'copyright' => $this->Setting("copyright", ''),
            "recordNumber" => $this->Setting("recordNumber", ''),
            "auth" => $auth
        ]);
    }

    public function background(): \think\response\File
    {
        $config = $this->Setting('defaultTab', 'static/defaultTab.json', true);
        if ($config) {
            $fp = public_path() . $config;
            if (file_exists($fp)) {
                $file = file_get_contents($fp);
                $json = json_decode($file, true);
                if (isset($json['config']['theme']['backgroundImage'])) {
                    $bg = $json['config']['theme']['backgroundImage'];
                    $path = joinPath(public_path(), $bg);
                    if (file_exists($path)) {
                        return download($path, 'background')->mimeType(\PluginStaticSystem::mimeType($path))->force(false)->expire(60 * 60 * 24 * 3);
                    }
                }
            }
        }
        return download("static/background.jpeg", "background.jpeg")->mimeType(\PluginStaticSystem::mimeType("static/background.jpeg"))->force(false)->expire(60 * 60 * 24 * 3);
    }

    function globalNotify()
    {
        $info = SettingModel::Config("globalNotify", false);
        if ($info) {
            return $this->success('ok', $info);
        }
        return $this->error('empty');
    }

    //获取邮件验证码
    function getMailCode(): \think\response\Json
    {
        $mail = $this->request->post("mail", false);
        $code = rand(100000, 999999);
        if ($mail) {
            if (Cache::get('code' . $mail)) {
                return $this->success("请勿频繁获取验证码");
            }
            $k = SettingModel::Config('smtp_code_template', false);
            if ($k === false || mb_strlen(trim($k)) == 0) {
                $k = '
                        <div style="border:1px #DEDEDE solid;border-top:3px #009944 solid;padding:25px;background-color:#FFF;">
                            <div style="font-size:17px;font-weight:bold;">邮箱验证码</div>
                            <div style="font-size:14px;line-height:36px;padding-top:15px;padding-bottom:15px;">
                                尊敬的用户，您好！<br>
                                您的验证码是：<b style="color: #1e9fff">{$code}</b>。5分钟内有效，请尽快验证。
                            </div>
                            <div style="line-height:15px;">
                                此致
                            </div>
                        </div>
                ';

            }
            $html = View::display($k, ['time' => date('Y-m-d H:i:s'), 'code' => $code]);
            $status = \Mail::send($mail, $html);
            if ($status) {
                Cache::set('code' . $mail, $code, 60);
                return $this->success("发送成功");
            }
        }
        return $this->error('发送失败');
    }

    private function addHttpProtocolRemovePath($url): string
    {
        // 解析URL
        $parsedUrl = parse_url($url);
        // 检查是否已经有协议，如果没有则添加http://
        if (!isset($parsedUrl['scheme'])) {
            // 检查是否以 // 开头，如果是，则转换为相对协议
            if (isset($parsedUrl['host']) && strpos($url, '//') === 0) {
                $url = 'http:' . $url;
            } else {
                $url = 'http://' . $url;
            }
        } else {
            // 如果有协议但没有路径，保留原样
            $url = $parsedUrl['scheme'] . '://';
            // 如果有主机，则添加主机部分
            if (isset($parsedUrl['host'])) {
                $url .= $parsedUrl['host'];
                // 如果有端口号，则添加端口号
                if (isset($parsedUrl['port'])) {
                    $url .= ':' . $parsedUrl['port'];
                }
            }
        }
        return $url;
    }

    private function addHttpProtocol($url)
    {
        // 检查是否已经有协议，如果没有则添加http://
        if (!parse_url($url, PHP_URL_SCHEME)) {
            // 检查是否以 // 开头，如果是，则转换为相对协议
            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            } else {
                $url = 'http://' . $url;
            }
        }
        return $url;
    }

    private function hasOnlyPath($url): bool
    {
        $parsedUrl = parse_url($url);
        // 检查是否存在路径但不存在域名和协议
        if (isset($parsedUrl['path']) && !isset($parsedUrl['host']) && !isset($parsedUrl['scheme'])) {
            return true;
        }
        return false;
    }


    function getIcon(): \think\response\Json
    {
        $avatar = $this->request->post('avatar');
        if ($avatar) {
            $remote_avatar = $this->Setting("remote_avatar", "https://avatar.mtab.cc/6.x/bottts/png?seed=", true);
            $str = $this->downloadFile($remote_avatar . $avatar, md5($avatar) . '.png');
            return $this->success(['src' => $str]);
        }
        $url = $this->request->post('url', false);
        $icon = "";
        $cdn = $this->Setting('assets_host', '');
        if ($url) {
            $realUrl = $this->addHttpProtocolRemovePath($url);
            $client = \Axios::http();
            try {
                $response = $client->get($realUrl, [
                    'headers' => [
                        "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
                    ]
                ]);
                $status = $response->getStatusCode();
            } catch (\Exception $e) {
                return $this->error('无法连接远程目标服务器');
            }
            if ($status == 200) {
                $body = $response->getBody()->getContents();
                $dom = new Dom();
                $dom->loadStr($body);
                $title = $dom->find('title');
                if (count($title) > 0) {
                    $title = $title->innerText;
                }
                try {
                    $list = $dom->find('[rel="icon"]');
                    if (count($list) == 0) {
                        $list = $dom->find('[rel="shortcut icon"]');
                    }
                    if (count($list) == 0) {
                        $list = $dom->find('[rel="Shortcut Icon"]');
                    }
                    if (count($list) > 0) {
                        $href = $list->href;
                        if ($this->hasOnlyPath($href)) {
                            if ($href[0] != '/') {
                                $href = "/" . $href;
                            }
                            $href = $realUrl . $href;
                        }
                        $href = $this->addHttpProtocol($href);
                        $icon = $href;
                        $response = \Axios::http()->get($icon, [
                            'headers' => [
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                            ]
                        ]);
                        $contentType = $response->getHeader('content-type');
                        $contentType = $contentType[0];
                        if (preg_match('/(png|jpg|jpeg|x-icon|svg\+xml)$/', $contentType, $matches)) {
                            $contentType = array(
                                'png' => 'png',
                                'jpg' => 'jpg',
                                'jpeg' => 'jpeg',
                                'x-icon' => 'ico',
                                'svg+xml' => 'svg',
                            );
                            $fileFormat = $matches[1];
                            $icon = $this->downloadFile($icon, md5($realUrl) . '.' . $contentType[$fileFormat]);
                            if ($icon) {
                                $icon = $cdn . $icon;
                            }
                        } else {
                            $icon = '';
                        }
                    }
                } catch (\ErrorException $e) {
                }
            }
            if (strlen($icon) == 0) {
                try {
                    $client = \Axios::http();
                    $response = $client->get($realUrl . '/favicon.ico');
                    $status = $response->getStatusCode();
                    if ($status == 200) {
                        $icon = $realUrl . '/favicon.ico';
                        $icon = $this->downloadFile($icon, md5($realUrl) . '.ico');
                        if ($icon) {
                            $icon = $cdn . $icon;
                        }
                    }
                } catch (\Exception $e) {
                }
            }
            if (strlen($icon) > 0) {
                return $this->success(['src' => $icon, 'name' => $title]);
            }
        }
        return $this->error('没有抓取到图标');
    }

    private function downloadFile($url, $name)
    {
        $client = \Axios::http();
        $path = '/images/' . date('Y/m/d/');
        $remotePath = public_path() . $path;
        $downloadPath = $remotePath . $name;
        if (!is_dir($remotePath)) {
            mkdir($remotePath, 0755, true);
        }
        try {
            $response = $client->request('GET', $url, [
                'sink' => $downloadPath,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ]
            ]);
            return $path . $name;
        } catch (RequestException $e) {
        }
        return false;
    }

    function renderIco(): \think\Response
    {
        $send = $this->request->get('seed');
        $client = new Client();
        $remote_avatar = $this->Setting('remote_avatar', 'https://avatar.mtab.cc/6.x/bottts/png?seed=', true);
        $response = $client->get($remote_avatar . urlencode($send), [
            'stream' => true,
            'timeout' => 10,
        ]);
        return response($response->getBody(), 200, ['Content-Type' => 'image/png']);
    }

    function upload(): \think\response\Json
    {
        $file = $this->request->file('file');
        if (empty($file)) {
            return $this->error('not File');
        }
        if ($file->getSize() > 1024 * 1024 * 5) {
            return $this->error('文件最大5MB');
        }
        if (in_array(strtolower($file->getOriginalExtension()), ['png', 'jpg', 'jpeg', 'webp', 'ico', 'svg'])) {
            // 验证文件并保存
            try {
                // 构建保存路径
                $savePath = '/images/' . date('Y/m/d');
                $hash = Str::random(32);
                $fileName = $hash . '.' . $file->getOriginalExtension();
                $filePath = Filesystem::disk('images')->putFileAs($savePath, $file, $fileName);
                $cdn = $this->Setting('assets_host', '/', true);
                return $this->success(['url' => $cdn . $filePath]);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败，给出错误提示
                // ...
            }
        }
        return $this->error('上传失败');
    }

    function AdminUpload(): \think\response\Json
    {
        $this->getAdmin();
        $file = $this->request->file('file');
        if (empty($file)) {
            return $this->error('not File');
        }
        if ($file->getSize() > 1024 * 1024 * 5) {
            return $this->error('max fileSize is 5M');
        }
        // 验证文件并保存
        try {
            // 构建保存路径
            $savePath = '/images/' . date('Y/m/d');
            $hash = Str::random(32);
            $fileName = $hash . '.' . $file->getOriginalExtension();
            $filePath = Filesystem::disk('images')->putFileAs($savePath, $file, $fileName);
            $cdn = $this->Setting('assets_host', '/', true);
            return $this->success(['url' => $cdn . $filePath]);
        } catch (\think\exception\ValidateException $e) {
            // 验证失败，给出错误提示
            // ...
        }
        return $this->error('上传失败');
    }

    function refresh(): \think\response\Json
    {
        $user = $this->getUser();
        if ($user) {
            $data = [];
            $data['link_update_time'] = LinkModel::where("user_id", $user['user_id'])->value("update_time");
            return $this->success("ok", $data);
        }
        return $this->error("not login");
    }

    function ts()
    {
        $k = SettingModel::Config('smtp_code_template');
        return View::display($k, ["time" => date("Y-m-d H:i:s"), 'code' => 123456]);
    }
}
