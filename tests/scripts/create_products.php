<?php

require_once __DIR__ . '/../../shell/abstract.php';

/**
 * Bolt install discount Shell Script
 */
class Bolt_Shell_CreateProduct extends Mage_Shell_Abstract
{
    /**
     * @var int Default tax class ID to provide for dummy products
     */
    private static $taxClassId;

    /**
     * @var int Attribute set ID to provide for dummy products
     */
    private static $attributeSetId;

    /**
     * @var array website IDs to set for dummy products
     */
    private static $websiteIds;

    protected function _getProductConfig()
    {
        return array(
            array(
                'sku'   => 'TestProduct01',
                'name'  => 'TestProduct01',
                'qty'   => 123,
                'is_in_stock' => 1,
                'price' => 123.45,
            ),
            array(
                'sku'   => 'TestProduct02',
                'name'  => 'TestProduct02',
                'qty'   => 123,
                'is_in_stock' => 1,
                'price' => 654.78,
            ),
        );
    }

    /**
     * Run script
     */
    public function run()
    {
        $productsData = $this->_getProductConfig();
        try {
            if (!self::$attributeSetId) {
                self::$attributeSetId = Mage::getModel('catalog/product')->getDefaultAttributeSetId();
            }
            if (!self::$taxClassId) {
                self::$taxClassId = Mage::getModel('tax/class')->getCollection()->getFirstItem()->getId();
            }
            if (!self::$websiteIds) {
                $websites = Mage::app()->getWebsites();
                self::$websiteIds = array();
                foreach ($websites as $website) {
                    self::$websiteIds[] = $website->getId();
                }
            }

            foreach ($productsData as $productData) {
                $this->createProductFromConfig($productData);
            }
        } catch (Mage_Core_Exception $e) {
            var_dump($e->getMessage());
            die;
        } catch (Exception $e) {
            var_dump($e->getMessage());
            die;
        }
    }

    /**
     * @param array $productData
     *
     * @throws Exception
     */
    public function createProductFromConfig($productData = array())
    {
        if (!count($productData)) {
            throw new Exception('There is no product data from config');
        }

        $sku = $productData['sku'];
        $name = $productData['name'];

        $product = Mage::getModel('catalog/product');
        $productData = array(
            'sku'   => $sku,
            'name'  => $name,
            'description'       => 'Description for ' . $name . ', SKU: ' . $sku,
            'short_description' => 'Short description for ' . $name . ', SKU: ' . $sku,
            'weight'    => 1,
            'price'     => (float) $productData['price'],
            'attribute_set_id'  => self::$attributeSetId,
            'tax_class_id'      => self::$taxClassId,
            'stock_data' => array(
                'qty' => $productData['qty'],
                'is_in_stock' => $productData['is_in_stock'],
                'use_config_manage_stock' => 0,
                'manage_stock' => 1
            ),
            'visibility'    => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            'status'        => Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
            'type_id'       => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            'website_ids'   => self::$websiteIds
        );

        $product->setData($productData);

        $product->save();
    }
}

$shell = new Bolt_Shell_CreateProduct();
$shell->run();
