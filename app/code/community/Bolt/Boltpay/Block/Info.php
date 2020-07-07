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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * This generates the Bolt Payment information
 * Its primarily used in sending out order confirmation
 * emails from the merchant and what is used on invoices
 */
class Bolt_Boltpay_Block_Info extends Mage_Payment_Block_Info
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Assigns a default template to be used
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('boltpay/info/default.phtml');
    }

    /**
     * Gets the credit card data and adds it to what will be displayed for Bolt payments
     *
     * @param Varien_Object|array|null $transport
     * @return Varien_Object|null
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $info = $this->getInfo();
        $data = [];
        $paymentProcessor = $info->getAdditionalInformation('bolt_payment_processor');
        
        if ( empty($paymentProcessor) || $paymentProcessor == Bolt_Boltpay_Model_Payment::PROCESSOR_VANTIV ) {
            if ($ccType = $info->getCcType()){
                $data['Credit Card Type'] = strtoupper($ccType);
            }
    
            if ($ccLast4 = $info->getCcLast4()){
                $data['Credit Card Number'] = sprintf('xxxx-%s', $ccLast4);
            }    
        }        

        if ($data){
            $transport->setData(array_merge($transport->getData(), $data));
        }

        return $transport;
    }

    /**
     * Displays Bolt and any auxiliary payment services in the title used for emails and invoices.
     * This also applies to store-front (frontend) customer checkouts, particularly one-page-checkout.
     * In this frontend, since the payment processor has not yet been added to additional information,
     *
     *
     * @return string  A string indicating Bolt was used as a payment.  If an auxiliary method was
     *                 used, it is appended as a hyphenated string
     */
    public function displayPaymentMethodTitle()
    {
        $info = $this->getInfo();
        $paymentProcessor = $info->getAdditionalInformation('bolt_payment_processor');
        
        if ( empty($paymentProcessor) || $paymentProcessor == Bolt_Boltpay_Model_Payment::PROCESSOR_VANTIV ) {
            $paymentTitle = $this->getMethod()->getTitle();
        } else {
            $paymentTitle = array_key_exists( $paymentProcessor, Bolt_Boltpay_Model_Payment::$_processorDisplayNames )
                ? 'Bolt-' . Bolt_Boltpay_Model_Payment::$_processorDisplayNames[ $paymentProcessor ]
                : 'Bolt-' . strtoupper( $paymentProcessor );
        }
        
        return $paymentTitle;
    }
}
