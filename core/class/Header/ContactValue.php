<?php
/**
* Contact Header Field Value Class
*/
class ContactValue
{
    /** @var string Address portion of the Contact value */
    public string $addr;

    /** @var string Display name portion of the Contact value */
    public string $name;

    /** @var float Q parameter, if provided */
    public float $q;

    /** @var int Expires parameter, if provided */
    public int $expires;

    /** @var array<string, string> Additional/extension parameters */
    public array $params = [];
}
