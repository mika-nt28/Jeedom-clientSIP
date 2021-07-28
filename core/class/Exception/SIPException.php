<?php
require_once  dirname(__FILE__) .'/InvalidBodyLengthException.php';
require_once  dirname(__FILE__) .'/InvalidCSeqValue.php';
require_once  dirname(__FILE__) .'/InvalidDuplicateHeader.php';
require_once  dirname(__FILE__) .'/InvalidHeaderLineException.php';
require_once  dirname(__FILE__) .'/InvalidHeaderParameter.php';
require_once  dirname(__FILE__) .'/InvalidHeaderSectionException.php';
require_once  dirname(__FILE__) .'/InvalidHeaderValue.php';
require_once  dirname(__FILE__) .'/InvalidMessageStartLineException.php';
require_once  dirname(__FILE__) .'/InvalidProtocolVersionException.php';
require_once  dirname(__FILE__) .'/InvalidRequestMethod.php';
require_once  dirname(__FILE__) .'/InvalidRequestURI.php';
require_once  dirname(__FILE__) .'/InvalidScalarValue.php';
require_once  dirname(__FILE__) .'/InvalidStatusCodeException.php';
/**
* Generic SIP exception
*/
class SIPException extends DomainException implements Throwable
{
}
