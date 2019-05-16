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

require_once 'Mage/Checkout/controllers/OnepageController.php';

/**
 * Class Bolt_Boltpay_OnepageController
 */
class Bolt_Boltpay_OnepageController
    extends Mage_Checkout_OnepageController implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_Controller_Traits_WebHookTrait;
    
    /**
     * Allows for success call to be treated like a webhook will not returning JSON
     * @see Bolt_Boltpay_Controller_Traits_WebHookTrait::preDispatch()
     */
    public function _construct()
    {
        $this->willReturnJson = false;
        parent::_construct();
    }
}
