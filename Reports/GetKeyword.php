<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
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
        $this->hasGoalMetrics = true;
        $this->order          = 2;

        $this->subcategoryId = 'Referrers_Campaigns';
    }
}
