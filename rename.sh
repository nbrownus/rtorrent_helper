#!/bin/bash
cd "$(dirname "$0")"

if [ -f "$PWD/settings.sh" ]; then
    . "$PWD/settings.sh"
fi

echo "KICKING OFF $1" >> "$PWD/rename.log"
php "$PWD/rename.php" $1 >> "$PWD/rename.log" &
