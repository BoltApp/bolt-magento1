#!/bin/sh

if [ $# -le 0 ]; then
    echo "You must specify version number. \nExample: ./create_release.sh 1.0.8"
    exit -1
fi

rm *.zip
rm -fr /tmp/bolt_magento_plugin

mkdir /tmp/bolt_magento_plugin
cp -r app /tmp/bolt_magento_plugin/.
cp -r js /tmp/bolt_magento_plugin/.
cp -r lib /tmp/bolt_magento_plugin/.
cp -r skin /tmp/bolt_magento_plugin/.
cp CHANGELOG.md /tmp/bolt_magento_plugin/.
# cp package.xml /tmp/bolt_magento_plugin/.
find /tmp/bolt_magento_plugin -name ".DS_Store" -type f -delete

current_dir=$(pwd)
cd /tmp/bolt_magento_plugin
zip -r bolt-magento1-v$1.zip *
cp bolt-magento1-v$1.zip $current_dir/.
# tar -cvzf bolt-magento1-v1-$1.tgz *
# cp bolt-magento1-v1-$1.tgz $current_dir/.

echo "\n\nZip file bolt-magento1-v$1.zip created."
