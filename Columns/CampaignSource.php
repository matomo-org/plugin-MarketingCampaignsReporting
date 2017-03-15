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

class CampaignSource extends Base
{
    protected $columnName = 'campaign_source';
    protected $columnType = 'VARCHAR(255) NULL';

    protected function configureSegments()
    {
        $segment = new Segment();
        $segment->setSegment('campaignSource');
        $segment->setName('MarketingCampaignsReporting_Source');
        $this->addSegment($segment);
    }

    public function getName()
    {
        return Piwik::translate('MarketingCampaignsReporting_Source');
    }
}
