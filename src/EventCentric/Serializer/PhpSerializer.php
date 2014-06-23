<?php

namespace EventCentric\Serializer;

use EventCentric\Contracts\Contract;
use EventCentric\DomainEvents\DomainEvent;

final class PhpSerializer implements Serializer
{
    public function serialize(DomainEvent $domainEvent)
    {
        return serialize($domainEvent);
    }

    /**
     * @param \EventCentric\Contracts\Contract $contract
     * @param $data
     * @return DomainEvent
     */
    public function unserialize(Contract $contract, $data)
    {
        return unserialize($data);
    }
} 