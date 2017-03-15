<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

use Piwik\Piwik;
use Piwik\Plugins\MarketingCampaignsReporting\Segment;

class CampaignKeyword extends Base
{
    protected $columnName = 'campaign_keyword';
    protected $columnType = 'VARCHAR(255) NULL';

    protected function configureSegments()
    {
        $segment = new Segment();
        $segment->setSegment('campaignKeyword');
        $segment->setName('MarketingCampaignsReporting_Keyword');
        $this->addSegment($segment);
    }

    public function getName()
    {
        return Piwik::translate('MarketingCampaignsReporting_Keyword');
    }
}
