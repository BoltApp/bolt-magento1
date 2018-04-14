<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class which defines the Magento admin page for publishing Bolt OAuth settings used to call Magento store API
 *
 * @deprecated OAuth will no longer be used in future versions
 */
class Bolt_Boltpay_Block_Oauth_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{

    /**
     * Bolt_Boltpay_Block_Oauth_Edit constructor.
     *
     * @inheritdoc
     */
    public function __construct()
    {
        $this->_controller  = 'oauth';
        $this->_mode = 'edit';
        $this->_blockGroup = 'boltpay';
        $this->_headerText = "Bolt Integration";
        parent::__construct();
        $this->_updateButton('save', 'label', Mage::helper('poll')->__('Publish'));
    }
}
