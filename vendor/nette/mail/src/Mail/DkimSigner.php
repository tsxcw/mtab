<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Mail;

use Nette;


class DkimSigner implements Signer
{
	use Nette\SmartObject;

	private const DefaultSignHeaders = [
		'From',
		'To',
		'Date',
		'Subject',
		'Message-ID',
		'X-Mailer',
		'Content-Type',
	];

	private const DkimSignature = 'DKIM-Signature';

	/** @var string */
	private $domain;

	/** @var array */
	private $signHeaders;

	/** @var string */
	private $selector;

	/** @var string */
	private $privateKey;

	/** @var string */
	private $passPhrase;


	/** @throws Nette\NotSupportedException */
	public function __construct(array $options, array $signHeaders = self::DefaultSignHeaders)
	{
		if (!extension_loaded('openssl')) {
			throw new Nette\NotSupportedException('DkimSigner requires PHP extension openssl which is not loaded.');
		}

		$this->domain = $options['domain'] ?? '';
		$this->selector = $options['selector'] ?? '';
		$this->privateKey = $options['privateKey'] ?? '';
		$this->passPhrase = $options['passPhrase'] ?? '';
		$this->signHeaders = count($signHeaders) > 0
			? $signHeaders
			: self::DefaultSignHeaders;
	}


	/** @throws SignException */
	public function generateSignedMessage(Message $message): string
	{
		$message = $message->build();

		if (preg_match("~(.*?\r\n\r\n)(.*)~s", $message->getEncodedMessage(), $parts)) {
			[, $header, $body] = $parts;

			return rtrim($header, "\r\n") . "\r\n" . $this->getSignature($message, $header, $this->normalizeNewLines($body)) . "\r\n\r\n" . $body;
		}

		throw new SignException('Malformed email');
	}


	protected function getSignature(Message $message, string $header, string $body): string
	{
		$parts = [];
		foreach (
			[
				'v' => '1',
				'a' => 'rsa-sha256',
				'q' => 'dns/txt',
				'l' => strlen($body),
				's' => $this->selector,
				't' => $this->getTime(),
				'c' => 'relaxed/simple',
				'h' => implode(':', $this->getSignedHeaders($message)),
				'd' => $this->domain,
				'bh' => $this->computeBodyHash($body),
				'b' => '',
			] as $key => $value
		) {
			$parts[] = $key . '=' . $value;
		}

		return $this->computeSignature($header, self::DkimSignature . ': ' . implode('; ', $parts));
	}


	protected function computeSignature(string $rawHeader, string $signature): string
	{
		$selectedHeaders = array_merge($this->signHeaders, [self::DkimSignature]);

		$rawHeader = preg_replace("/\r\n[ \t]+/", ' ', rtrim($rawHeader, "\r\n") . "\r\n" . $signature);

		$parts = [];
		foreach ($test = explode("\r\n", $rawHeader) as $key => $header) {
			if (strpos($header, ':') !== false) {
				[$heading, $value] = explode(':', $header, 2);

				if (($index = array_search($heading, $selectedHeaders, true)) !== false) {
					$parts[$index] =
						trim(strtolower($heading), " \t") . ':' .
						trim(preg_replace("/[ \t]{2,}/", ' ', $value), " \t");
				}
			}
		}

		ksort($parts);

		return $signature . $this->sign(implode("\r\n", $parts));
	}


	/** @throws SignException */
	protected function sign(string $value): string
	{
		$privateKey = openssl_pkey_get_private($this->privateKey, $this->passPhrase);
		if (!$privateKey) {
			throw new SignException('Invalid private key');
		}

		if (openssl_sign($value, $signature, $privateKey, 'sha256WithRSAEncryption')) {
			if (PHP_VERSION_ID < 80000) {
				openssl_pkey_free($privateKey);
			}

			return base64_encode($signature);
		}

		if (PHP_VERSION_ID < 80000) {
			openssl_pkey_free($privateKey);
		}

		return '';
	}


	protected function computeBodyHash(string $body): string
	{
		return base64_encode(
			pack(
				'H*',
				hash('sha256', $body)
			)
		);
	}


	protected function normalizeNewLines(string $s): string
	{
		$s = str_replace(["\r\n", "\n"], "\r", $s);
		$s = str_replace("\r", "\r\n", $s);
		return rtrim($s, "\r\n") . "\r\n";
	}


	protected function getSignedHeaders(Message $message): array
	{
		return array_filter($this->signHeaders, function ($name) use ($message) {
			return $message->getHeader($name) !== null;
		});
	}


	protected function getTime(): int
	{
		return time();
	}
}
