@echo off
cd /d "%~dp0"
py -3.12 -m venv .venv
if errorlevel 1 goto erro
.venv\Scripts\python.exe -m pip install --requirement requirements.txt
if errorlevel 1 goto erro
echo Instalacao concluida. Execute Configurar.bat.
pause
exit /b 0
:erro
echo Falha. Instale Python 3.12 (64 bits) pelo python.org e execute novamente.
pause
exit /b 1
