<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

class CampaignId extends Base
{
    protected $columnName = 'campaign_id';
    protected $columnType = 'VARCHAR(100) NULL';
    protected $segmentName = 'campaignId';
    protected $nameSingular = 'MarketingCampaignsReporting_CampaignId';
}
