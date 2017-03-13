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
use Piwik\Plugins\AdvancedCampaignReporting\Columns\CampaignSourceMedium;

class GetSourceMedium extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension             = new CampaignSourceMedium();
        $this->name                  = Piwik::translate('AdvancedCampaignReporting_CombinedSourcesMediums');
        $this->actionToLoadSubTables = 'getNameFromSourceMediumId';
        $this->hasGoalMetrics        = true;
        $this->order                 = 6;

        $this->subcategoryId = 'Referrers_Campaigns';
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);
        $view->config->subtable_controller_action = 'getNameFromSourceMediumId';
    }
}
