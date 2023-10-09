Nette Mail: Sending E-mails
===========================

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/mail.svg)](https://packagist.org/packages/nette/mail)
[![Tests](https://github.com/nette/mail/workflows/Tests/badge.svg?branch=master)](https://github.com/nette/mail/actions)
[![Coverage Status](https://coveralls.io/repos/github/nette/mail/badge.svg?branch=master)](https://coveralls.io/github/nette/mail?branch=master)
[![Latest Stable Version](https://poser.pugx.org/nette/mail/v/stable)](https://github.com/nette/mail/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/nette/mail/blob/master/license.md)


Introduction
------------

Are you going to send emails such as newsletters or order confirmations? Nette Framework provides the necessary tools with a very nice API.

Documentation can be found on the [website](https://doc.nette.org/mailing).


[Support Me](https://github.com/sponsors/dg)
--------------------------------------------

Do you like Nette Mail? Are you looking forward to the new features?

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!


Installation
------------

```shell
composer require nette/mail
```

It requires PHP version 7.1 and supports PHP up to 8.2.


Creating Emails
===============

Email is a [Nette\Mail\Message](https://api.nette.org/3.0/Nette/Mail/Message.html) object:

```php
$mail = new Nette\Mail\Message;
$mail->setFrom('John <john@example.com>')
	->addTo('peter@example.com')
	->addTo('jack@example.com')
	->setSubject('Order Confirmation')
	->setBody("Hello, Your order has been accepted.");
```

All parameters must be encoded in UTF-8.

In addition to specifying recipients with the `addTo()` method, you can also specify the recipient of copy with `addCc()`, or the recipient of blind copy with `addBcc()`. All these methods, including `setFrom()`, accepts addressee in three ways:

```php
$mail->setFrom('john.doe@example.com');
$mail->setFrom('john.doe@example.com', 'John Doe');
$mail->setFrom('John Doe <john.doe@example.com>');
```

The body of an email written in HTML is passed using the `setHtmlBody()` method:

```php
$mail->setHtmlBody('<p>Hello,</p><p>Your order has been accepted.</p>');
```

You don't have to create a text alternative, Nette will generate it automatically for you. And if the email does not have a subject set, it will be taken from the `<title>` element.

Images can also be extremely easily inserted into the HTML body of an email. Just pass the path where the images are physically located as the second parameter, and Nette will automatically include them in the email:

```php
// automatically adds /path/to/images/background.gif to the email
$mail->setHtmlBody(
	'<b>Hello</b> <img src="background.gif">',
	'/path/to/images'
);
```

The image embedding algorithm supports the following patterns: `<img src=...>`, `<body background=...>`, `url(...)` inside the HTML attribute `style` and special syntax `[[...]]`.

Can sending emails be even easier?

Emails are like postcards. Never send passwords or other credentials via email.



Attachments
-----------

You can, of course, attach attachments to email. Use the `addAttachment(string $file, string $content = null, string $contentType = null)`.

```php
// inserts the file /path/to/example.zip into the email under the name example.zip
$mail->addAttachment('/path/to/example.zip');

// inserts the file /path/to/example.zip into the email under the name info.zip
$mail->addAttachment('info.zip', file_get_contents('/path/to/example.zip'));

// attaches new example.txt file contents "Hello John!"
$mail->addAttachment('example.txt', 'Hello John!');
```


Templates
---------

If you send HTML emails, it's a great idea to write them in the [Latte](https://latte.nette.org) template system. How to do it?

```php
$latte = new Latte\Engine;
$params = [
	'orderId' => 123,
];

$mail = new Nette\Mail\Message;
$mail->setFrom('John <john@example.com>')
	->addTo('jack@example.com')
	->setHtmlBody(
		$latte->renderToString('email.latte', $params),
		'/path/to/images'
	);
```

File `email.latte`:

```html
<html>
<head>
	<meta charset="utf-8">
	<title>Order Confirmation</title>
	<style>
	body {
		background: url("background.png")
	}
	</style>
</head>
<body>
	<p>Hello,</p>

	<p>Your order number {$orderId} has been accepted.</p>
</body>
</html>
```

Nette automatically inserts all images, sets the subject according to the `<title>` element, and generates text alternative for HTML body.



Sending Emails
==============

Mailer is class responsible for sending emails. It implements the [Nette\Mail\Mailer](https://api.nette.org/3.0/Nette/Mail/Mailer.html) interface and several ready-made mailers are available which we will introduce.



SendmailMailer
--------------

The default mailer is SendmailMailer which uses PHP function `mail()`. Example of use:

```php
$mailer = new Nette\Mail\SendmailMailer;
$mailer->send($mail);
```

If you want to set `returnPath` and the server still overwrites it, use `$mailer->commandArgs = '-fmy@email.com'`.


SmtpMailer
----------

To send mail via the SMTP server, use `SmtpMailer`.

```php
$mailer = new Nette\Mail\SmtpMailer([
	'host' => 'smtp.gmail.com',
	'username' => 'franta@gmail.com',
	'password' => '*****',
	'secure' => 'ssl',
]);
$mailer->send($mail);
```

If you do not specify `host`, the value from php.ini will be used. The following additional keys can be used in the options:

* `port` - if not set, the default 25 or 465 for `ssl` will be used
* `context` - allows you to set [SSL context options](https://www.php.net/manual/en/context.ssl.php) for connection
* `timeout` - timeout for SMTP connection
* `persistent` - use persistent connection
* `clientHost` - client designation


FallbackMailer
--------------

It does not send email but sends them through a set of mailers. If one mailer fails, it repeats the attempt at the next one. If the last one fails, it starts again from the first one.

```php
$mailer = new Nette\Mail\FallbackMailer([
	$smtpMailer,
	$backupSmtpMailer,
	$sendmailMailer
]);
$mailer->send($mail);
```

Other parameters in the constructor include the number of repeat and waiting time in miliseconds.


DKIM
====

DKIM (DomainKeys Identified Mail) is a trustworthy email technology that also helps detect spoofed messages. The sent message is signed with the private key of the sender's domain and this signature is stored in the email header.
The recipient's server compares this signature with the public key stored in the domain's DNS records. By matching the signature, it is shown that the email actually originated from the sender's domain and that the message was not modified during the transmission of the message.

```php
$options = [
	'domain' => 'nette.org',
	'selector' => 'dkim',
	'privateKey' => file_get_contents('../dkim/dkim.key'),
	'passPhrase' => '****',
];

$mailer = new Nette\Mail\SendmailMailer; // or SmtpMailer
$mailer->setSigner(new Nette\Mail\DkimSigner($options));
$mailer->send($mail);
```
