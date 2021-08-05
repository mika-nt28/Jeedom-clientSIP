<?php
/**
* ProxyAuth Header Field Value Class
*/
class ProxyAuthValue
{

    /** @var string ProxyAuth digest parameters */
    public $digest;
  
    /** @var string ProxyAuth reponse parameters */
    public $reponse;

    /** @var array<string, string> Additional parameters */
    public $params = [];
}
