<?php

declare(strict_types=1);

namespace PHPSTORM_META;

expectedArguments(\Nette\Mail\Message::setPriority(), 0, \Nette\Mail\Message::HIGH, \Nette\Mail\Message::NORMAL, \Nette\Mail\Message::LOW);
expectedReturnValues(\Nette\Mail\Message::getPriority(), \Nette\Mail\Message::HIGH, \Nette\Mail\Message::NORMAL, \Nette\Mail\Message::LOW);
