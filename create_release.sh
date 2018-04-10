#!/bin/sh

rm -fr /tmp/bolt_magento_plugin

mkdir /tmp/bolt_magento_plugin
cp -r app /tmp/bolt_magento_plugin/.
cp -r lib /tmp/bolt_magento_plugin/.
cp -r skin /tmp/bolt_magento_plugin/.
cp CHANGELOG.md /tmp/bolt_magento_plugin/.
find /tmp/bolt_magento_plugin -name ".DS_Store" -type f -delete

tar -C /tmp -cvf bolt_magento_plugin.tar.gz bolt_magento_plugin
current_dir=$(pwd)
cd /tmp/bolt_magento_plugin
zip -r bolt-magento-plugin.zip *
cp bolt-magento-plugin.zip $current_dir/.
