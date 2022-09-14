<?php
/**
* Sesson Connexion Body Class
*/
class SessionConnexionBody
{
/** @var string Body field value */
    public $networkType;
    public $adresseType;
    public $adresse;

    final public function __construct() {}

    /**
     * Sesson Connexion body field parser
     *
     * @param list<string> $hbody body
     * @throws InvalidDuplicateHeader
     * @throws InvalidHeaderLineException
     * @return SessionConnexionBody
     */
    public static function parse(array $bbody): SessionConnexionBody
    {
      	$ret = [];
        foreach($bbody as $cbbody){

          $tok = explode(' ',trim($cbbody));

            if ($tok === false) {
                throw new InvalidHeaderLineException('Empty body value', Response::BAD_REQUEST);
            }

            $con = new static;
            $con->networkType = $tok[0];
            $con->adresseType = $tok[1];
            $con->adresse = $tok[2];
		    $ret[]=$con;
        
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

        $ret = "{$hname}: {$this->networkType} {$this->adresseType} {$this->adresse}";

        $ret .= "\r\n";

        return $ret;
    }
}
