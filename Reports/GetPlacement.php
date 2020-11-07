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
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignPlacement;

class GetPlacement extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension      = new CampaignPlacement();
        $this->name           = Piwik::translate('MarketingCampaignsReporting_Placements');
        $this->hasGoalMetrics = true;
        $this->order          = 9;

        $this->subcategoryId = 'Referrers_Campaigns';
    }
}
