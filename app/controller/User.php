<?php


namespace app\controller;

use app\BaseController;
use app\model\TokenModel;
use app\model\UserModel;
use think\facade\Cache;

class User extends BaseController
{
    public function login(): \think\response\Json
    {
        $user = $this->request->post('username', '0');
        $pass = $this->request->post('password', '0');
        $info = UserModel::where("mail", $user)->field('id,mail,password,login_fail_count,login_ip,login_time')->find();

        if (Cache::get('login.' . $user)) {
            return $this->error("账号已被安全锁定,您可以修改密码然后登录");
        }
        if (!$info) {
            return $this->error("账号不存在");
        }
        if ($info['login_fail_count'] == 10) {
            Cache::set('login.' . $user, 'lock', 7200);
            $info->login_fail_count = 0;
            $info->save();
            return $this->error("账号已被锁定2小时");
        }
        if ($info['password'] != md5($pass)) {
            $info->login_fail_count += 1;
            $info->save();
            return $this->error("账号不存在或密码错误");
        }
        $token = renderToken($user);
        $agent = $this->request->header("User-Agent");
        $agent = mb_substr($agent, 0, 250);
        $auth = ["user_id" => $info['id'], 'token' => $token, 'create_time' => time(), 'ip' => $this->request->ip(), 'user_agent' => $agent];
        $add = TokenModel::insert($auth);
        unset($auth['user_agent']);
        unset($auth['ip']);
        $info->login_ip = $this->request->ip();
        $info->login_time = date("Y-m-d H:i:s");
        $info->login_fail_count = 0;//登陆成功将失败次数归零
        $info->save();
        return $this->success("登录成功", $auth);
    }

    function register(): \think\response\Json
    {
        $user = $this->request->post('username', false);
        $pass = $this->request->post('password', false);
        $code = $this->request->post('code', '0000');
        if ($user && $pass) {
            if (!validateEmail($user)) {
                return $this->error("邮箱格式错误");
            }
            if (strlen($pass) < 6) {
                return $this->error("密码过短");
            }
            $cacheCode = Cache::get("code" . $user);
            if (!$cacheCode && $cacheCode != $code) {
                return $this->error('验证码错误');
            }
            if (UserModel::where("mail", $user)->field("id,mail")->find()) {
                return $this->error("账号已存在");
            }
            $add = UserModel::insert(["mail" => $user, "password" => md5($pass), "create_time" => date('Y-m-d H:i:s'),'register_ip'=>$this->request->ip()]);
            if ($add) {
                Cache::delete("code" . $user);
                return $this->success("ok");
            }
        }
        return $this->error('注册失败');
    }

    public function forgetPass(): \think\response\Json
    {
        $user = $this->request->post('username', false);
        $pass = $this->request->post('password', false);
        $code = $this->request->post('code', '0000');
        if ($user && $pass) {
            if (!validateEmail($user)) {
                return $this->error("邮箱格式错误");
            }
            if (strlen($pass) < 6) {
                return $this->error("密码过短");
            }
            $info = UserModel::where("mail", $user)->field("id,mail")->find();
            if (!$info) {
                return $this->error("账号不存在");
            }
            $cacheCode = Cache::get("code" . $user);
            if ($cacheCode && $cacheCode == $code) {
                $info->password = md5($pass);
                $add = $info->save();
                if ($add) {
                    TokenModel::where("user_id", $info['id'])->delete(); //删除所有登录记录
                    Cache::delete('login.' . $user);
                    return $this->success("ok");
                }
            } else {
                return $this->error('验证码错误');
            }
        }
        return $this->error('修改失败');
    }

    public function get(): \think\response\Json
    {
        $info = $this->getUser(true);
        if ($info) {
            $info = UserModel::field('id,mail,manager')->find($info['user_id']);
            return $this->success('ok', $info);
        }
        return $this->error('获取失败');
    }
}
