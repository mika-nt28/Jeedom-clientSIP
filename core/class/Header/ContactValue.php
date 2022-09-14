<?php
/**
* Contact Header Field Value Class
*/
class ContactValue
{
    /** @var string Address portion of the Contact value */
    public $addr;

    /** @var string Display name portion of the Contact value */
    public $name;

    /** @var float Q parameter, if provided */
    public $q;

    /** @var int Expires parameter, if provided */
    public $expires;

    /** @var array<string, string> Additional/extension parameters */
    public $params = [];
}
