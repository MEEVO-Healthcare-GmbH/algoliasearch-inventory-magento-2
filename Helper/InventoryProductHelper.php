<?php

namespace Algolia\AlgoliaSearchInventory\Helper;

use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Catalog\Model\Product;

class InventoryProductHelper extends ProductHelper
{
    protected function addInStock($defaultData, $customData, Product $product)
    {
        if (isset($defaultData['in_stock']) === false) {
            $customData['in_stock'] = $this->productIsInStock($product, $product->getStoreId());
        }

        return $customData;
    }

    /**
     * This method is overriden and left empty to remove the native stock filter behaviour
     * (from CatalogInventory Helper)
     *
     * @param $products
     * @param $storeId
     */
    protected function addStockFilter($products, $storeId)
    {
        //void
    }

    protected function addMandatoryAttributes($products): void
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $products */
        $products->addAttributeToSelect('special_price')
            ->addAttributeToSelect('special_from_date')
            ->addAttributeToSelect('special_to_date')
            ->addAttributeToSelect('visibility')
            ->addAttributeToSelect('status');
    }

    public function productIsInStock($product, $storeId)
    {
        // Handled in ProductHelperPlugin
        return true;
    }

    /**
     * @param $defaultData
     * @param $customData
     * @param $additionalAttributes
     * @param Product $product
     * @return mixed
     */
    protected function addStockQty($defaultData, $customData, $additionalAttributes, Product $product)
    {
        if (isset($defaultData['stock_qty']) === false && $this->isAttributeEnabled($additionalAttributes, 'stock_qty')) {
            $customData['stock_qty'] = 0;

            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            if ($stockItem) {
                $customData['stock_qty'] = (int)$stockItem->getQty();
            }
        }

        if ($this->isMsiEnabled()) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            /** @var \Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface $stockByWebsiteId */
            $stockByWebsiteId = $objectManager->get('Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface');
            /** @var \Magento\Store\Model\StoreManagerInterface $storeManager */
            $storeManager = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
            /** @var \Magento\InventorySalesApi\Api\GetProductSalableQtyInterface $getProductSalableQty */
            $getProductSalableQty = $objectManager->get('Magento\InventorySalesApi\Api\GetProductSalableQtyInterface');

            $stockId = 1;
            $storeId = $product->getStoreId();
            $websiteId = $storeManager->getStore($storeId)->getWebsiteId();
            $stockId = $stockByWebsiteId->execute($websiteId)->getStockId();
            $customData['stock_qty'] = $getProductSalableQty->execute($product['sku'], $stockId);
        }

        return $customData;
    }

    public function isMsiEnabled(): bool
    {
        // If Magento Inventory is not installed, no need for the external module
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\Module\Manager $moduleManager */
        $moduleManager = $objectManager->get('Magento\Framework\Module\Manager');
        $hasMsiModule = $moduleManager->isEnabled('Magento_Inventory');
        if (! $hasMsiModule) {
            return false;
        }

        // Module installation is only needed if there's more than one source
        $sourceCollection = $objectManager->get('\Magento\Inventory\Model\ResourceModel\Source\Collection');
        if ($sourceCollection->getSize() <= 1) {
            return false;
        }

        return true;
    }
}
