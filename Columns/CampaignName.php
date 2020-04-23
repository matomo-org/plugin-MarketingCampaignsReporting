<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

class CampaignName extends Base
{
    protected $columnName = 'campaign_name';
    protected $columnType = 'VARCHAR(255) NULL';
    protected $segmentName = 'campaignName';
    protected $nameSingular = 'MarketingCampaignsReporting_Name';
}
