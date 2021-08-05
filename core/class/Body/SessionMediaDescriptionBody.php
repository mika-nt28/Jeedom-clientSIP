<?php
/**
* Sesson Media Description Body Class
*/
class SessionMediaDescriptionBody
{
/** @var string Body field value */
    public $type;
    public $port;
    public $protocol;
    public $codec;

    final public function __construct() {}

    /**
     * Sesson Connexion body field parser
     *
     * @param list<string> $hbody body
     * @throws InvalidDuplicateHeader
     * @throws InvalidHeaderLineException
     * @return SessionConnexionBody
     */
    public static function parse(array $bbody): SessionMediaDescriptionBody
    {
      	$ret = [];
        foreach($bbody as $cbbody){

          $tok = explode(' ',trim($cbbody));
            if ($tok === false) {
                throw new InvalidHeaderLineException('Empty body value', Response::BAD_REQUEST);
            }
            $media = new static;
            $media->type = $tok[0];
            $media->port = $tok[1];
            $media->protocol = $tok[2];
            $media->codec = array_slice($tok,3);
            $ret[]=$media;
        }
        return $ret;
    }

    /**
     * Sesson Connexion body renderer, with optional parameters
     *
     * @param string $hname body field name
     * @throws InvalidHeaderValue
     * @return string
     */
    public function render(string $bname): string
    {
        if (!isset($this->networkType, $this->adresseType, $this->adresse)) {
            throw new InvalidHeaderValue('Missing body field value for body: ' . $bname);
        }
        $ret = "{$hname}: {$this->type} {$this->port} {$this->protocol} {$this->codec}";
        $ret .= "\r\n";
        return $ret;
    }
}
