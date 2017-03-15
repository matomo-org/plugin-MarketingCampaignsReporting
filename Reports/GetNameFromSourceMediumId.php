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
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignName;
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignSourceMedium;

class GetNameFromSourceMediumId extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension        = new CampaignName();
        $this->name             = Piwik::translate('AdvancedCampaignReporting_CombinedSourcesMediums');
        $this->isSubtableReport = true;
        $this->hasGoalMetrics   = true;
    }
}
