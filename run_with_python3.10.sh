#!/bin/bash

# Activate the Python 3.10 virtual environment
source /home/yagho/python/venv/bin/activate

# Print Python version information
python3 -c "import sys, platform; print(f'Python {sys.version}\\nArchitecture: {platform.architecture()[0]}\\nRunning on: {platform.platform()}')"
echo "----------------------------------------------"

# Run the specified script with the correct environment
# Usage: ./run_with_python3.10.sh script_name.py
if [ -z "$1" ]
then
    echo "Please specify a script to run, e.g., ./run_with_python3.10.sh edubridge_algoritmo/test12_interactive.py"
else
    python3 "$1"
fi