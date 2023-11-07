<?php

namespace app\controller;

use app\BaseController;
use app\model\SettingModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPHtmlParser\Dom;
use think\facade\Cache;
use think\facade\Filesystem;
use think\helper\Str;

class Api extends BaseController
{
    public function site(): \think\response\Json
    {
        return $this->success("ok", [
            'email' => $this->Setting('email', ''),
            "recordNumber" => $this->Setting("recordNumber", '')
        ]);
    }

    public function background()
    {
        $bg = $this->Setting('backgroundImage');
        if ($bg) {
            return redirect($bg, 302);
        }
        return download("static/background.jpeg",);
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
            $status = \Mail::send($mail, "<h2>您的验证码是: <b style='color:#1d5cdc'>$code</b></h2>");
            if ($status) {
                Cache::set('code' . $mail, $code, 60);
                return $this->success("发送成功");
            }
        }
        return $this->error('发送失败');
    }

    function getIcon(): \think\response\Json
    {
        $avatar = $this->request->post('avatar');
        if ($avatar) {
            $remote_avatar = $this->Setting("remote_avatar", "https://avatar.mtab.cc/6.x/thumbs/png?seed=", true);
            $str = $this->downloadFile($remote_avatar . $avatar, md5($avatar) . '.png');
            return $this->success(['src' => $str]);
        }
        $url = $this->request->post('url', false);
        $icon = "";
        $cdn = $this->Setting('assets_host', '');
        if ($url) {
            $urlInfo = parse_url($url);
            $host = $urlInfo['host'] ?? $urlInfo['path'];
            $title = '';
            $scheme = "http";
            if (isset($urlInfo['scheme'])) {
                $scheme = $urlInfo["scheme"];
            }
            $realUrl = $scheme . "://" . $host;
            $client = \Axios::http();
            $response = null;
            $status = null;
            try {
                $response = $client->get($realUrl);
                $status = $response->getStatusCode();
            } catch (\Exception $e) {
                return $this->error('获取失败');
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
                    if (count($list) > 0) {
                        $icon = $list->href;
                        $iconInfo = parse_url($icon);
                        if (!isset($iconInfo['scheme'])) {
                            if (isset($iconInfo['host'])) {
                                $icon = "https://" . $iconInfo["host"] . $icon;
                            } else {
                                $icon = $realUrl . $icon;
                            }
                        }
                        $response = \Axios::http()->head($icon);
                        $contentType = $response->getHeader('content-type');
                        $contentType = $contentType[0];
                        if (preg_match('/(png|jpg|jpeg|x-icon|svg\+xml)$/', $contentType, $matches)) {
                            $contentType = array(
                                'png' => 'png',
                                'jpg' => 'jpg',
                                'jpeg' => 'jpeg',
                                'x-icon' => 'ico',
                                'svg+xml' => 'svg'
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
        return $this->error('no');
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
                'sink' => $downloadPath
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
        $remote_avatar = $this->Setting('remote_avatar', 'https://avatar.mtab.cc/6.x/thumbs/png?seed=', true);
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
            return $this->error('max fileSize is 5M');
        }
        if (in_array(strtolower($file->getOriginalExtension()), ['png', 'jpg', 'jpeg', 'webp', 'ico'])) {
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
}
