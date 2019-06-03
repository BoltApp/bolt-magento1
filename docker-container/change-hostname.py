"""
    Changes the hostname for the store so that it can work with ngrok
"""

import sys
newText=""
with open("docker-magento/env") as f:
    newText=f.read()
    newText = newText.replace('local.magento', sys.argv[1])

with open("docker-magento/env", "w") as f:
    f.write(newText)