"""
    Changes the hostname for the store so that it can work with ngrok
"""

import sys
newText=""

with open("docker_env", "r") as f:
    lines = f.readlines()
with open("docker_env", "w") as f:
    for line in lines:
        if "MAGENTO_URL" in line:
            url = line.split("=")
            f.write(url[0] + "=" + sys.argv[1] + "\n")
        else:
            f.write(line)