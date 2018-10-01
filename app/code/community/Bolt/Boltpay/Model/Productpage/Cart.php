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

class Bolt_Boltpay_Model_Productpage_Cart extends Mage_Core_Model_Abstract
{
    const ERR_CODE_OUT_OF_STOCKS = 7001;
    const ERR_CODE_INVALID_SIZE = 7002;
    const ERR_CODE_INVALID_QUANTITY = 7003;
    const ERR_CODE_INVALID_REFERENCE = 7004;
    const ERR_CODE_INVALID_AMOUNT = 7005;

    const ITEM_TYPE_PHYSICAL = 'physical';
    const ITEM_TYPE_DIGITAL = 'digital';

    protected $cartRequest;
    protected $cartResponse;
    protected $httpCode;

    protected $responseError;
    protected $errorCode;
    protected $errorMessage;

    /**
     * Initialize model with data
     *
     * @param $cartRequest
     */
    public function init($cartRequest)
    {
        $this->cartRequest = $cartRequest;
    }

    /**
     * Generate Data
     */
    public function generateData()
    {
        try {
            $this->validateCartRequest();
            $this->createCart();
            $this->setCartResponse();
        } catch (\Bolt_Boltpay_BadInputException $e) {
            return false;
        } catch (\Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);

            return false;
        }

        return true;
    }

    /**
     * Validate cart request data
     *
     * @return bool
     * @throws \Exception
     */
    protected function validateCartRequest()
    {
        $this->validateCartInfo();
        $this->validateEmptyCart();
        $this->validateProductsExist();
        $this->validateProductsQty();
        $this->validateProductsStock();

        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function validateCartInfo()
    {
        if (!$this->cartRequest) {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_INVALID_SIZE,
                "Invalid cart information",
                422
            );
        }

        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function validateEmptyCart()
    {
        if (!$this->getCartRequestItems()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_INVALID_SIZE,
                "Empty cart request",
                422
            );
        }

        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function validateProductsExist()
    {
        $cartItems = json_decode(json_encode($this->getCartRequestItems()), true);
        $productIds = array_column($cartItems, 'reference');

        /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
        $productCollection = Mage::getModel('catalog/product')->getCollection();
        $productCollection->addFieldToFilter('entity_id', array('in' => $productIds));

        $dbProductIds = array_column($productCollection->getData(), 'entity_id');
        $diffIds = array_values(array_diff($productIds, $dbProductIds));

        if (count($diffIds) > 0) {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_INVALID_REFERENCE,
                "Product {$diffIds[0]} was not found",
                404
            );
        }

        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function validateProductsQty()
    {
        $cartItems = $this->getCartRequestItems();

        foreach ($cartItems as $cartItem) {
            if (!isset($cartItem->quantity) || !is_numeric($cartItem->quantity) || $cartItem->quantity <= 0) {
                $this->setErrorResponseAndThrowException(
                    self::ERR_CODE_INVALID_QUANTITY,
                    "Invalid product quantity",
                    422
                );
            }
        }

        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function validateProductsStock()
    {
        $cartItems = $this->getCartRequestItems();

        foreach ($cartItems as $cartItem) {
            $product = $this->getProductById($cartItem->reference);
            $stockInfo = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
            if ($stockInfo->getManageStock()) {
                if (($stockInfo->getQty() < $cartItem->quantity) && !$stockInfo->getBackorders()) {
                    $this->setErrorResponseAndThrowException(
                        self::ERR_CODE_OUT_OF_STOCKS,
                        "Product {$product->getName()} is out of stock",
                        422
                    );
                }
            }
        }

        return true;
    }

    /**
     *
     * @return Mage_Checkout_Model_Cart
     */
    protected function createCart()
    {
        $cartItems = $this->getCartRequestItems();
        $cart = $this->getSessionCart();

        foreach ($cartItems as $cartItem) {
            $productId = @$cartItem->reference;

            $product = $this->getProductById($productId);

            $param = array(
                'product' => $productId,
                'qty'     => @$cartItem->quantity
            );
            $cart->addProduct($product, $param);
        }
        $cart->save();

        return $cart;
    }

    /**
     * @return array
     */
    protected function setCartResponse()
    {
        $quote = $this->getSessionQuote();
        $this->cartResponse = array(
            'order_reference' => $quote->getId(),
            'currency'        => $quote->getQuoteCurrencyCode(),
            'items'           => $this->getGeneratedItems(),
            'total'           => $this->getGeneratedTotal()
        );
        $this->httpCode = 200;

        return $this->cartResponse;
    }

    /**
     * Get session cart
     *
     * @return Mage_Checkout_Model_Cart
     */
    public function getSessionCart()
    {
        /** @var Mage_Checkout_Model_Cart $cart */
        $cart = Mage::getSingleton('checkout/cart');

        return $cart;
    }

    /**
     *
     * @return array
     */
    protected function getGeneratedItems()
    {
        $items = $this->getSessionQuote()->getAllVisibleItems();
        /** @var Bolt_Boltpay_Helper_Data $boltHelper */
        $boltHelper = Mage::helper('boltpay');

        return array_map(
            function ($item) use ($boltHelper) {
                $imageUrl = $boltHelper->getItemImageUrl($item);
                $product = $this->getProductById($item->getProductId());
                $type = $product->getTypeId() == 'virtual' ? self::ITEM_TYPE_DIGITAL : self::ITEM_TYPE_PHYSICAL;

                $unitPrice = (int)round($item->getPrice() * 100);
                $quantity = (int)($item->getQty());
                $totalAmount = (int)round($unitPrice * $quantity);

                return array(
                    'reference'    => $this->getSessionQuote()->getId(),
                    'image_url'    => $imageUrl,
                    'name'         => $item->getName(),
                    'sku'          => $product->getData('sku'),
                    'description'  => substr($product->getDescription(), 0, 8182) ?: '',
                    'total_amount' => $totalAmount,
                    'unit_price'   => $unitPrice,
                    'quantity'     => $quantity,
                    'type'         => $type
                );
            }, $items
        );
    }

    /**
     *
     * @return string
     */
    protected function getGeneratedTotal()
    {
        $items = $this->getSessionQuote()->getAllVisibleItems();
        $calculatedTotal = 0;

        foreach ($items as $item) {
            $unitPrice = (int)round($item->getPrice() * 100);
            $quantity = (int)($item->getQty());
            $totalAmount = (int)round($unitPrice * $quantity);

            $calculatedTotal += $totalAmount;
        }

        return $calculatedTotal;
    }

    /**
     * Get session quote
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function getSessionQuote()
    {
        return $this->getSessionCart()->getQuote();
    }

    /**
     * Sets the response error and the http status code and then throws an exception.
     *
     * @param int       $errCode
     * @param string    $message
     * @param int       $httpStatusCode
     * @param Exception $exception
     *
     * @throws Exception
     */
    protected function setErrorResponseAndThrowException($errCode, $message, $httpStatusCode, \Exception $exception = null)
    {
        $this->responseError = array(
            'code'    => $errCode,
            'message' => $message,
        );

        $this->httpCode = $httpStatusCode;
        $this->cartResponse = $this->getCartTotals();

        if ($exception) {
            throw $exception;
        }

        throw new \Bolt_Boltpay_BadInputException($message);
    }

    /**
     * Get response body
     *
     * @return array
     */
    public function getResponseBody()
    {
        if ($this->responseError) {
            return array(
                'status' => 'failure',
                'error'  => $this->responseError
            );
        }

        return array(
            'status' => 'success',
            'cart'   => $this->cartResponse
        );
    }

    /**
     * Get http response code
     *
     * @return string
     */
    public function getResponseHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * Get cart request items
     *
     * @return array
     */
    protected function getCartRequestItems()
    {
        return @$this->cartRequest->items;
    }

    /**
     * @param $id
     *
     * @return Mage_Catalog_Model_Product
     */
    protected function getProductById($id)
    {
        return Mage::getModel('catalog/product')->load($id);
    }
}
