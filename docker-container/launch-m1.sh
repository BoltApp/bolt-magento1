source config.sh
set -x
# Clean environment from before
rm -rf docker-magento

python set-magento-version.py $m1Version
python change-hostname.py $ngrokUrl
docker-compose down -v
docker-compose up -d
sleep 5 # used to give DB time to initialize

# Install Sample Data and Initialize Magento
if [ "$installSampleData" = true ] ; then
    docker exec -it magento1_store install-sampledata
fi
sleep 1 # used to control DB race conditions
docker exec -it magento1_store install-magento

# Installs Bolt and sets the keys for it
if [ "$localRepo" = true ] ; then
    docker cp $boltRepo magento1_store:/var/www/html/
else
    git clone --depth 1 -b $boltBranch git@github.com:BoltApp/bolt-magento1.git
    docker cp ./bolt-magento1 magento1_store:/var/www/html/
    rm -rf ./bolt-magento1
fi
docker exec magento1_store bash -c "cp -r /var/www/html/bolt-magento1/* /var/www/html/"
docker cp set-magento.php magento1_store:/var/www/html/
docker exec -it magento1_store php set-magento.php $boltApiKey $boltSigningSecret $boltPublishableKey