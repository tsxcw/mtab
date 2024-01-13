<?php

use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;
use \app\model\SettingModel;

class Mail
{
    public static function send($to = "", $text = ""): bool
    {
        $mail = new Message;
        $send_mail = SettingModel::Config('smtp_email');
        $mail->setFrom(SettingModel::Config('title','')." <$send_mail>")
            ->addTo($to)
            ->setSubject(SettingModel::Config('title','') . '动态令牌')
            ->setHtmlBody($text);
        $mailer = new SmtpMailer([
            'port' => SettingModel::Config('smtp_port'),
            'host' => SettingModel::Config('smtp_host'),
            'username' => SettingModel::Config('smtp_email'),
            'password' => SettingModel::Config('smtp_password'),
            'secure' => 'ssl',
        ]);
        try {
            $mailer->send($mail);
        } catch (\Throwable $th) {
            return false;
        }
        return true;
    }
}
