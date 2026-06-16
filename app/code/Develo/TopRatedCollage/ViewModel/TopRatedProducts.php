<?php
/**
 * Develo_TopRatedCollage
 */
declare(strict_types=1);

namespace Develo\TopRatedCollage\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Review as ReviewResource;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Supplies the highest-rated catalog products (by average review rating) to the
 * collage template. Designed to be used as a Hyvä ViewModel argument.
 */
class TopRatedProducts implements ArgumentInterface
{
    /** @var ProductInterface[]|null Cached result for the current request */
    private ?array $products = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ReviewResource $reviewResource,
        private readonly Visibility $visibility,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * Return the top $limit products ordered by rating, then by number of reviews.
     *
     * @return ProductInterface[]
     */
    public function getProducts(int $limit = 2): array
    {
        if ($this->products !== null) {
            return $this->products;
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $entityType = (int) $this->reviewResource->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE);

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name', 'image', 'url_key'])
            ->addStoreFilter($storeId)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addPriceData();

        // Join the aggregated review summary so we can sort by rating.
        $collection->getSelect()
            ->joinInner(
                ['rating' => $collection->getTable('review_entity_summary')],
                sprintf(
                    'rating.entity_pk_value = e.entity_id AND rating.entity_type = %d AND rating.store_id = %d',
                    $entityType,
                    $storeId
                ),
                ['rating_summary', 'reviews_count']
            )
            ->where('rating.reviews_count > 0')
            ->order(['rating.rating_summary DESC', 'rating.reviews_count DESC']);

        $collection->setPageSize(max(1, $limit))->setCurPage(1);

        return $this->products = array_values($collection->getItems());
    }

    /**
     * Full-size catalog image URL for the given product.
     */
    public function getImageUrl(ProductInterface $product): string
    {
        $mediaBaseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        return $mediaBaseUrl . 'catalog/product' . $product->getImage();
    }

    /**
     * Average rating expressed as a 0-100 percentage (drives the star bar width).
     */
    public function getRatingPercent(ProductInterface $product): int
    {
        return (int) $product->getData('rating_summary');
    }

    /**
     * Average rating expressed on a 0-5 scale, rounded to one decimal.
     */
    public function getRatingValue(ProductInterface $product): float
    {
        return round($this->getRatingPercent($product) / 20, 1);
    }

    public function getReviewsCount(ProductInterface $product): int
    {
        return (int) $product->getData('reviews_count');
    }

    /**
     * Formatted "from" price (works for simple and configurable products via the
     * price index column added by addPriceData()).
     */
    public function getPriceHtml(ProductInterface $product): string
    {
        $price = (float) $product->getData('min_price');

        return $this->priceCurrency->format($price, false);
    }
}
