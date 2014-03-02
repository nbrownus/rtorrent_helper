#!/bin/bash
cd "$(dirname "$0")"
echo "KICKING OFF $1" >> $PWD/rename.log
php $PWD/rename.php $1 >> $PWD/rename.log &
