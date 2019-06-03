source config.sh
set -x
# Clean environment from before
rm -rf docker-magento

# Install docker base from external library
git clone -b $m1Version git@github.com:alexcheng1982/docker-magento.git

# Configure external library to our specs
python change-hostname.py $ngrokUrl

# Clean and Initialize Docker Environment 
cd docker-magento
docker-compose down -v
docker-compose up -d
sleep 5

# Install Sample Data and Initialize Magento
docker exec -it docker-magento_web_1 install-sampledata
sleep 1
docker exec -it docker-magento_web_1 install-magento

# Installs Bolt and sets the keys for it
if [ "$localRepo" = true ] ; then
    docker cp $boltRepo docker-magento_web_1:/var/www/html/
else
    git clone -b $boltBranch git@github.com:BoltApp/bolt-magento1.git
    docker cp ./bolt-magento1 docker-magento_web_1:/var/www/html/
    rm -rf ./bolt-magento1
fi
docker exec docker-magento_web_1 bash -c "cp -r /var/www/html/bolt-magento1/* /var/www/html/"
docker cp ../set-magento.php docker-magento_web_1:/var/www/html/
docker exec -it docker-magento_web_1 php set-magento.php $boltApiKey $boltSigningSecret $boltPublishableKey