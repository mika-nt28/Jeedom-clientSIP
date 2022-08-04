<?php
/**
* Auth Header Field Value Class
*/
class AuthValue
{

    /** @var string Auth digest parameters */
    public $digest;
  
    /** @var string Auth reponse parameters */
    public $reponse;

    /** @var array<string, string> Additional parameters */
    public $params = [];
}
