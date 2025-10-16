@echo off
chcp 1252 >nul
echo Erstelle Ordnerstruktur von GIO...
set "ZIEL=C:\Users\user\Documents\GitHub\grabiton-snapshot\gio"
tree "%ZIEL%" /F /A > "%ZIEL%\ordnerstruktur.txt"
echo Fertig: %ZIEL%\ordnerstruktur.txt wurde erstellt.
pause
