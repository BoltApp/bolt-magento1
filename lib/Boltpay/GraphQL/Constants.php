<?php
interface Boltpay_GraphQL_Constants {

    const MERCHANT_API_GQL_ENDPOINT = 'v2/merchant/api';

    // The const via which Bolt identifies the type of plugin.
    const PLUGIN_TYPE='MAGENTO_1';

    /**
     * The graphql query to retrieve feature switches. This will be maintained backward compatible.
     */
    const GET_FEATURE_SWITCHES_QUERY = <<<'GQL'
query GetFeatureSwitches($type: PluginType!, $version: String!) {
  plugin(type: $type, version: $version) {
    features {
      name
      value
      defaultValue
      rolloutPercentage
    }
  }
}
GQL;

    // Operation name for graphql query to retrieve feature switches.
    const GET_FEATURE_SWITCHES_OPERATION = 'GetFeatureSwitches';
}