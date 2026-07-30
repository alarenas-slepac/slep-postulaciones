<?php
header('Content-Type: text/plain; charset=utf-8');
echo "php=".PHP_VERSION.PHP_EOL;
echo "sapi=".PHP_SAPI.PHP_EOL;
echo "fileinfo=".(extension_loaded('fileinfo')?'ON':'OFF').PHP_EOL;
echo "sodium=".(extension_loaded('sodium')?'ON':'OFF').PHP_EOL;
