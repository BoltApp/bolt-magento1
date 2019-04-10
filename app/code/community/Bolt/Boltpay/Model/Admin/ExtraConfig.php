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
     * Saves the Bolt extra options json string to the database after each option has been validated.
     * If any extra options fails validation, the entirety of the new value is ignored and
     * the old value is retained.
     *
     * @return Mage_Core_Model_Abstract  $this
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
     * @return string
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
     * Normalizes JSON by stripping new lines from the given string and returning null in the case of only white space.
     * New line characters are added by the text area and this breaks JSON decoding
     *
     * @param $string   The string to be stripped of newlines
     *
     * @return string|null    The string stripped of newline characters or null on error
     */
    private function normalizeJSON($string) {
        return trim(preg_replace( '/(\r\n)|\n|\r/', '', $string )) ?: array();
    }
}
