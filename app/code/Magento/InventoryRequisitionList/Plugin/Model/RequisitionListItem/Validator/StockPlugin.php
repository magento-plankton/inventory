<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryRequisitionList\Plugin\Model\RequisitionListItem\Validator;

use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\RequisitionList\Api\Data\RequisitionListItemInterface;
use Magento\RequisitionList\Model\RequisitionListItem\Validator\Stock;
use Magento\RequisitionList\Model\RequisitionListItemProduct;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * This plugin adds multi-source stock calculation capabilities to the Requisition List feature.
 */
class StockPlugin
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteId;

    /**
     * @var GetProductSalableQtyInterface
     */
    private $getProductSalableQty;

    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface
     */
    private $isSourceItemManagementAllowedForProductType;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteId
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        StockByWebsiteIdResolverInterface $stockByWebsiteId,
        GetProductSalableQtyInterface $getProductSalableQty,
        IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
    ) {
        $this->productRepository = $productRepository;
        $this->stockByWebsiteId = $stockByWebsiteId;
        $this->getProductSalableQty = $getProductSalableQty;
        $this->isSourceItemManagementAllowedForProductType = $isSourceItemManagementAllowedForProductType;
    }

    /**
     * Extend requisition list item stock validation with multi-sourcing capabilities.
     *
     * @param Stock $subject
     * @param callable $proceed
     * @param RequisitionListItemInterface $item
     * @return array Item errors
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundValidate(Stock $subject, callable $proceed, RequisitionListItemInterface $item)
    {
        $errors = [];
        $product = $this->productRepository->get($item->getSku(), false, null, true);

        if (!$this->isSourceItemManagementAllowedForProductType->execute($product->getTypeId())) {
            return $proceed($item);
        }

        $websiteId = (int)$product->getStore()->getWebsiteId();
        $stockId = (int)$this->stockByWebsiteId->execute($websiteId)->getStockId();
        $salableQty = $this->getProductSalableQty->execute($product->getSku(), $stockId);

        if ($salableQty === 0) {
            $errors[$subject::ERROR_OUT_OF_STOCK] = __('The SKU is out of stock.');
            return $errors;
        }

        if (($salableQty < $item->getQty()) && !$product->isComposite()) {
            $errors[$subject::ERROR_LOW_QUANTITY] =
                __('The requested qty is not available');
            return $errors;
        }

        return $errors;
    }
}
