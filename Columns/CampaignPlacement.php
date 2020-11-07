<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

class CampaignPlacement extends Base
{
    protected $columnName = 'campaign_placement';
    protected $columnType = 'VARCHAR(100) NULL';
    protected $segmentName = 'campaignPlacement';
    protected $nameSingular = 'MarketingCampaignsReporting_Placement';
}
