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
 * This generated Bolt Payment information
 * Its primarily used in sending out order confirmation
 * emails from the merchant
 */
class Bolt_Boltpay_Block_Info extends Mage_Payment_Block_Info
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * @param null $transport
     * @return Varien_Object|null
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $info = $this->getInfo();
        $data = [];

        if ($ccType = $info->getCcType()){
            $data['Credit Card Type'] = strtoupper($ccType);
        }

        if ($ccLast4 = $info->getCcLast4()){
            $data['Credit Card Number'] = sprintf('xxxx-%s', $ccLast4);
        }

        if ($data){
            $transport->setData(array_merge($transport->getData(), $data));
        }

        return $transport;
    }
}
