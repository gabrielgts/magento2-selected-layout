<?php

/**
 * @author Gtstudio
 * @copyright Copyright (c) 2023 Gtstudio
 */

declare(strict_types=1);

namespace Gtstudio\SelectedLayout\Plugin;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category\Attribute\LayoutUpdateManager;
use Magento\Framework\Api\CustomAttributesDataInterface;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Magento\Framework\View\Design\Theme\FlyweightFactory;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Model\Layout\Merge as LayoutProcessor;
use Magento\Framework\View\Model\Layout\MergeFactory as LayoutProcessorFactory;

class CategorySelectedLayout
{
    private FlyweightFactory $themeFactory,
    private DesignInterface $design,
    private LayoutProcessorFactory $layoutProcessorFactory

    public function __construct(
        FlyweightFactory $themeFactory,
        DesignInterface $design,
        LayoutProcessorFactory $layoutProcessorFactory
    ) {
        $this->themeFactory = $themeFactory;
        $this->design = $design;
        $this->layoutProcessorFactory = $layoutProcessorFactory;
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
     * @param LayoutUpdateManager $subject
     * @param $result
     * @param CategoryInterface $category
     * @return array
     */
    public function afterFetchAvailableFiles(LayoutUpdateManager $subject, $result, CategoryInterface $category)
    {
        if (!$category->getId()) {
            return [];
        }

        $handles = $this->getLayoutProcessor()->getAvailableHandles();

        return array_filter(
            array_map(
                function (string $handle) use ($category): ?string {
                    preg_match(
                        '/^catalog\_category\_view\_selectable\_(' . $category->getId() . '|all)\_([a-z0-9]+)/i',
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
     * Extract custom layout attribute value.
     *
     * @param CategoryInterface $category
     * @return mixed
     */
    private function extractAttributeValue(CategoryInterface $category): mixed
    {
        if ($category instanceof Category && !$category->hasData(CustomAttributesDataInterface::CUSTOM_ATTRIBUTES)) {
            return $category->getData('custom_layout_update_file');
        }
        if ($attr = $category->getCustomAttribute('custom_layout_update_file')) {
            return $attr->getValue();
        }

        return null;
    }

    /**
     * @param LayoutUpdateManager $subject
     * @param null $result
     * @param CategoryInterface $category
     * @param DataObject $intoSettings
     * @return void
     */
    public function afterExtractCustomSettings(
        LayoutUpdateManager $subject,
        $result,
        CategoryInterface $category,
        DataObject $intoSettings
    ): void {
        if ($category->getId() && $value = $this->extractAttributeValue($category)) {
            $handles = $intoSettings->getPageLayoutHandles() ?? [];
            $handles = array_merge(
                $handles,
                ['selectable' => $value]
            );
            $intoSettings->setPageLayoutHandles($handles);
        }
    }
}
