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
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignName;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignSourceMedium;

class GetNameFromSourceMediumId extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension        = new CampaignName();
        $this->name             = Piwik::translate('MarketingCampaignsReporting_CombinedSourcesMediums');
        $this->isSubtableReport = true;
    }
}
