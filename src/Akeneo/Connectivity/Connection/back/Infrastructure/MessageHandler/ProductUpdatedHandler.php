<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Infrastructure\MessageHandler;

use Akeneo\Pim\Enrichment\Bundle\Message\ProductUpdated;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

class ProductUpdatedHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        yield ProductUpdated::class => [
            'from_transport' => 'async',
        ];
    }

    public function __invoke(ProductUpdated $message)
    {
    }
}
