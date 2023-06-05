<?php

/**
 * @author Gtstudio
 * @copyright Copyright (c) 2023 Gtstudio
 */

declare(strict_types=1);

namespace Gtstudio\SelectedLayout\Plugin;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Page\CustomLayout\CustomLayoutManager;
use Magento\Cms\Model\Page\CustomLayout\Data\CustomLayoutSelectedInterface;
use Magento\Cms\Model\Page\IdentityMap;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Design\Theme\FlyweightFactory;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Model\Layout\Merge as LayoutProcessor;
use Magento\Framework\View\Model\Layout\MergeFactory as LayoutProcessorFactory;
use Magento\Framework\View\Result\Page as PageLayout;

class CmsSelectableLayout
{
    public function __construct(
        private LayoutProcessorFactory $layoutProcessorFactory,
        private FlyweightFactory $themeFactory,
        private DesignInterface $design,
        private IdentityMap $identityMap,
        private PageRepositoryInterface $pageRepository
    ) {
    }

    /**
     * Adopt page's identifier to be used as layout handle.
     *
     * @param PageInterface $page
     * @return string
     */
    private function sanitizeIdentifier(PageInterface $page): string
    {
        return $page->getIdentifier() === null ? '' : str_replace('/', '_', $page->getIdentifier());
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
     * @param CustomLayoutManager $subject
     * @param array $result
     * @param PageInterface $page
     * @return array
     */
    public function afterFetchAvailableFiles(CustomLayoutManager $subject, array $result, PageInterface $page): array
    {
        $identifier = $this->sanitizeIdentifier($page);
        $handles = $this->getLayoutProcessor()->getAvailableHandles();

        return array_filter(
            array_map(
                function (string $handle) use ($identifier): ?string {
                    preg_match(
                        '/^cms\_page\_view\_selectable\_(' . preg_quote($identifier) . '|all)\_([a-z0-9]+)/i',
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
     * @param CustomLayoutManager $subject
     * @param null $result
     * @param PageLayout $layout
     * @param CustomLayoutSelectedInterface $layoutSelected
     * @return void
     * @throws LocalizedException
     */
    public function afterApplyUpdate(
        CustomLayoutManager $subject,
        $result,
        PageLayout $layout,
        CustomLayoutSelectedInterface $layoutSelected
    ): void {
        $page = $this->identityMap->get($layoutSelected->getPageId());
        if (!$page) {
            $page = $this->pageRepository->getById($layoutSelected->getPageId());
        }

        $layout->addPageLayoutHandles(
            ['selectable' => $layoutSelected->getLayoutFileId()],
            'cms_page_view'
        );
    }
}
