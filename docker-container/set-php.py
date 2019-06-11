# Edits php version

import sys

with open("docker-magento/Dockerfile", "r") as f:
    lines = f.readlines()
with open("docker-magento/Dockerfile", "w") as f:
    for line in lines:
        if "alexcheng/apache2-php5" in line:
            f.write("alexcheng/apache2-php5:" + sys.argv[1])
        else:
            f.write(line)