#!/usr/bin/env bash

basedir=$(dirname $0)
cd $basedir
/usr/bin/php -q  cron.php  --param_file=cron.params >/dev/null 2>&1
