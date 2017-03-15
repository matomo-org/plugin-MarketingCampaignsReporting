<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\AdvancedCampaignReporting\Columns;

use Piwik\Piwik;
use Piwik\Plugins\AdvancedCampaignReporting\Segment;

class CampaignId extends Base
{
    protected $columnName = 'campaign_id';
    protected $columnType = 'VARCHAR(100) NULL';

    protected function configureSegments()
    {
        $segment = new Segment();
        $segment->setSegment('campaignId');
        $segment->setName('AdvancedCampaignReporting_CampaignId');
        $this->addSegment($segment);
    }

    public function getName()
    {
        return Piwik::translate('AdvancedCampaignReporting_CampaignId');
    }
}
