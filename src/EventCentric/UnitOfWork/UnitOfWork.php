<?php

namespace EventCentric\UnitOfWork;

use EventCentric\AggregateRoot\ReconstitutesFromHistory;
use EventCentric\AggregateRoot\TracksChanges;
use EventCentric\EventStore\CommitId;
use EventCentric\Contracts\Contract;
use EventCentric\DomainEvents\DomainEvent;
use EventCentric\DomainEvents\DomainEventsArray;
use EventCentric\EventStore\EventEnvelope;
use EventCentric\EventStore\EventId;
use EventCentric\EventStore\EventStore;
use EventCentric\Identity\Identity;
use EventCentric\Serializer\DomainEventSerializer;
use iter as _;

/**
 * A Unit of Work will keep track of one or more Aggregates.
 * When the Unit of Work is committed, the changes will be persisted using a single commit for each Aggregate.
 * A UnitOfWork can also reconstitute an Aggregate from the Event Store.
 */
final class UnitOfWork
{
    /**
     * @var \EventCentric\EventStore\EventStore
     */
    private $eventStore;

    /**
     * @var DomainEventSerializer
     */
    private $serializer;

    /**
     * @var AggregateRootReconstituter
     */
    private $aggregateRootReconstituter;

    private $trackedAggregates  = [];

    public function __construct(
        EventStore $eventStore,
        DomainEventSerializer $serializer,
        AggregateRootReconstituter $aggregateRootReconstituter
    )
    {
        $this->eventStore = $eventStore;
        $this->serializer = $serializer;
        $this->aggregateRootReconstituter = $aggregateRootReconstituter;
    }

    /**
     * Track a newly created AggregateRoot
     *
     * @param Contract $aggregateContract
     * @param Identity $aggregateId
     * @param TracksChanges $aggregateRoot
     * @throws AggregateRootIsAlreadyBeingTracked
     */
    public function track(Contract $aggregateContract, Identity $aggregateId, TracksChanges $aggregateRoot)
    {
        $aggregate = new Aggregate($aggregateContract, $aggregateId, $aggregateRoot);

        $alreadyTracked =
            _\any(
                function(Aggregate $foundAggregate) use($aggregate){ return $aggregate->equals($foundAggregate); },
                $this->trackedAggregates
            );

        if($alreadyTracked) {
            throw AggregateRootIsAlreadyBeingTracked::identifiedBy($aggregateContract, $aggregateId);
        }

        $this->trackedAggregates[] = $aggregate;
    }

    /**
     * @param Contract $aggregateContract
     * @param Identity $aggregateId
     * @return ReconstitutesFromHistory
     */
    public function get(Contract $aggregateContract, Identity $aggregateId)
    {
        $streamId = $aggregateId;
        $stream = $this->eventStore->openStream($aggregateContract, $streamId);

        $eventEnvelopes = $stream->all();

        $unwrapFromEnvelope = function (EventEnvelope $eventEnvelope) {
            $domainEvent = $this->serializer->unserialize(
                $eventEnvelope->getEventContract(),
                $eventEnvelope->getEventPayload()
            );
            return $domainEvent;
        };

        $domainEvents = new DomainEventsArray(
            array_map($unwrapFromEnvelope, $eventEnvelopes)
        );

        $aggregateRoot = $this->aggregateRootReconstituter->reconstitute($aggregateContract, $domainEvents);
        return $aggregateRoot;
    }

    /**
     * Persist each tracked Aggregate.
     */
    public function commit()
    {
        foreach($this->trackedAggregates as $aggregate) {
            $this->persistAggregate($aggregate);
        }
    }

    /**
     * @param Aggregate $aggregate
     * @todo happens if there are no changes to an Aggregate?
     */
    private function persistAggregate(Aggregate $aggregate)
    {
        $stream = $this->eventStore->createStream($aggregate->getAggregateContract(), $aggregate->getAggregateId());

        $domainEvents = $aggregate->getChanges();

        $wrapInEnvelope = function (DomainEvent $domainEvent) {
            $eventContract = Contract::canonicalFrom(get_class($domainEvent));
            $payload = $this->serializer->serialize($eventContract, $domainEvent);
            return EventEnvelope::wrap(EventId::generate(), $eventContract, $payload);
        };

        $envelopes = $domainEvents->map($wrapInEnvelope);

        $stream->appendAll($envelopes);
        $stream->commit(CommitId::generate());
    }
} 