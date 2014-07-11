<?php

// This script is provided as an alternate way
// (alternative to the 'raw', 'browse', and 'N2R' scripts)
// to handle any browse/..., raw/..., N2R/... requests.
// 
// It also demonstrates the handleRequest function,
// which may be useful if you're using this project
// as a library from another.

$res = require 'setup.php';
Nife_Util::outputResponse($res->handleRequest($_SERVER['PATH_INFO']));
