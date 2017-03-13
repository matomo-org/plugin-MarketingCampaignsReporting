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

class GetName extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension             = new CampaignName();
        $this->name                  = Piwik::translate('AdvancedCampaignReporting_Names');
        $this->actionToLoadSubTables = 'getKeywordContentFromNameId';
        $this->hasGoalMetrics        = true;
        $this->order                 = 1;

        $this->subcategoryId = 'Referrers_Campaigns';
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);
        $view->config->subtable_controller_action = 'getKeywordContentFromNameId';
    }
}
