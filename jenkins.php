<?php

include_once 'settings.php';
include_once 'parameters.php';
include_once 'lib/lib.php';
include_once 'lib/suite-item.php';
include_once 'lib/sub-build.php';

$results = getResults($builds, $tasks);
file_put_contents('results.txt', formatResults($results));
