<?php


namespace app\controller;

use app\BaseController;
use app\model\ConfigModel;
use app\model\FileModel;
use app\model\LinkFolderModel;
use app\model\LinkModel;
use app\model\SettingModel;
use app\model\TokenModel;
use app\model\UserGroupModel;
use app\model\UserModel;
use GuzzleHttp\Exception\GuzzleException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;
use think\facade\Log;
use think\facade\View;

class User extends BaseController
{
    protected $qq_bind_mode = false;

    private function getDefaultUserGroupId()
    {
        $group = UserGroupModel::where('default_user_group', 1)->find();
        if (!$group) {
            return 0;
        }
        return $group['id'];
    }

    public function login(): \think\response\Json
    {
        $user = $this->request->post('username', '0');
        $pass = $this->request->post('password', '0');
        $captchaKey = $this->request->post('captcha_key', '');
        $captchaCode = $this->request->post('captcha_code', '');
        $user = trim($user);
        $pass = trim($pass);

        // 滑块拼图验证码强制校验：只有后台显式配置 captcha_check='1'（关闭验证码）才旁路，
        // 其余情况一律校验，不传字段同样会被拒绝，避免被脚本绕过
        if ($this->systemSetting("captcha_check", '0', true) !== '1') {
            // 校验失败时的错误提示，null 表示通过
            $captchaError = Captcha::check($captchaKey, $captchaCode);
            if ($captchaError !== null) {
                return $this->error($captchaError);
            }
        }

        $info = UserModel::where('mail', $user)->find();

        if (Cache::get('login.' . $user)) {
            return $this->error('账号已被安全锁定,您可以修改密码然后登录');
        }
        if (!$info) {
            // 统一错误信息，防止用户枚举（无论账号是否存在都返回相同提示）
            return $this->error('邮箱或密码错误');
        }
        if ($info['login_fail_count'] == 10) {
            Cache::set('login.' . $user, 'lock', 7200);
            $info->login_fail_count = 0;
            $info->save();
            return $this->error('账号已被锁定2小时');
        }
        // 密码校验
        if ($info['password'] !== md5($pass)) {
            $info->login_fail_count += 1;
            $info->save();
            return $this->error('邮箱或密码错误');
        }
        if ($info['status'] === 1) {
            return $this->error('账号已被冻结');
        }
        $auth = $this->refreshToken($info);
        $info->login_ip = getRealIp();
        $info->login_time = date('Y-m-d H:i:s');
        $info->login_fail_count = 0; //登陆成功将失败次数归零
        $info->save();
        return $this->success('登录成功', $auth);
    }


    private function refreshToken($info): array
    {
        $token = renderToken($info['id']);
        // 部分客户端（如接口调用）不带 User-Agent，header() 会返回 null，PHP 8.1+ 下传给 mb_substr 会触发弃用错误
        $agent = (string)$this->request->header('User-Agent');
        $agent = mb_substr($agent, 0, 250);
        $auth = ['user_id' => $info['id'], 'token' => $token, 'create_time' => time(), 'ip' => getRealIp(), 'user_agent' => $agent];
        if (isset($info['access_token'])) {
            $auth['access_token'] = $info['access_token'];
        }
        TokenModel::insert($auth);
        unset($auth['user_agent']);
        unset($auth['access_token']);
        unset($auth['ip']);
        return $auth;
    }

    function register(): \think\response\Json
    {
        if ($this->systemSetting("user_register", '0', true) === '1') {
            return $this->error('管理员已关闭用户注册功能');
        }
        $user = $this->request->post('username', false);
        $pass = $this->request->post('password', false);
        $code = $this->request->post('code', '0000');
        if ($user && $pass) {
            $user = trim($user);
            $pass = trim($pass);
            if (!validateEmail($user)) {
                return $this->error('邮箱格式错误');
            }
            if (strlen($pass) < 6) {
                return $this->error('密码过短');
            }
            if ($this->systemSetting("auth_check", '0', true) === '0') {
                // 验证码验证（含错误次数限制，最多5次）
                $cacheCode = Cache::get('code' . $user);
                if (!$cacheCode || $cacheCode != $code) {
                    $attempts = Cache::get('code_attempts_' . $user) ?? 0;
                    $attempts++;
                    Cache::set('code_attempts_' . $user, $attempts, 300);
                    if ($attempts >= 5) {
                        Cache::delete('code' . $user);
                        Cache::delete('code_attempts_' . $user);
                        return $this->error('验证码错误次数过多，请重新获取');
                    }
                    return $this->error('验证码错误');
                }
                Cache::delete('code_attempts_' . $user);
            }
            if (UserModel::where('mail', $user)->field('id,mail')->find()) {
                return $this->error('该邮箱已被注册');
            }
            $add = UserModel::insert(['mail' => $user, 'password' => md5($pass), 'create_time' => date('Y-m-d H:i:s'), 'register_ip' => getRealIp(), 'group_id' => $this->getDefaultUserGroupId()]);
            if ($add) {
                Cache::delete('code' . $user);
                return $this->success('ok');
            }
        }
        return $this->error('注册失败');
    }

    public function forgetPass(): \think\response\Json
    {
        $user = $this->request->post('username', false);
        $pass = $this->request->post('password', false);
        $oldPass = $this->request->post('oldPassword', false);
        $code = $this->request->post('code', '0000');
        if ($user && $pass) {
            $user = trim($user);
            $pass = trim($pass);
            if (!validateEmail($user)) {
                return $this->error('邮箱格式错误');
            }
            if (strlen($pass) < 6) {
                return $this->error('密码过短');
            }
            $info = UserModel::where('mail', $user)->field('id,mail,password')->find();
            if (!$info) {
                // 统一提示，防止用户枚举
                return $this->error('操作已完成，请查收邮件');
            }
            // 账号被锁定时不允许修改密码（防止锁定绕过）
            if (Cache::get('login.' . $user)) {
                return $this->error('账号已被安全锁定，请稍后再试');
            }
            if ($this->systemSetting("auth_check", '0', true) === '0') {
                // 验证码验证（含错误次数限制，最多5次）
                $cacheCode = Cache::get('code' . $user);
                if (!$cacheCode || $cacheCode != $code) {
                    $attempts = Cache::get('code_attempts_' . $user) ?? 0;
                    $attempts++;
                    Cache::set('code_attempts_' . $user, $attempts, 300);
                    if ($attempts >= 5) {
                        Cache::delete('code' . $user);
                        Cache::delete('code_attempts_' . $user);
                        return $this->error('验证码错误次数过多，请重新获取');
                    }
                    return $this->error('验证码错误');
                }
                Cache::delete('code_attempts_' . $user);
            } else if ($this->systemSetting("auth_check", '0', true) === '1') {
                //不验证邮件验证码
                if (!$oldPass || md5($oldPass) != $info['password']) {
                    return $this->error('旧密码错误');
                }
            }
            $info->password = md5($pass);
            $add = $info->save();
            if ($add) {
                TokenModel::where('user_id', $info['id'])->delete(); //删除所有登录记录
                Cache::delete('login.' . $user);
                return $this->success('ok');
            }
        }
        return $this->error('修改失败');
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function newMail(): \think\response\Json
    {
        $userinfo = $this->getUser(true);
        $user = $this->request->post('mail', false);
        $code = $this->request->post('code', false);
        if ($user && $code) {
            $user = trim($user);
            if (!validateEmail($user)) {
                return $this->error('邮箱格式错误');
            }
            $cacheCode = Cache::get('code' . $user);
            if ($cacheCode && $cacheCode == $code) {
                Cache::delete('code_attempts_' . $user);
                $info = UserModel::where('mail', $user)->field('id,mail')->find();
                if ($info) {
                    return $this->error('该邮箱已被使用！');
                }
                $info = UserModel::where('id', $userinfo['user_id'])->field('id,mail')->find();
                $info->mail = $user;
                $info->save();
                Cache::delete('code' . $user);
                return $this->success('修改成功');
            } else {
                // 验证码错误次数限制，最多5次
                $attempts = Cache::get('code_attempts_' . $user) ?? 0;
                $attempts++;
                Cache::set('code_attempts_' . $user, $attempts, 300);
                if ($attempts >= 5) {
                    Cache::delete('code' . $user);
                    Cache::delete('code_attempts_' . $user);
                    return $this->error('验证码错误次数过多，请重新获取');
                }
                return $this->error('验证码错误');
            }
        }
        return $this->error('请认真填写表单');
    }

    function loginOut(): \think\response\Json
    {
        $user = $this->getUser();
        if ($user) {
            TokenModel::where('user_id', $user['user_id'])->where('token', $user['token'])->delete();
        }
        return $this->success('ok');
    }

    public function get(): \think\response\Json
    {
        $info = $this->getUser(true);
        if ($info) {
            $info = UserModel::field('id,mail,manager,nickname,avatar,qq_open_id,active,wx_open_id')->find($info['user_id']);
            if ($info['qq_open_id']) {
                $info['qqBind'] = true;
                unset($info['qq_open_id']);
            }
            if ($info['wx_open_id']) {
                $info['wxBind'] = true;
                unset($info['wx_open_id']);
            }
            if ($info['active'] !== date("Y-m-d")) {
                $info['active'] = date("Y-m-d");
                $info->save();
            }
            return $this->success('ok', $info);
        }
        return $this->error('获取失败');
    }


    public function unbindQQ(): \think\response\Json
    {
        $info = $this->getUser(true);
        if ($info) {
            $info = UserModel::field('id,mail,manager,nickname,avatar,qq_open_id')->find($info['user_id']);
            if (empty($info->mail)) {
                return $this->error("请先绑定邮箱后再解绑");
            }
            $info->qq_open_id = "";
            $info->save();
        }
        return $this->success('解绑成功', $info);
    }

    public function unbindWx(): \think\response\Json
    {
        $info = $this->getUser(true);
        if ($info) {
            $info = UserModel::field('id,mail,manager,nickname,avatar,wx_open_id')->find($info['user_id']);
            if (empty($info->mail)) {
                return $this->error('请先绑定邮箱后再解绑');
            }
            $info->wx_open_id = '';
            $info->wx_unionid = '';
            $info->save();
        }
        return $this->success('解绑成功', $info);
    }

    public function updateInfo(): \think\response\Json
    {
        $info = $this->getUser(true);
        $field = $this->request->post('field', false);
        $value = $this->request->post('value', false);
        //允许修改的字段
        $allow = ['nickname', 'avatar'];
        if ($info && $field && $value && in_array($field, $allow)) {
            UserModel::where('id', $info['user_id'])->update([$field => $value]);
        }
        return $this->success('修改成功');
    }

    function qLogin(): \think\response\Redirect
    {
        $appId = SettingModel::Config('qq_login_appid', false);
        $callback = 'https://' . $this->request->host() . '/qq_login';
        $type = $this->request->get('type', '');
        $query = [
            'redirect_uri' => $callback,
            'state' => md5(uniqid()),
            'response_type' => 'code',
            'scope' => 'get_user_info,list_album,upload_pic',
            'client_id' => $appId
        ];
        if ($type === 'bind') {
            $query['state'] = $query['state'] . 'bind';
        }
        $http = http_build_query($query);
        return redirect('https://graph.qq.com/oauth2.0/authorize?' . $http);
    }

    function qq_login(): string
    {
        $appId = SettingModel::Config('qq_login_appid', false);
        $code = $this->request->get('code', false);
        $state = $this->request->get('state');
        if (strpos($state, 'bind')) {
            //绑定模式
            $this->qq_bind_mode = true;
        }
        $callback = 'https://' . $this->request->host(true) . '/qq_login';
        $result = \Axios::http()->get('https://graph.qq.com/oauth2.0/token', [
            'query' => [
                'grant_type' => 'authorization_code',
                'client_id' => $appId,
                'client_secret' => SettingModel::Config('qq_login_appkey', false),
                'code' => $code,
                'redirect_uri' => $callback,
                'fmt' => 'json'
            ]
        ]);
        if ($result->getStatusCode() === 200) {
            $content = $result->getBody()->getContents();
            $js = \Axios::toJson($content);
            if (isset($js['access_token'])) {
                $access_token = $js['access_token'];
                return $this->getOpenId($access_token);
            }
        }
        return View::fetch('/qq_login_error');
    }

    //此方法禁止网络访问
    private function getOpenId($access_token): string
    {
        $result = \Axios::http()->get('https://graph.qq.com/oauth2.0/me', [
            'query' => [
                'access_token' => $access_token,
                'fmt' => 'json'
            ]
        ]);
        if ($result->getStatusCode() === 200) {
            $content = $result->getBody()->getContents();
            $js = \Axios::toJson($content);
            if (isset($js['openid'])) {
                $openid = $js['openid'];
                if ($this->qq_bind_mode) {
                    //绑定模式
                    if (UserModel::where('qq_open_id', $openid)->field('id,qq_open_id')->find()) {
                        return View::fetch('/qq_login_error');
                    }
                    //如果openid数据库不存在说明QQ没有被绑定过，可以绑定
                    $this->BindQQ($openid); //绑定后需要替换Token，不然之前的QQ登录会失效
                }
                $info = UserModel::where('qq_open_id', $openid)->find();
                if (!$info) { //不存在就创建一个新用户,如果上一个步骤绑定成功的话，是不可能进入此步骤的
                    UserModel::insert(['mail' => null, 'password' => md5(time()), 'create_time' => date('Y-m-d H:i:s'), 'register_ip' => getRealIp(), 'qq_open_id' => $openid, 'group_id' => $this->getDefaultUserGroupId()]);
                    $info = UserModel::where('qq_open_id', $openid)->find();
                    $this->getUserOpenInfo($access_token, $openid); //获取一些用户的默认信息
                }
                if ($info) { //如果用户存在
                    $info->login_ip = getRealIp();
                    $info->login_time = date('Y-m-d H:i:s');
                    $info->login_fail_count = 0; //登陆成功将失败次数归零
                    $info->save();
                    $info['access_token'] = $access_token;
                    $auth = $this->refreshToken($info);
                    if ($info['status'] === 1) {
                        return View::fetch('/qq_login_error');
                    }
                    return View::fetch('/qq_login', ['info' => $auth]);
                }
            }
        }
        return View::fetch('/qq_login_error');
    }

    private function BindQQ($qq_open_id)
    {
        $user = $this->getUser();
        if ($user) {
            $info = UserModel::where('id', $user['user_id'])->field('id,mail,qq_open_id,password,login_fail_count,login_ip,login_time')->find();
            if ($info) {
                $info->qq_open_id = $qq_open_id;
                $info->save();
            }
        }
    }

    private function getUserOpenInfo($access_token, $openid)
    {
        $result = \Axios::http()->get('https://graph.qq.com/user/get_user_info', [
            'query' => [
                'openid' => $openid,
                'oauth_consumer_key' => SettingModel::Config('qq_login_appid', false),
                'access_token' => $access_token
            ]
        ]);
        if ($result->getStatusCode() === 200) {
            $content = $result->getBody()->getContents();
            $js = \Axios::toJson($content);
            if ($js['ret'] === 0) {
                UserModel::where('qq_open_id', $openid)->update(['nickname' => $js['nickname'], 'avatar' => $this->downloadFile($js['figureurl_qq_1'])]);
            }
        }
    }

    function UserGroup(): \think\response\Json
    {
        is_demo_mode(true);
        $this->getAdmin();
        return $this->success("ok", UserGroupModel::order("sort", 'desc')->select()->toArray());
    }

    function createGroup(): \think\response\Json
    {
        is_demo_mode(true);
        $type = $this->request->post('type', false);
        $this->getAdmin();
        if ($type === 'edit') {
            $form = $this->request->post('info');
            $id = $this->request->post('info.id', false);
            if ($id && $id > 0) {
                $model = UserGroupModel::find($id);
                $model->update($form);
            } else {
                $model = new UserGroupModel();
                $model->insert($form);
            }
            $defUserGroup = $this->request->post('info.default_user_group', 0);
            if ($defUserGroup) {
                UserGroupModel::where('id', '!=', $id)->update(['default_user_group' => 0]);
                UserGroupModel::where('id', $id)->update(['default_user_group' => 1]);
            }
        } else if ($type === 'del') {
            $id = $this->request->post('id');
            $result = UserGroupModel::where('id', $id)->find();
            if ($result) {
                $result->delete();
                UserModel::where('group_id', $id)->update(['group_id' => 0]);
            }
        }
        return $this->success('处理完毕！');
    }

    function sortGroup(): \think\response\Json
    {
        $this->getAdmin();
        $sort = (array)$this->request->post();
        foreach ($sort as $key => $value) {
            UserGroupModel::where('id', $value['id'])->update(['sort' => $value['sort']]);
        }
        return $this->success('ok');
    }


    function wx_login()
    {
        $code = $this->request->get('code');
        $state = $this->request->get('state');
        $mode = $this->request->get("mode");
        $appid = $this->systemSetting("wx_login_appid");
        $secret = $this->systemSetting("wx_login_appkey");
        $user_id = $this->request->get('user_id');
        $token = $this->request->get('token');
        Log::info($this->request->all());
        if ($code && $state) {
            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code";
            $result = \Axios::http()->get($url);
            if ($result->getStatusCode() === 200) {
                $content = $result->getBody()->getContents();
                $js = \Axios::toJson($content);
                if (isset($js['openid'])) {
                    $info = UserModel::where('wx_open_id', $js['openid'])->find();
                    if ($info) {
                        if ($mode === 'bind') {
                            Cache::set($state, ['type' => 'wx_login', 'status' => 0, 'msg' => '该微信已绑定其他账号']);
                            return '';
                        }
                        $info->login_ip = getRealIp();
                        $info->login_time = date('Y-m-d H:i:s');
                        $info->login_fail_count = 0;
                        $info->save();
                    } else {
                        $userInfo = null;
                        if ($mode === 'bind') {
                            //绑定模式
                            if ($user_id && $token && $auth = TokenModel::where('user_id', $user_id)->where('token', $token)->find()) {
                                $info = UserModel::find($auth->user_id);
                                if ($info) {
                                    $info['wx_open_id'] = $js['openid'];
                                    $info['wx_unionid'] = $js['unionid'];
                                    $info->save();
                                }
                            } else {
                                Cache::set($state, ['type' => 'wx_login', 'status' => 0, 'msg' => '用户不存在']);
                                return '';
                            }
                        } else {
                            $accessToken = $js["access_token"];
                            $openid = $js["openid"];
                            $getUSerInfo = $this->getWxInfo($accessToken, $openid);
                            if ($getUSerInfo) {
                                $table = ['mail' => null, 'password' => md5(time()), 'create_time' => date('Y-m-d H:i:s'), 'login_ip' => getRealIp(), 'register_ip' => getRealIp(), 'wx_open_id' => $js['openid'], 'wx_unionid' => $js['unionid']];
                                if ($getUSerInfo['headimgurl']) {
                                    $table['avatar'] = $this->downloadFile($getUSerInfo['headimgurl']);
                                }
                                $table['nickname'] = $getUSerInfo['nickname'];
                                $table['group_id'] = $this->getDefaultUserGroupId();
                                //非绑定模式下则创建用户
                                UserModel::insert($table);
                                $info = UserModel::where('wx_open_id', $js['openid'])->find();
                            } else {
                                Cache::set($state, ['type' => 'wx_login', 'status' => 2, 'msg' => '获取用户信息失败']);
                                return '';
                            }
                        }
                    }
                    $token = $this->refreshToken($info);
                    Cache::set($state, ['type' => 'wx_login', 'status' => 1, 'msg' => '登录成功', 'mode' => $mode, 'openid' => $info['wx_open_id'], 'id' => $info['id'], 'token' => $token]);
                    return "";
                }
            }
        }
        Cache::set($state, ['type' => 'wx_login', 'status' => 2, 'msg' => '登录登录失败请重新尝试']);
        return "";
    }

    //根据access_token获取用户的一些基本信息
    private function getWxInfo($access_token, $openid)
    {
        $api = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}";
        try {
            $result = \Axios::http()->get($api);
            if ($result->getStatusCode() == 200) {
                $content = $result->getBody()->getContents();
                $js = \Axios::toJson($content);
                //检查$js是否包含openid
                if (!isset($js['openid'])) {
                    return false;
                }
                return $js;
            }
        } catch (GuzzleException $e) {
        }
        return false;
    }

    public function is_wx_login(): \think\response\Json
    {
        $state = $this->request->post("state");

        if ($state) {
            $text = Cache::get($state);
            if ($text) {
                if ($text['type'] == 'wx_login') {
                    if ($text['status'] == 1) {
                        Cache::delete($state);
                        return $this->success('ok', $text);
                    } else if ($text['status'] == 2) {
                        Cache::delete($state);
                        return $this->success("ok", $text);
                    }
                }
            }
        }
        return $this->error('wait');
    }

    private function downloadFile($url)
    {
        $client = \Axios::http();
        $path = '/images/' . date('Y/m/d/');
        $remotePath = public_path() . $path;
        $filename = md5($url);
        $downloadPath = $remotePath . $filename . 'tmp';
        if (!is_dir($remotePath)) {
            mkdir($remotePath, 0755, true);
        }
        try {
            $client->request('GET', $url, [
                'sink' => $downloadPath,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ]
            ]);
            $ext = getFileExtByContent($downloadPath);//返回文件结尾扩展列入png
            $newName = $remotePath . md5($url) . "." . $ext;
            if (file_exists($downloadPath)) {
                rename($downloadPath, $newName);
            }
            return FileModel::addFile($path . $filename . "." . $ext);//添加到文件记录
        } catch (GuzzleException $e) {
            Log::error($e->getMessage());
        }
        return '';
    }
}
