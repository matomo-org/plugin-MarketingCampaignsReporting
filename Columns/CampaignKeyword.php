<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

use Piwik\Columns\DimensionSegmentFactory;
use Piwik\Piwik;
use Piwik\Plugins\MarketingCampaignsReporting\Segment;
use Piwik\Segment\SegmentsList;

class CampaignKeyword extends Base
{
    protected $columnName = 'campaign_keyword';
    protected $columnType = 'VARCHAR(255) NULL';
    protected $segmentName = 'campaignKeyword';
    protected $nameSingular = 'MarketingCampaignsReporting_Keyword';

    public function getName()
    {
        return Piwik::translate('MarketingCampaignsReporting_Keyword');
    }
}
