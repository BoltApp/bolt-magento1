<?php

/**
 * A simple product provider for Magento1
 */
class Bolt_Boltpay_ProductProvider
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

    /**
     * Create a dummy product
     *
     * @param string $sku            The SKU of the product
     * @param array  $additionalData An array with (additional) data
     * @param int    $quantity       Product stock quantity
     *
     * @return int The Product ID of the newly created product
     * @throws Exception
     */
    public static function createDummyProduct($sku, $additionalData = array(), $quantity = 10)
    {
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

        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect(array('entity_id', 'sku'))
            ->addAttributeToFilter('sku', array('eq' => $sku))
        ;

        if ($collection->getSize() > 0) {
            $id = $collection->getFirstItem()->getId();

            return $id;
        } else {
            unset($collection);
        }

        $product = Mage::getModel('catalog/product');
        $productData = array(
            'sku' => $sku,
            'name' => 'Testproduct: ' . $sku,
            'description' => 'Description for ' . $sku,
            'short_description' => 'Short description for ' . $sku,
            'weight' => 1,
            'price' => 10,
            'attribute_set_id' => self::$attributeSetId,
            'tax_class_id' => self::$taxClassId,
            'stock_data' => array(
                'qty' => $quantity,
                'is_in_stock' => 1,
                'use_config_manage_stock' => 0,
                'manage_stock' => 1
            ),
            'visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            'status' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
            'type_id' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            'website_ids' => self::$websiteIds
        );
        foreach ($additionalData as $key => $value) {
            $productData[$key] = $value;
        }

        $product->setData($productData);
        $product->save();

        return $product->getId();
    }

    /**
     * Delete the dummy product
     *
     * @param $productId
     * @throws Zend_Db_Adapter_Exception
     */
    public static function  deleteDummyProduct($productId)
    {
        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        /** @var Magento_Db_Adapter_Pdo_Mysql $writeConnection */
        $writeConnection = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/product');

        $query = "DELETE FROM ".$table." WHERE entity_id = :productId";
        $bind = array(
            'productId' => (int) $productId
        );

        $writeConnection->query($query, $bind);
    }

    /**
     * Get store product with it's stock quantity
     *
     * @param int $productId Store product ID (catalog_product_entity::entity_id)
     *
     * @return Mage_Catalog_Model_Resource_Product
     */
    public static function getStoreProductWithQty($productId)
    {
        $storeProducts = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('entity_id', $productId)
            ->joinField(
                'qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );
        return $storeProducts->getFirstItem();
    }
}