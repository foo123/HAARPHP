@echo off

REM %1 is opencv haar xml file name
php -f haartophp.php -- --xml="%~f1"