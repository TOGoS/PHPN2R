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

### Repository format

The repositories listed in config.php are assumed to be of the following format

```
  <repository directory>/
    data/
      <arbitrary sector name>/
        <first 2 letters of base32-encoded SHA-1>/
          <base32-encoded SHA-1 of file contents>
```

You can have any number of sectors, and their names are not important.
They just act as buckets within which you can organize your data
(e.g. you might put really important stuff in one, less important
stuff somewhere else, data of questionable value in yet another).

## Use as a library

Explicitly not documented, because the API isn't so nice and I'm
probably the only one in the world to whom it is useful, anyway.


## TODO:

Look at http://pablotron.org/software/zipstream-php/ for making zips of directories