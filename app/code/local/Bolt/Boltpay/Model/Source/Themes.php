<?php

class Bolt_Boltpay_Model_Source_Themes
{
  public function toOptionArray()
  {
    return array(
      array(
        'value' => 'light',
        'label' => Mage::helper('boltpay')->__('Light, for dark backgrounds')
      ),
      array(
        'value' => 'dark',
        'label' => Mage::helper('boltpay')->__('Dark, for light backgrounds')
      ),
    );
  }
}
