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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Model_Admin_Form_ExtraOptions
 *
 * Class responsible for saving and validating Bolt extra options.
 *
 * The options are stored as a json object where each option is a name/value pair.
 *
 * The name of the option is used as the basis of searching for the options validation method,
 * where the first letter of the option name is capitalized, and then prepended with the string
 * 'isValid'.
 *
 * (e.g. The option `boltPrimaryColor` would have a corresponding validation method
 * named `isValidBoltPrimaryColor`)
 *
 * If the method exist in this object, then it is called for validation, otherwise,
 * the value is assumed valid.
 *
 */
class Bolt_Boltpay_Model_Admin_Form_ExtraOptions extends Mage_Core_Model_Config_Data
{
    /**
     * Saves the Bolt extra options json string to the database after each option has been validated.
     * If any extra options fails validation, the entirety of the new value is ignored and
     * the old value is retained.
     *
     * @return Mage_Core_Model_Abstract  $this
     */
    public function save()
    {
        $optionsJson = (array) json_decode($this->getValue(), true);
        $areAllValidOptions = true;

        if (json_last_error() === JSON_ERROR_NONE) {
            foreach ( $optionsJson as $optionName => $optionValue ) {
                $methodPostfix = ucfirst($optionName);
                if (method_exists($this, 'isValid'.$methodPostfix)) {
                    $validationMethod = 'isValid'.$methodPostfix;
                    $areAllValidOptions = $areAllValidOptions && $this->$validationMethod($optionValue);
                }
            }
        } else {
            $areAllValidOptions = false;
            Mage::getSingleton('core/session')->addError(Mage::helper('boltpay')->__('Invalid json for Bolt Extra Options.'));
        }

        if (!$areAllValidOptions) {
            $this->setValue($this->getOldValue());
        }

        return parent::save();
    }


    /**
     * Validates whether value is a six or 8 character hex color value.  If the value is not valid
     * then an error message is added to the session for display.
     *
     * @param string $hexColor  A six or eight character hex value preceded by the '#' character
     *
     * @return bool     True if the $hexColor is a valid color hex value, otherwise false
     */
    public function isValidBoltPrimaryColor($hexColor) {
        if ( !($isValid = (!empty($hexColor) && preg_match('/^#(([A-Fa-f0-9]{6})|([A-Fa-f0-9]{8}))$/', $hexColor))) ) {
            Mage::getSingleton('core/session')->addError(Mage::helper('boltpay')->__('Invalid hex color value for extra option `boltPrimaryColor`. It must be in 6 or 8 character hex format.  (e.g. #f00000 or #3af508a2)'));
        }
        return $isValid;
    }
}
