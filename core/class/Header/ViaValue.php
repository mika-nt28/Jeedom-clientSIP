<?php
/**
* Via Header Field Value Class
*/
class ViaValue
{
    /** @var string Via protocol name (i.e. SIP) */
    public $protocol;

    /** @var string Via protocol version (i.e. 2.0) */
    public $version;

    /** @var string Via transport (e.g. UDP, TCP, WSS etc.) */
    public $transport;

    /** @var string Via host */
    public $host;

    /** @var string Via branch parameters */
    public $branch;

    /** @var array<string, string> Additional parameters */
    public $params = [];
}
