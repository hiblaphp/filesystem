<?php

use Hibla\Filesystem\File;

require 'vendor/autoload.php';

File::copy('testfile', 'hello.txt')->await();
