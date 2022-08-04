<?php
/**
* wwwAuth Header Class
*/
class wwwAuthHeader
{
    /** @var list<AuthValue> wwwAuth value(s) */
    public $values = [];

    final public function __construct() {
          $this->cnonce = md5(time());
    }

    /**
     * Single value header field parser, with parameters
     *
     * @param list<string> $hbody Header body
     * @throws InvalidHeaderLineException
     * @throws InvalidHeaderParameter
     * @return wwwAuthHeader
     */
    public static function parse(array $hbody): wwwAuthHeader
    {
        $ret = new static;
        foreach ($hbody as $hline) {
           $hvalues = explode(' ', trim($hline));
            $val = new AuthValue;
            if (count($hvalues) != 2) {
                throw new InvalidHeaderLineException('Invalid wwwAuth header', Response::BAD_REQUEST);
            }
            $val->digest = $hvalues[0];
            $vparams = explode(',', $hvalues[1]);
            foreach ($vparams as $param) {
                $p = explode('=', $param);
                $p[0] = trim($p[0]);
                if (!isset($p[0][0])) {
                    throw new InvalidHeaderParameter('Empty header parameters', Response::BAD_REQUEST);
                }
                $pv = isset($p[1]) ? trim($p[1]) : '';
                $val->params[$p[0]] = str_replace('"','',$pv);
            }
            $ret->values[] = $val;
        }
        return $ret;
    }
   /**
     * wwwAuthHeader header field value reponse
     *
     * @param string $hname Header field name
     * @throws InvalidHeaderValue
     * @return string
     */
    public function reponse($params): string
    {
        $ha1 = md5($params['username'].':'.$params['realm'].':'.$params['password']);		
        $ha2 = md5($params['method'].':'.$params['uri']);
        if(isset($params['qop']))
          $res = md5($ha1.':'.$params['nonce'].':00000001:'.$this->cnonce.':auth:'.$ha2);
        else
         $res = md5($ha1.':'.$params['nonce'].':'.$ha2);		
        return $res;
    }
    /**
     * wwwAuthHeader header field value renderer
     *
     * @param string $hname Header field name
     * @throws InvalidHeaderValue
     * @return string
     */
    public function render(string $hname): string
    {
        $ret = "{$hname}: ";
        $delim = '';
        foreach ($this->values as $key => $value) {
            $value->reponse = $this->reponse($value->params);
            if (!isset($value->digest, $value->reponse)) 
                throw new InvalidHeaderValue('Malformed wwwAuthHeader header');
            $ret .= "{$delim}{$value->digest}"; 
            $delim = ' ';

            $ret .="{$delim}"; 
            $delim = ', ';
            $ret .="username=\"{$value->params['username']}\"{$delim}"; 
            $ret .="realm=\"{$value->params['realm']}\"{$delim}"; 
            $ret .="nonce=\"{$value->params['nonce']}\"{$delim}"; 
            $ret .="uri=\"{$value->params['uri']}\"{$delim}"; 
            $ret .="response=\"{$value->reponse}\"{$delim}"; 
            $ret .="algorithm=\"{$value->params['algorithm']}\"";
			if(isset($value->params['qop'])){
                $ret .=$delim; 
                $ret .="qop=\"{$value->params['qop']}\"{$delim}"; 
                $ret .="nc=\"00000001\"{$delim}"; 
                $ret .="cnonce=\"{$this->cnonce}\""; 
            }
        }
        return $ret . "\r\n";
    }
}
