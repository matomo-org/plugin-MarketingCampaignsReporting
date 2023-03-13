<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignSourceMedium;

class GetSourceMedium extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension             = new CampaignSourceMedium();
        $this->name                  = Piwik::translate('MarketingCampaignsReporting_CombinedSourcesMediums');
        $this->documentation         = '<b>' . Piwik::translate('MarketingCampaignsReporting_AttributionTitle') . ':</b> ' . Piwik::translate('MarketingCampaignsReporting_AttributionDocumentation', ['<a href="https://matomo.org/faq/general/faq_106/" rel="noreferrer noopener" target="_blank">', '</a>']);
        $this->actionToLoadSubTables = 'getNameFromSourceMediumId';
        $this->hasGoalMetrics        = true;
        $this->order                 = 6;

        $this->subcategoryId = 'Referrers_Campaigns';
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);
        $view->config->subtable_controller_action = 'getNameFromSourceMediumId';
    }
}
