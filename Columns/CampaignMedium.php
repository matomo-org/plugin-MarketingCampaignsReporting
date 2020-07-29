<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

class CampaignMedium extends Base
{
    protected $columnName = 'campaign_medium';
    protected $columnType = 'VARCHAR(255) NULL';
    protected $segmentName = 'campaignMedium';
    protected $nameSingular = 'MarketingCampaignsReporting_Medium';
}
