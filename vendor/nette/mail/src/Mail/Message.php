<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Mail;

use Nette;
use Nette\Utils\Strings;


/**
 * Mail provides functionality to compose and send both text and MIME-compliant multipart email messages.
 *
 * @property   string $subject
 * @property   string $htmlBody
 */
class Message extends MimePart
{
	/** Priority */
	public const
		HIGH = 1,
		NORMAL = 3,
		LOW = 5;

	/** @var array */
	public static $defaultHeaders = [
		'MIME-Version' => '1.0',
		'X-Mailer' => 'Nette Framework',
	];

	/** @var array */
	private $attachments = [];

	/** @var array */
	private $inlines = [];

	/** @var string */
	private $htmlBody = '';


	public function __construct()
	{
		foreach (static::$defaultHeaders as $name => $value) {
			$this->setHeader($name, $value);
		}

		$this->setHeader('Date', date('r'));
	}


	/**
	 * Sets the sender of the message. Email or format "John Doe" <doe@example.com>
	 * @return static
	 */
	public function setFrom(string $email, ?string $name = null)
	{
		$this->setHeader('From', $this->formatEmail($email, $name));
		return $this;
	}


	/**
	 * Returns the sender of the message.
	 */
	public function getFrom(): ?array
	{
		return $this->getHeader('From');
	}


	/**
	 * Adds the reply-to address. Email or format "John Doe" <doe@example.com>
	 * @return static
	 */
	public function addReplyTo(string $email, ?string $name = null)
	{
		$this->setHeader('Reply-To', $this->formatEmail($email, $name), true);
		return $this;
	}


	/**
	 * Sets the subject of the message.
	 * @return static
	 */
	public function setSubject(string $subject)
	{
		$this->setHeader('Subject', $subject);
		return $this;
	}


	/**
	 * Returns the subject of the message.
	 */
	public function getSubject(): ?string
	{
		return $this->getHeader('Subject');
	}


	/**
	 * Adds email recipient. Email or format "John Doe" <doe@example.com>
	 * @return static
	 */
	public function addTo(string $email, ?string $name = null) // addRecipient()
	{
		$this->setHeader('To', $this->formatEmail($email, $name), true);
		return $this;
	}


	/**
	 * Adds carbon copy email recipient. Email or format "John Doe" <doe@example.com>
	 * @return static
	 */
	public function addCc(string $email, ?string $name = null)
	{
		$this->setHeader('Cc', $this->formatEmail($email, $name), true);
		return $this;
	}


	/**
	 * Adds blind carbon copy email recipient. Email or format "John Doe" <doe@example.com>
	 * @return static
	 */
	public function addBcc(string $email, ?string $name = null)
	{
		$this->setHeader('Bcc', $this->formatEmail($email, $name), true);
		return $this;
	}


	/**
	 * Formats recipient email.
	 */
	private function formatEmail(string $email, ?string $name = null): array
	{
		if (!$name && preg_match('#^(.+) +<(.*)>$#D', $email, $matches)) {
			[, $name, $email] = $matches;
			$name = stripslashes($name);
			$tmp = substr($name, 1, -1);
			if ($name === '"' . $tmp . '"') {
				$name = $tmp;
			}
		}

		return [$email => $name];
	}


	/**
	 * Sets the Return-Path header of the message.
	 * @return static
	 */
	public function setReturnPath(string $email)
	{
		$this->setHeader('Return-Path', $email);
		return $this;
	}


	/**
	 * Returns the Return-Path header.
	 */
	public function getReturnPath(): ?string
	{
		return $this->getHeader('Return-Path');
	}


	/**
	 * Sets email priority.
	 * @return static
	 */
	public function setPriority(int $priority)
	{
		$this->setHeader('X-Priority', (string) $priority);
		return $this;
	}


	/**
	 * Returns email priority.
	 */
	public function getPriority(): ?int
	{
		$priority = $this->getHeader('X-Priority');
		return is_numeric($priority) ? (int) $priority : null;
	}


	/**
	 * Sets HTML body.
	 * @return static
	 */
	public function setHtmlBody(string $html, ?string $basePath = null)
	{
		if ($basePath) {
			$cids = [];
			$matches = Strings::matchAll(
				$html,
				'#
					(<img[^<>]*\s src\s*=\s*
					|<body[^<>]*\s background\s*=\s*
					|<[^<>]+\s style\s*=\s* ["\'][^"\'>]+[:\s] url\(
					|<style[^>]*>[^<]+ [:\s] url\()
					(["\']?)(?![a-z]+:|[/\#])([^"\'>)\s]+)
					|\[\[ ([\w()+./@~-]+) \]\]
				#ix',
				PREG_OFFSET_CAPTURE
			);
			foreach (array_reverse($matches) as $m) {
				$file = rtrim($basePath, '/\\') . '/' . (isset($m[4]) ? $m[4][0] : urldecode($m[3][0]));
				if (!isset($cids[$file])) {
					$cids[$file] = substr($this->addEmbeddedFile($file)->getHeader('Content-ID'), 1, -1);
				}

				$html = substr_replace(
					$html,
					"{$m[1][0]}{$m[2][0]}cid:{$cids[$file]}",
					$m[0][1],
					strlen($m[0][0])
				);
			}
		}

		if ($this->getSubject() == null) { // intentionally ==
			$html = Strings::replace($html, '#<title>(.+?)</title>#is', function (array $m): void {
				$this->setSubject(Nette\Utils\Html::htmlToText($m[1]));
			});
		}

		$this->htmlBody = ltrim(str_replace("\r", '', $html), "\n");

		if ($this->getBody() === '' && $html !== '') {
			$this->setBody($this->buildText($html));
		}

		return $this;
	}


	/**
	 * Gets HTML body.
	 */
	public function getHtmlBody(): string
	{
		return $this->htmlBody;
	}


	/**
	 * Adds embedded file.
	 */
	public function addEmbeddedFile(string $file, ?string $content = null, ?string $contentType = null): MimePart
	{
		return $this->inlines[$file] = $this->createAttachment($file, $content, $contentType, 'inline')
			->setHeader('Content-ID', $this->getRandomId());
	}


	/**
	 * Adds inlined Mime Part.
	 * @return static
	 */
	public function addInlinePart(MimePart $part)
	{
		$this->inlines[] = $part;
		return $this;
	}


	/**
	 * Adds attachment.
	 */
	public function addAttachment(string $file, ?string $content = null, ?string $contentType = null): MimePart
	{
		return $this->attachments[] = $this->createAttachment($file, $content, $contentType, 'attachment');
	}


	/**
	 * Gets all email attachments.
	 * @return MimePart[]
	 */
	public function getAttachments(): array
	{
		return $this->attachments;
	}


	/**
	 * Creates file MIME part.
	 */
	private function createAttachment(
		string $file,
		?string $content,
		?string $contentType,
		string $disposition
	): MimePart
	{
		$part = new MimePart;
		if ($content === null) {
			$content = Nette\Utils\FileSystem::read($file);
			$file = Strings::fixEncoding(basename($file));
		}

		if (!$contentType) {
			$contentType = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content);
		}

		if (!strcasecmp($contentType, 'message/rfc822')) { // not allowed for attached files
			$contentType = 'application/octet-stream';
		} elseif (!strcasecmp($contentType, 'image/svg')) { // Troublesome for some mailers...
			$contentType = 'image/svg+xml';
		}

		$part->setBody($content);
		$part->setContentType($contentType);
		$part->setEncoding(preg_match('#(multipart|message)/#A', $contentType) ? self::ENCODING_8BIT : self::ENCODING_BASE64);
		$part->setHeader('Content-Disposition', $disposition . '; filename="' . addcslashes($file, '"\\') . '"');
		return $part;
	}


	/********************* building and sending ****************d*g**/


	/**
	 * Returns encoded message.
	 */
	public function generateMessage(): string
	{
		return $this->build()->getEncodedMessage();
	}


	/**
	 * Builds email. Does not modify itself, but returns a new object.
	 * @return static
	 */
	public function build()
	{
		$mail = clone $this;
		$mail->setHeader('Message-ID', $mail->getHeader('Message-ID') ?? $this->getRandomId());

		$cursor = $mail;
		if ($mail->attachments) {
			$tmp = $cursor->setContentType('multipart/mixed');
			$cursor = $cursor->addPart();
			foreach ($mail->attachments as $value) {
				$tmp->addPart($value);
			}
		}

		if ($mail->htmlBody !== '') {
			$tmp = $cursor->setContentType('multipart/alternative');
			$cursor = $cursor->addPart();
			$alt = $tmp->addPart();
			if ($mail->inlines) {
				$tmp = $alt->setContentType('multipart/related');
				$alt = $alt->addPart();
				foreach ($mail->inlines as $value) {
					$tmp->addPart($value);
				}
			}

			$alt->setContentType('text/html', 'UTF-8')
				->setEncoding(preg_match('#[^\n]{990}#', $mail->htmlBody)
					? self::ENCODING_QUOTED_PRINTABLE
					: (preg_match('#[\x80-\xFF]#', $mail->htmlBody) ? self::ENCODING_8BIT : self::ENCODING_7BIT))
				->setBody($mail->htmlBody);
		}

		$text = $mail->getBody();
		$mail->setBody('');
		$cursor->setContentType('text/plain', 'UTF-8')
			->setEncoding(preg_match('#[^\n]{990}#', $text)
				? self::ENCODING_QUOTED_PRINTABLE
				: (preg_match('#[\x80-\xFF]#', $text) ? self::ENCODING_8BIT : self::ENCODING_7BIT))
			->setBody($text);

		return $mail;
	}


	/**
	 * Builds text content.
	 */
	protected function buildText(string $html): string
	{
		$html = Strings::replace($html, [
			'#<(style|script|head).*</\1>#Uis' => '',
			'#<t[dh][ >]#i' => ' $0',
			'#<a\s[^>]*href=(?|"([^"]+)"|\'([^\']+)\')[^>]*>(.*?)</a>#is' => '$2 &lt;$1&gt;',
			'#[\r\n]+#' => ' ',
			'#<(/?p|/?h\d|li|br|/tr)[ >/]#i' => "\n$0",
		]);
		$text = Nette\Utils\Html::htmlToText($html);
		$text = Strings::replace($text, '#[ \t]+#', ' ');
		$text = implode("\n", array_map('trim', explode("\n", $text)));
		return trim($text);
	}


	private function getRandomId(): string
	{
		return '<' . Nette\Utils\Random::generate() . '@'
			. preg_replace('#[^\w.-]+#', '', $_SERVER['HTTP_HOST'] ?? php_uname('n'))
			. '>';
	}
}
