<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Reports;

use Piwik\Plugin\ViewDataTable;

abstract class Base extends \Piwik\Plugin\Report
{
    protected function init()
    {
        $this->categoryId = 'Referrers_Referrers';
        $this->onlineGuideUrl = 'https://matomo.org/docs/tracking-campaigns/';
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->addTranslation('label', $this->dimension->getName());
    }
}
