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
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignKeyword;

class GetKeyword extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension      = new CampaignKeyword();
        $this->name           = Piwik::translate('MarketingCampaignsReporting_Keywords');
        $this->documentation  = '<b>' . Piwik::translate('MarketingCampaignsReporting_AttributionTitle') . ':</b> ' . Piwik::translate('MarketingCampaignsReporting_AttributionDocumentation', ['<a href="https://matomo.org/faq/general/faq_106/" rel="noreferrer noopener" target="_blank">', '</a>']);
        $this->hasGoalMetrics = true;
        $this->order          = 2;

        $this->subcategoryId = 'Referrers_Campaigns';
    }
}
