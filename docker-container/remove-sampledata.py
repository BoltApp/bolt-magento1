# Removes line that installs sample data

with open("docker-magento/Dockerfile", "r") as f:
    lines = f.readlines()
with open("docker-magento/Dockerfile", "w") as f:
    for line in lines:
        if line.strip("\n") != "RUN chmod +x /usr/local/bin/install-sampledata":
            f.write(line)