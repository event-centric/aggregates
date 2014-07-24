<?php

namespace EventCentric\Contracts;

use Assert;

/**
 * The label for a contract describing the shape of a message or domain object.
 * When two systems need to communicate, they need a common understanding of what the message looks like.
 * This understanding is a contract, represented by this Contract object.
 */
final class Contract
{
    /**
     * @var string
     */
    private $contractName;

    private function __construct($contract)
    {
        $this->contractName = $contract;
    }

    /**
     * Make a contract with a given name, for when you have string representation of a contract.
     * @param string $name
     * @return Contract
     */
    public static function with($name)
    {
        Assert\that($name)->string()->betweenLength(1, 255);
        return new static($name);
    }

    /**
     * Make a contract from an fully qualified class name, of the form My.Namespace.Class
     * @param string $className
     * @return Contract
     */
    public static function canonicalFrom($className)
    {
        return new Contract(
            str_replace('\\', '.', $className)
        );
    }

    /**
     * @return string Fully Qualified Class Name (FQCN) of the message or object
     */
    public function toClassName()
    {
        return str_replace('.', '\\', $this->contractName);
    }

    public function __toString()
    {
        return $this->contractName;
    }

    public function equals(Contract $other)
    {
        return $this->contractName == $other->contractName;
    }

} 