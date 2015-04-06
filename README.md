# PHPN2R

Serves blobs identified by urn:sha1 and urn:bitprint URNs via the
/uri-res/N2R?(URN) convention (see [RFC 2169](https://www.ietf.org/rfc/rfc2169.txt)).

Can be used as a library or by itself.

## Installation as stand-alone script

1. Check this project out to a directory under your document root called 'uri-res'.
2. Copy config.php.example to config.php and edit it.
3. That's it.

ext-lib contains classes from other libraries so that you don't need
to ``composer install``` to use PHPN2R by itself.

## Use as a library

Explicitly not documented, because the API isn't so nice and I'm
probably the only one in the world to whom it is useful, anyway.
