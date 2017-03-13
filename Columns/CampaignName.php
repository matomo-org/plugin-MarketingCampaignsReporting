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

class CampaignName extends Base
{
    protected $columnName = 'campaign_name';
    protected $columnType = 'VARCHAR(255) NULL';

    protected function configureSegments()
    {
        $segment = new Segment();
        $segment->setSegment('campaignName');
        $segment->setName('AdvancedCampaignReporting_Name');
        $this->addSegment($segment);
    }

    public function getName()
    {
        return Piwik::translate('AdvancedCampaignReporting_Name');
    }
}
