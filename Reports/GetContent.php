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
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignContent;

class GetContent extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension      = new CampaignContent();
        $this->name           = Piwik::translate('MarketingCampaignsReporting_Contents');
        $this->documentation  = '<b>' . Piwik::translate('MarketingCampaignsReporting_AttributionTitle') . ':</b> ' . Piwik::translate('MarketingCampaignsReporting_AttributionDocumentation');
        $this->hasGoalMetrics = true;
        $this->order          = 5;

        $this->subcategoryId = 'Referrers_Campaigns';
    }
}
