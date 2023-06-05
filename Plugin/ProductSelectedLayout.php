<?php

/**
 * @author Gtstudio
 * @copyright Copyright (c) 2023 Gtstudio
 */

declare(strict_types=1);

namespace Gtstudio\SelectedLayout\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\LayoutUpdateManager;
use Magento\Framework\Api\CustomAttributesDataInterface;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Magento\Framework\View\Design\Theme\FlyweightFactory;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Model\Layout\Merge as LayoutProcessor;
use Magento\Framework\View\Model\Layout\MergeFactory as LayoutProcessorFactory;

class ProductSelectedLayout
{
    public function __construct(
        private FlyweightFactory $themeFactory,
        private DesignInterface $design,
        private LayoutProcessorFactory $layoutProcessorFactory
    ) {
    }

    /**
     * @param LayoutUpdateManager $subject
     * @param $result
     * @param ProductInterface $product
     * @return array
     */
    public function afterFetchAvailableFiles(LayoutUpdateManager $subject, $result, ProductInterface $product)
    {
        if (!$product->getSku()) {
            return [];
        }

        $identifier = $this->sanitizeSku($product);
        $handles = $this->getLayoutProcessor()->getAvailableHandles();

        return array_filter(
            array_map(
                function (string $handle) use ($identifier): ?string {
                    preg_match(
                        '/^catalog\_product\_view\_selectable\_(' . preg_quote($identifier) . '|all)\_([a-z0-9]+)/i',
                        $handle,
                        $selectable
                    );
                    if (!empty($selectable[2])) {
                        return "{$selectable[1]}_{$selectable[2]}";
                    }

                    return null;
                },
                $handles
            )
        );
    }

    /**
     * Get the processor instance.
     *
     * @return LayoutProcessor
     */
    private function getLayoutProcessor(): LayoutProcessor
    {
        return $this->layoutProcessorFactory->create(
            [
                'theme' => $this->themeFactory->create(
                    $this->design->getConfigurationDesignTheme(Area::AREA_FRONTEND)
                )
            ]
        );
    }

    /**
     * Extract custom layout attribute value.
     *
     * @param ProductInterface $product
     * @return mixed
     */
    private function extractAttributeValue(ProductInterface $product)
    {
        if ($product instanceof Product && !$product->hasData(CustomAttributesDataInterface::CUSTOM_ATTRIBUTES)) {
            return $product->getData('custom_layout_update_file');
        }
        if ($attr = $product->getCustomAttribute('custom_layout_update_file')) {
            return $attr->getValue();
        }

        return null;
    }

    /**
     * Adopt product's SKU to be used as layout handle.
     *
     * @param ProductInterface $product
     * @return string
     */
    private function sanitizeSku(ProductInterface $product): string
    {
        return rawurlencode($product->getSku());
    }

    /**
     * @param LayoutUpdateManager $subject
     * @param null $result
     * @param ProductInterface $product
     * @param DataObject $intoSettings
     * @return void
     */
    public function afterExtractCustomSettings(
        LayoutUpdateManager $subject,
        $result,
        ProductInterface $product,
        DataObject $intoSettings
    ): void {
        if ($product->getSku() && $value = $this->extractAttributeValue($product)) {
            $handles = $intoSettings->getPageLayoutHandles() ?? [];
            $handles = array_merge(
                $handles,
                ['selectable' => $value]
            );
            $intoSettings->setPageLayoutHandles($handles);
        }
    }
}
