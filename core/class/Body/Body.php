<?php
/**
* Body Class
*/

require_once  dirname(__FILE__) .'/SessionNameBody.php';
require_once  dirname(__FILE__) .'/SessionConnexionBody.php';
require_once  dirname(__FILE__) .'/SessionActiveTime.php';
require_once  dirname(__FILE__) .'/SessonMediaDescriptionBody.php';

class Body
{
    /** @var list<string> Generic body field values */
    public $values = [];

    final public function __construct() {}

    /**
     * Generic header value parser
     *
     * @param list<string> $body Body body
     * @return Header
     */
    public static function parse(array $body): Body
    {
        $ret = new static;

        foreach ($body as $line) {
            $ret->values[] = trim($line);
        }

        return $ret;
    }

    /**
     * Generic body value renderer
     *
     * @param string $bname Body field name
     * @return string
     */
    public function render(string $bname): string
    {
        $ret = '';

        foreach ($this->values as $value) {
            $ret .= "{$bname}: {$value}\r\n";
        }

        return $ret;
    }
}
