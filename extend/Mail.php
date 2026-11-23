<?php

use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;
use \app\model\SettingModel;

class Mail
{
    public static function send($to = "", $text = ""): bool
    {
        $mail = new Message;
        $send_mail = SettingModel::Config('smtp_email', false);
        $option = [
            'port' => SettingModel::Config('smtp_port'),
            'host' => SettingModel::Config('smtp_host', false),
            'username' => SettingModel::Config('smtp_email'),
            'password' => SettingModel::Config('smtp_password'),
        ];
        if (!$send_mail || !$option['host']) {
            return abort(0, "管理员没有配置SMTP邮件服务");
        }
        $mail->setFrom(SettingModel::Config('title', '新标签页') . " <$send_mail>")
            ->addTo($to)
            ->setSubject(SettingModel::Config('title', '新标签页') . '动态令牌')
            ->setHtmlBody($text);
        $ssl = (int)SettingModel::Config('smtp_ssl', '0');
        if (in_array($ssl, [1, 2, 3])) {
            if ($ssl === 2) {
                $option['secure'] = 'ssl';
            }
            if ($ssl === 3) {
                $option['secure'] = 'tls';
            }
        } else {
            if ((int)$option['port'] === 465) {
                $option['secure'] = 'ssl';
            }
            if ((int)$option['port'] === 587) {
                $option['secure'] = 'tls';
            }
        }
        try {
            $mailer = new SmtpMailer($option);
            $mailer->send($mail);
            return true;
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Connection has been closed unexpectedly.') {
                return true;
            }
            throw new \Exception($e->getMessage());
        }
    }

    public static function testMail($to, $config): bool
    {
        $mail = new Message;
        $send_mail = $config['smtp_email'];
        $mail->setFrom("新标签页 <$send_mail>")
            ->addTo($to)
            ->setSubject('新标签页验证令牌')
            ->setHtmlBody("您的验证令牌是：113223");
        $option = [
            'port' => $config['smtp_port'],
            'host' => $config['smtp_host'],
            'username' => $config['smtp_email'],
            'password' => $config['smtp_password'],
        ];
        $ssl = (int)($config['smtp_ssl'] ?? '0');
        if (in_array($ssl, [1, 2, 3])) {
            if ($ssl === 2) {
                $option['secure'] = 'ssl';
            }
            if ($ssl === 3) {
                $option['secure'] = 'tls';
            }
        } else {
            if ((int)$option['port'] === 465) {
                $option['secure'] = 'ssl';
            }
            if ((int)$option['port'] === 587) {
                $option['secure'] = 'tls';
            }
        }
        try {
            $mailer = new SmtpMailer($option);
            $mailer->send($mail);
            return true;
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Connection has been closed unexpectedly.') {
                return true;
            }
            throw new \Exception($e->getMessage());
        }
    }
}
