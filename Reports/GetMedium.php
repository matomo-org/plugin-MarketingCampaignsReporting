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
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignMedium;

class GetMedium extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension      = new CampaignMedium();
        $this->name           = Piwik::translate('MarketingCampaignsReporting_Mediums');
        $this->documentation  = '<b>' . Piwik::translate('Referrers_AttributionTitle') . ':</b> ' . Piwik::translate('Referrers_AttributionDocumentation');
        $this->hasGoalMetrics = true;
        $this->order          = 4;

        $this->subcategoryId = 'Referrers_Campaigns';
    }
}
