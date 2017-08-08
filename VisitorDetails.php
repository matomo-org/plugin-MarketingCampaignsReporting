<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\MarketingCampaignsReporting;

use Piwik\Piwik;
use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\View;

class VisitorDetails extends VisitorDetailsAbstract
{
    public function extendVisitorDetails(&$visitor)
    {
        $fields = array(
            'campaignId'      => 'campaign_id',
            'campaignContent' => 'campaign_content',
            'campaignKeyword' => 'campaign_keyword',
            'campaignMedium'  => 'campaign_medium',
            'campaignName'    => 'campaign_name',
            'campaignSource'  => 'campaign_source',
        );

        foreach ($fields as $name => $field) {
            $visitor[$name] = $this->details[$field];
        }
    }

    public function renderVisitorDetails($visitorDetails)
    {
        $campaignData = array();
        $fields       = array(
            'campaignId'      => Piwik::translate('MarketingCampaignsReporting_CampaignId'),
            'campaignName'    => Piwik::translate('MarketingCampaignsReporting_Name'),
            'campaignMedium'  => Piwik::translate('MarketingCampaignsReporting_Medium'),
            'campaignContent' => Piwik::translate('MarketingCampaignsReporting_Content'),
            'campaignKeyword' => Piwik::translate('MarketingCampaignsReporting_Keyword'),
            'campaignSource'  => Piwik::translate('MarketingCampaignsReporting_Source'),
        );

        foreach ($fields as $field => $name) {
            if (!empty($visitorDetails[$field])) {
                $campaignData[$name] = $visitorDetails[$field];
            }
        }

        $view           = new View('@MarketingCampaignsReporting/visitorDetails');
        $view->campaign = $campaignData;
        return [[ 30, $view->render() ]];
    }
}