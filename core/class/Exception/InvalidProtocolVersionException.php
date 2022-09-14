<?php
/**
* Exception thrown when processing SIP Messages with a version different than SIP/2.0 (RFC3261 Section 7)
*/
class InvalidProtocolVersionException extends SIPException
{
}
