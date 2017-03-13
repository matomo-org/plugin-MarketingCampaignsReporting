<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\AdvancedCampaignReporting\Reports;

use Piwik\Piwik;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignMedium;

class GetMedium extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension      = new CampaignMedium();
        $this->name           = Piwik::translate('AdvancedCampaignReporting_Mediums');
        $this->hasGoalMetrics = true;
        $this->order          = 4;

        $this->subcategoryId = 'Referrers_Campaigns';
    }
}
