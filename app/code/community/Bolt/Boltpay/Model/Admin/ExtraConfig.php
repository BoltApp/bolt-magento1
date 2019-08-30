<?php
/**
 * Bolt magento plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Model_Admin_ExtraConfig
 *
 * Class responsible for saving, validating, and filtering Bolt extra
 * config data.
 *
 * The config values are stored as a json object where each option is
 * a name/value pair.
 *
 * The name of the option is used as the basis of searching for the option's
 * validation and filter methods, where the first letter of the option name
 * is capitalized, and then prepended with the string 'hasValid' or 'filter',
 * respectively.
 *
 * (e.g. The config `boltPrimaryColor` would optionally have a
 * corresponding validation method named `hasValidBoltPrimaryColor` and
 * an optional filter method named `filterBoltPrimaryColor`)
 *
 * If the validation method exist in this object, then it is called for
 * validation, otherwise, the value is assumed valid.
 *
 * The filter method should be defined to accept two parameters:
 *    1.) $rawConfigValue - The value to be filtered
 *    2.) $additionalParams - an optional array of parameters to guide filter
 *
 * In addition to filtering, the filter method could also be used to return a
 * default value.
 *
 * If the filter method is not defined, then $rawConfigValue will be used
 *
 * @see Bolt_Boltpay_Helper_Data::getExtraConfig()
 *
 */
class Bolt_Boltpay_Model_Admin_ExtraConfig extends Mage_Core_Model_Config_Data
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Gets the value of a Bolt non-publicized or non-emphasized
     * configuration value after passing it through an optionally
     * defined filter method.
     *
     * @param string $configName        The name of the config as defined
     *                                  the configuration JSON
     * @param array $filterParameters   Optional set of parameters passed to
     *                                  the optionally defined filter method
     *                                  of the config
     *
     * @return mixed    Typically a string representing the config value, but
     *                  is not limited to this type.  If the config is not defined,
     *                  an empty string is returned
     */
    public function getExtraConfig($configName, $filterParameters = array() ) {
        $methodPostfix = ucfirst($configName);
        $filterMethod = 'filter'.$methodPostfix;

        $allExtraConfigs = (array) json_decode($this->normalizeJSON(Mage::getStoreConfig('payment/boltpay/extra_options')), true);
        $rawValue = @$allExtraConfigs[$configName] ?: '';

        return method_exists($this, $filterMethod)
            ? $this->$filterMethod($rawValue, $filterParameters)
            : $rawValue;
    }


    /**
     * Saves the Bolt extra options json string to the database after each option has been validated.
     * If any extra options fails validation, the entirety of the new value is ignored and
     * the old value is retained.
     *
     * @return Mage_Core_Model_Abstract  $this
     *
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save()
    {
        $optionsJson = (array) json_decode($this->normalizeJSON($this->getValue()));
        $areAllOptionsValid = true;

        if (json_last_error() === JSON_ERROR_NONE) {
            foreach ( $optionsJson as $optionName => $optionValue ) {
                $methodPostfix = ucfirst($optionName);
                $validationMethod = 'hasValid'.$methodPostfix;
                if (method_exists($this, $validationMethod)) {
                    $areAllOptionsValid = $areAllOptionsValid && $this->$validationMethod($optionValue);
                }
            }
        } else {
            $areAllOptionsValid = false;
            Mage::getSingleton('core/session')->addError($this->boltHelper()->__('Invalid JSON for Bolt Extra Options. '.json_last_error_msg()));
        }

        if (!$areAllOptionsValid) {
            $this->setValue($this->getOldValue());
        }

        return parent::save();
    }


    /**
     * Validates whether value is a six or 8 character hex color value.
     * If the value is not valid then an error message is added to the
     * session for display.
     *
     * @param string $hexColor  A six or eight character hex value
     *                          preceded by the '#' character
     *
     * @return bool     True if the $hexColor is a valid color hex value,
     *                  otherwise false
     */
    public function hasValidBoltPrimaryColor($hexColor) {
        if ( !($isValid = (!empty($hexColor) && preg_match('/^#(([A-Fa-f0-9]{6})|([A-Fa-f0-9]{8}))$/', $hexColor))) ) {
            Mage::getSingleton('core/session')->addError($this->boltHelper()->__('Invalid hex color value for extra option `boltPrimaryColor`. [%s] It must be in 6 or 8 character hex format.  (e.g. #f00000 or #3af508a2)', $hexColor));
        }
        return $isValid;
    }

    /**
     * Validate datadog key severity
     * @param $severityString
     * @return mixed
     */
    public function hasValidDatadogKeySeverity($severityString) {

        $severityString = preg_replace('/\s+/', '', $severityString);
        if($severityString) {
            $severities = explode(',', $severityString);
            foreach ($severities as $severity) {
                if (!in_array($severity, [
                        Boltpay_DataDog_ErrorTypes::TYPE_ERROR,
                        Boltpay_DataDog_ErrorTypes::TYPE_WARNING,
                        Boltpay_DataDog_ErrorTypes::TYPE_INFO]
                )) {
                    Mage::getSingleton('core/session')->addError(
                        Mage::helper('boltpay')->__(
                            'Invalid datadog key severity value for extra option `datadogKeySeverity`.[%s]
                         The valid values must be error or warning or info ', $severity
                        )
                    );

                    return false;
                }
            }
        }

        return true;
    }


    /**
     * Makes hex letters all-caps or all lower case.
     * This function is non-critical, provided as a reference demonstration
     * of how to implement the filter method for a given config value
     *
     * @param string $rawConfigValue    The config value pre-filter
     *                                  A 6 or 8 character hex value prepended with '#'
     *                                  is expected, otherwise, an empty string
     *
     * @param array  $additionalParams  Single parameter `case` that is 'UPPER'|'lower'.
     *                                  The default is 'UPPER'
     *
     * @return string   upper or lower case of hex value
     */
    public function filterBoltPrimaryColor($rawConfigValue, $additionalParams = array('case' => 'UPPER') ) {
        return (strtolower(@$additionalParams['case']) === 'lower')
            ? strtolower($rawConfigValue)
            : strtoupper($rawConfigValue);
    }


    /**
     * Provides a default implementation for the javascript $hints_transform
     * function.  If defined in the config, then that value is returned,
     * unfiltered.
     *
     * @param $rawConfigValue   The config value pre-filter
     *                          A valid javascript function is expected,
     *                          otherwise, an empty string
     *
     * @param array $additionalParams   unused.
     *
     * @return string   The hint data post-filter.  If $rawConfigValue was
     *                  defined, then it is returned unchanged, otherwise,
     *                  a default function implementation that returns the
     *                  original hints untransformed is returned.
     */
    public function filterHintsTransform($rawConfigValue, $additionalParams = array() ) {
        $defaultFunction = <<<JS
            function(hints) {
                return hints;
            }
JS;

        return (trim($rawConfigValue))
            ?
            : $defaultFunction;
    }


    /**
     * @todo revise method for enabling and disabling datadog
     *
     * @param $rawConfigValue
     * @param array $additionalParams
     * @return string
     */
    public function filterDatadogKeySeverity($rawConfigValue, $additionalParams = array())
    {
        $allExtraConfigs = (array)json_decode($this->normalizeJSON(Mage::getStoreConfig('payment/boltpay/extra_options')), true);
        return (array_key_exists("datadogKeySeverity", $allExtraConfigs)) ? $rawConfigValue : Bolt_Boltpay_Helper_DataDogTrait::$defaultSeverityConfig;
    }

    /**
     * @param $rawConfigValue
     * @param array $additionalParams
     * @return string
     */
    public function filterDatadogKey($rawConfigValue, $additionalParams = array())
    {
        return trim($rawConfigValue) ?: Bolt_Boltpay_Helper_DataDogTrait::$defaultDataDogKey;
    }

    /**
     * Validates whether value is an int
     *
     * @param int $priceTolerance  The amount a Bolt order is allowed to differ
     *                             from a the Magento order in cents
     *
     * @return bool     True if the value is positive and integer
     */
    public function hasValidPriceFaultTolerance($priceTolerance) {
        if (is_int($priceTolerance) && ($priceTolerance >= 0) ) {
            return true;
        }

        Mage::getSingleton('core/session')->addError(
            Mage::helper('boltpay')->__(
                 'Invalid value for extra option `priceFaultTolerance`.[%s]
                         A valid value must be a positive integer.', $priceTolerance
            )
        );
        return false;
    }

    /**
     * Defines the default value as a 1 cent tolerance for Bolt and Magento grand total
     * difference
     *
     * @param int|string $rawConfigValue    The config value pre-filter. Will be an int or an empty string
     * @param array      $additionalParams  unused for this filter
     *
     * @return int  the number defined in the extra config admin.  If not defined, the default of 1
     */
    public function filterPriceFaultTolerance($rawConfigValue, $additionalParams = array() ) {
        return is_int($rawConfigValue) ? $rawConfigValue : 1;
    }

    /**
     * Ensures boolean value for whether to display Bolt pre-auth orders in Magento
     *
     * @param mixed $rawConfigValue    The config value pre-filter
     * @param array  $additionalParams  unused for this filter
     *
     * @return bool  the value from the extra config admin forced to boolean
     */
    public function filterDisplayPreAuthOrders($rawConfigValue, $additionalParams = array() ) {
        return $this->normalizeBoolean($rawConfigValue);
    }

    /**
     * Ensures boolean value for whether to keep created_at and updated_at time-stamps for pre-auth orders
     *
     * @param mixed $rawConfigValue    The config value pre-filter
     * @param array  $additionalParams  unused for this filter
     *
     * @return bool  the value from the extra config admin forced to boolean
     */
    public function filterKeepPreAuthOrderTimeStamps($rawConfigValue, $additionalParams = array() ) {
        return $this->normalizeBoolean($rawConfigValue);
    }

    /**
     * Ensures boolean value for whether to keep for pre-auth orders after failed payment hoods
     *
     * @param mixed $rawConfigValue    The config value pre-filter
     * @param array  $additionalParams  unused for this filter
     *
     * @return bool  the value from the extra config admin forced to boolean
     */
    public function filterKeepPreAuthOrders($rawConfigValue, $additionalParams = array() ) {
        return $this->normalizeBoolean($rawConfigValue);
    }

    /**
     * Ensures boolean value for whether bench mark profiling is enabled
     *
     * @param mixed $rawConfigValue    The config value pre-filter
     * @param array  $additionalParams  unused for this filter
     *
     * @return bool  the value from the extra config admin forced to boolean
     */
    public function filterEnableBenchmarkProfiling($rawConfigValue, $additionalParams = array() ) {
        return $this->normalizeBoolean($rawConfigValue);
    }

    /**
     * Normalizes JSON by stripping new lines from the given string and returning null
     * in the case of only white space.
     *
     * New line characters are added by the text area and this breaks JSON decoding
     *
     * @param string $string    The string to be stripped of newlines
     *
     * @return string|null      The string stripped of newline characters or null on error
     */
    private function normalizeJSON($string) {
        return trim(preg_replace( '/(\r\n)|\n|\r/', '', $string )) ?: json_encode(array());
    }

    /**
     * Converts the inputted value to a boolean after setting common negative strings as boolean false
     *
     * @param mixed $rawConfigValue    The config value pre-filter
     *
     * @return  bool    false if the value represents a recognized negative response, otherwise the original value
     *                  converted to a boolean
     */
    private function normalizeBoolean($rawConfigValue) {
        if (is_string($rawConfigValue)) {
            $rawConfigValue = strtolower(trim($rawConfigValue));
            if (in_array($rawConfigValue,  ['false', 'no', 'n', 'off'], true)) {
                $rawConfigValue = false;
            }
        }

        return (bool) $rawConfigValue;
    }
}
