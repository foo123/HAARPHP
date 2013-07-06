#!/bin/sh

# $1 is opencv haar xml file name without xml extension
php -f haartophp.php "$1.xml" "$1" > "$1.php"