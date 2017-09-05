<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\MarketingCampaignsReporting\Reports;

use Piwik\EventDispatcher;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignName;

class GetName extends Base
{
    protected function init()
    {
        parent::init();
        $this->dimension             = new CampaignName();
        $this->name                  = Piwik::translate('MarketingCampaignsReporting_Names');
        $this->actionToLoadSubTables = 'getKeywordContentFromNameId';
        $this->hasGoalMetrics        = true;
        $this->order                 = 1;

        $this->subcategoryId = 'Referrers_Campaigns';
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);
        $view->config->subtable_controller_action = 'getKeywordContentFromNameId';
        $this->configureFooterMessage($view);
    }


    protected function configureFooterMessage(ViewDataTable $view)
    {
        if ($this->isSubtableReport) {
            // no footer message for subtables
            return;
        }

        $out = '';
        EventDispatcher::getInstance()->postEvent('Template.afterCampaignsReport', array(&$out));
        $view->config->show_footer_message = $out;
    }
}
