<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

class CampaignContent extends Base
{
    protected $columnName = 'campaign_content';
    protected $columnType = 'VARCHAR(255) NULL';
    protected $segmentName = 'campaignContent';
    protected $nameSingular = 'MarketingCampaignsReporting_Content';
}
