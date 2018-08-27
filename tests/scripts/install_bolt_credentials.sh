#!/usr/bin/env bash

API_KEY         = ${BOLT_API_KEY}
SIGNING_SECRET  = ${BOLT_SIGNING_SECRET}
KEY_CHECKOUT    = ${BOLT_PUBLISHABLE_KEY_CHECKOUT}
KEY_PAYMENT     = ${BOLT_PUBLISHABLE_KEY_PAYMENT}
KEY_BACK_OFFICE = ${BOLT_PUBLISHABLE_KEY_BACK_OFFICE}

mysql -D ${DB_NAME} -u root -p <<EOF
UPDATE core_config_data SET value = '1' WHERE path LIKE 'payment/boltpay/active';
UPDATE core_config_data SET value = '1' WHERE path LIKE 'payment/boltpay/sandbox_mode';
UPDATE core_config_data SET value = '1' WHERE path LIKE 'payment/boltpay/automatic_capture_mode';
UPDATE core_config_data SET value = '1' WHERE path LIKE 'payment/boltpay/debug';
EOF

if [-z $API_KEY]
then
mysql -D ${DB_NAME} -u root -p <<EOF
UPDATE core_config_data SET value = '${API_KEY}' WHERE path LIKE 'payment/boltpay/api_key';
EOF
echo "# Added API_KEY."
fi

if [-z $SIGNING_SECRET]
then
mysql -D ${DB_NAME} -u root -p <<EOF
UPDATE core_config_data SET value = '${SIGNING_SECRET}' WHERE path LIKE 'payment/boltpay/signing_secret';
EOF
echo "# Added SIGNING_SECRET."
fi

if [-z $KEY_CHECKOUT]
then
mysql -D ${DB_NAME} -u root -p <<EOF
UPDATE core_config_data SET value = '${KEY_CHECKOUT}' WHERE path LIKE 'payment/boltpay/publishable_key_checkout';
EOF
echo "# Added KEY_CHECKOUT."
fi


if [-z $KEY_PAYMENT]
then
mysql -D ${DB_NAME} -u root -p <<EOF
UPDATE core_config_data SET value = '${KEY_PAYMENT}' WHERE path LIKE 'payment/boltpay/publishable_key_payment';
EOF
echo "# Added KEY_PAYMENT."
fi

if [-z $KEY_BACK_OFFICE]
then
mysql -D ${DB_NAME} -u root -p <<EOF
UPDATE core_config_data SET value = '${KEY_BACK_OFFICE}' WHERE path LIKE 'payment/boltpay/publishable_key_back_office';
EOF
echo "# Added KEY_BACK_OFFICE."
fi

echo ""
echo "########################"
echo "#      Completed!      #"
echo "########################"
echo ""