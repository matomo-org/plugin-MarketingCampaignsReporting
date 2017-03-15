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
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CombinedKeywordContent;

class GetKeywordContentFromNameId extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension        = new CombinedKeywordContent();
        $this->name             = Piwik::translate('AdvancedCampaignReporting_CombinedKeywordContent');
        $this->isSubtableReport = true;
        $this->hasGoalMetrics   = true;
    }
}
