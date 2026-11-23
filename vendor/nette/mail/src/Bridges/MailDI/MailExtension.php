<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Bridges\MailDI;

use Nette;
use Nette\Schema\Expect;


/**
 * Mail extension for Nette DI.
 */
class MailExtension extends Nette\DI\CompilerExtension
{
	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::structure([
			'smtp' => Expect::bool(false),
			'host' => Expect::string()->dynamic(),
			'port' => Expect::int()->dynamic(),
			'username' => Expect::string()->dynamic(),
			'password' => Expect::string()->dynamic(),
			'secure' => Expect::anyOf(null, 'ssl', 'tls')->dynamic(), // deprecated
			'encryption' => Expect::anyOf(null, 'ssl', 'tls')->dynamic(),
			'timeout' => Expect::int()->dynamic(),
			'context' => Expect::arrayOf('array')->dynamic(),
			'clientHost' => Expect::string()->dynamic(),
			'persistent' => Expect::bool(false)->dynamic(),
			'dkim' => Expect::anyOf(
				Expect::null(),
				Expect::structure([
					'domain' => Expect::string()->dynamic(),
					'selector' => Expect::string()->dynamic(),
					'privateKey' => Expect::string()->required(),
					'passPhrase' => Expect::string()->dynamic(),
				])->castTo('array')
			),
		])->castTo('array');
	}


	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$mailer = $builder->addDefinition($this->prefix('mailer'))
			->setType(Nette\Mail\Mailer::class);

		if ($this->config['dkim']) {
			$dkim = $this->config['dkim'];
			$dkim['privateKey'] = Nette\Utils\FileSystem::read($dkim['privateKey']);
			unset($this->config['dkim']);

			$signer = $builder->addDefinition($this->prefix('signer'))
				->setType(Nette\Mail\Signer::class)
				->setFactory(Nette\Mail\DkimSigner::class, [$dkim]);

			$mailer->addSetup('setSigner', [$signer]);
		}

		if ($this->config['smtp']) {
			$this->config['secure'] = $this->config['encryption'] ?? $this->config['secure'];
			$mailer->setFactory(Nette\Mail\SmtpMailer::class, [$this->config]);
		} else {
			$mailer->setFactory(Nette\Mail\SendmailMailer::class);
		}

		if ($this->name === 'mail') {
			$builder->addAlias('nette.mailer', $this->prefix('mailer'));
		}
	}
}
