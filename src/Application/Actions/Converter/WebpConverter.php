<?php

declare(strict_types=1);


use App\Application\Actions\Action;
use Psr\Log\LoggerInterface;

abstract class WebpConverter extends Action
{

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
    }
}
