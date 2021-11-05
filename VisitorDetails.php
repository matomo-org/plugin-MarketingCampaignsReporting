<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
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
            'campaignGroup'   => 'campaign_group',
            'campaignPlacement'  => 'campaign_placement',
        );

        foreach ($fields as $name => $field) {
            $visitor[$name] = empty($this->details[$field]) ? '' : $this->details[$field];
        }
    }

    public function renderVisitorDetails($visitorDetails)
    {
        $campaignData = [];
        $fields       = array(
            'campaignId'      => Piwik::translate('MarketingCampaignsReporting_CampaignId'),
            'campaignName'    => Piwik::translate('MarketingCampaignsReporting_Name'),
            'campaignMedium'  => Piwik::translate('MarketingCampaignsReporting_Medium'),
            'campaignContent' => Piwik::translate('MarketingCampaignsReporting_Content'),
            'campaignKeyword' => Piwik::translate('MarketingCampaignsReporting_Keyword'),
            'campaignSource'  => Piwik::translate('MarketingCampaignsReporting_Source'),
            'campaignGroup'  => Piwik::translate('MarketingCampaignsReporting_Group'),
            'campaignPlacement'  => Piwik::translate('MarketingCampaignsReporting_Placement'),
        );

        foreach ($fields as $field => $name) {
            if (!empty($visitorDetails[$field])) {
                $campaignData[$name] = html_entity_decode($visitorDetails[$field], ENT_QUOTES, 'UTF-8');
            }
        }

        $view                           = new View('@MarketingCampaignsReporting/visitorDetails');
        $view->sendHeadersWhenRendering = false;
        $view->campaign                 = $campaignData;
        return [[30, $view->render()]];
    }
}
