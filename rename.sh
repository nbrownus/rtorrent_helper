#!/bin/bash
cd "$(dirname "$0")"

if [ -f "$PWD/settings.sh" ]; then
    . "$PWD/settings.sh"
fi

logger -t renamer "KICKING OFF $1"
php "$PWD/rename.php" $1 2>&1 | logger -t renamer
