<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * Based on code from AdvancedCampaignReporting plugin by Piwik PRO released under GPL v3 or later:
 * https://github.com/PiwikPRO/plugin-AdvancedCampaignReporting
 */

namespace Piwik\Plugins\MarketingCampaignsReporting;

use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Plugin;
use Piwik\Plugin\Report;
use Piwik\Plugins\MarketingCampaignsReporting\Columns\Base;
use Piwik\Plugins\Referrers\Reports\GetCampaigns;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

/**
 * @package MarketingCampaignsReporting
 */
class MarketingCampaignsReporting extends Plugin
{
    private const CAMPAIGN_VALUES_MASKED_SETTING_CLASS = 'Piwik\\Plugins\\PrivacyManager\\Settings\\CampaignParameterValuesMasked';
    private const CONSENT_COLUMN_CLASS = 'Piwik\\Plugins\\CoreHome\\Columns\\Consent';

    public static $CAMPAIGN_NAME_FIELD_DEFAULT_URL_PARAMS = array('mtm_campaign', 'matomo_campaign', 'mtm_cpn', 'pk_campaign', 'piwik_campaign', 'pk_cpn', 'utm_campaign');
    public static $CAMPAIGN_KEYWORD_FIELD_DEFAULT_URL_PARAMS = array('mtm_keyword', 'matomo_kwd', 'mtm_kwd', 'pk_keyword', 'piwik_kwd', 'pk_kwd', 'utm_term');
    public static $CAMPAIGN_SOURCE_FIELD_DEFAULT_URL_PARAMS = array('mtm_source', 'pk_source', 'utm_source');
    public static $CAMPAIGN_MEDIUM_FIELD_DEFAULT_URL_PARAMS = array('mtm_medium', 'pk_medium', 'utm_medium');
    public static $CAMPAIGN_CONTENT_FIELD_DEFAULT_URL_PARAMS = array('mtm_content', 'pk_content', 'utm_content');
    public static $CAMPAIGN_ID_FIELD_DEFAULT_URL_PARAMS = array('mtm_cid', 'pk_cid', 'utm_id', 'mtm_clid');
    public static $CAMPAIGN_GROUP_FIELD_DEFAULT_URL_PARAMS = array('mtm_group', 'pk_group');
    public static $CAMPAIGN_PLACEMENT_FIELD_DEFAULT_URL_PARAMS = array('mtm_placement', 'pk_placement');

    public function registerEvents()
    {
        return array(
            'Tracker.PageUrl.getQueryParametersToExclude' => 'getQueryParametersToExclude',
            'Report.filterReports'                        => 'removeOriginalCampaignReport',
            'Insights.addReportToOverview'                => 'addReportToInsightsOverview',
            'AssetManager.getStylesheetFiles'             => 'getStylesheetFiles',
            'Plugin.shouldLowerCampaignCase'              => 'shouldLowerCampaignCase',
        );
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/MarketingCampaignsReporting/stylesheets/styles.less";
    }

    public function install()
    {
        $tables = \Piwik\DbHelper::getTablesInstalled();
        foreach ($tables as $tableName) {
            if (strpos($tableName, 'archive_blob_') !== false || strpos($tableName, 'archive_numeric_') !== false) {
                Db::exec('UPDATE `' . $tableName . '` SET `name`=REPLACE(`name`, \'AdvancedCampaignReporting_\', \'MarketingCampaignsReporting_\') WHERE `name` LIKE \'AdvancedCampaignReporting_%\'');
            }
        }

        Plugin\Manager::getInstance()->deactivatePlugin('AdvancedCampaignReporting');
    }

    public function getQueryParametersToExclude(&$excludedParameters)
    {
        $advancedCampaignParameters = self::getCampaignParameters();

        foreach ($advancedCampaignParameters as $advancedCampaignParameter) {
            $excludedParameters = array_merge($excludedParameters, $advancedCampaignParameter);
        }
    }

    public function addReportToInsightsOverview(&$reports)
    {
        unset($reports['Referrers_getCampaigns']);
        $reports['MarketingCampaignsReporting_getName'] = [];
    }

    /**
     * @return array
     */
    public static function getCampaignParameters()
    {
        return array_merge(
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_name'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_keyword'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_source'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_medium'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_content'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_id'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_group'),
            StaticContainer::get('advanced_campaign_reporting.uri_parameters.campaign_placement')
        );
    }

    /**
     * @param Report[] $reports
     */
    public function removeOriginalCampaignReport(&$reports)
    {
        foreach ($reports as $index => $report) {
            if ($report instanceof GetCampaigns) {
                unset($reports[$index]);
            }
        }
    }

    public static function getAdvancedCampaignFields()
    {
        $dimensions     = Base::getDimensions(new self());
        $campaignFields = [];

        foreach ($dimensions as $dimension) {
            $campaignFields[] = $dimension->getColumnName();
        }

        return $campaignFields;
    }

    public static function isCampaignValuesMaskingEnabled(?int $idSite): bool
    {
        $settingClass = self::getCampaignValuesMaskedSettingClass();
        if (empty($settingClass)) {
            return false;
        }

        return $settingClass::isEnabled($idSite);
    }

    public static function getCampaignPlaceholderValue(): string
    {
        $settingClass = self::getCampaignValuesMaskedSettingClass();
        if (empty($settingClass)) {
            return '';
        }

        return $settingClass::getPlaceholderValue();
    }

    /**
     * Formats stored campaign values for display.
     *
     * Accepts mixed input because callers may pass placeholder values or raw
     * dimension values without first narrowing the type.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function formatCampaignValue($value)
    {
        $settingClass = self::getCampaignValuesMaskedSettingClass();
        if (empty($settingClass)) {
            return $value;
        }

        return $settingClass::formatValue($value);
    }

    /**
     * Formats labels composed of multiple campaign dimensions separated by the
     * archive label separator.
     *
     * if all campaign dimensions are masked by policy, then a single placeholder
     * value is returned, rather than several separated by SEPARATOR_COMBINED_DIMENSIONS
     *
     * @param mixed $value
     */
    public static function formatCombinedCampaignValue($value): string
    {
        $parts = explode(Archiver::SEPARATOR_COMBINED_DIMENSIONS, (string) $value);
        $placeholder = self::getCampaignPlaceholderValue();
        $nonEmptyParts = array_values(array_filter($parts, function ($part) {
            return $part !== '';
        }));

        if (!empty($nonEmptyParts) && $placeholder !== '') {
            $allPartsAreMasked = true;

            foreach ($nonEmptyParts as $part) {
                if ($part !== $placeholder) {
                    $allPartsAreMasked = false;
                    break;
                }
            }

            if ($allPartsAreMasked) {
                return (string) self::formatCampaignValue($placeholder);
            }
        }

        $parts = array_map([self::class, 'formatCampaignValue'], $parts);

        return implode(Archiver::SEPARATOR_COMBINED_DIMENSIONS, $parts);
    }

    /**
     * Whether this request reports consent, on a core that measures consent-aware analytics.
     *
     * A core without it cannot say, and every visitor is then one who has not consented, which
     * is how this plugin has always treated them.
     */
    public static function requestHasConsent(Request $request): bool
    {
        return true === self::callCoreMethod($request, 'hasConsent');
    }

    /**
     * Whether consent was recorded against the visit, on a core that measures consent-aware
     * analytics.
     */
    public static function visitHasConsented(Visitor $visitor): bool
    {
        return true === self::callCoreMethod(self::CONSENT_COLUMN_CLASS, 'hasVisitConsented', [$visitor]);
    }

    /**
     * Calls a core method that only the Matomo versions supporting consent-aware analytics have.
     *
     * The method is reached by name, as the masking setting above is reached by class name, so
     * that the plugin analyses cleanly against the versions that have it and those that do not.
     *
     * @param object|string $target an instance, or a class name for a static method
     * @param mixed[] $arguments
     * @return mixed null where this core does not have the method
     */
    private static function callCoreMethod($target, string $method, array $arguments = [])
    {
        if (!method_exists($target, $method)) {
            return null;
        }

        return call_user_func_array([$target, $method], $arguments);
    }

    private static function getCampaignValuesMaskedSettingClass(): ?string
    {
        if (!class_exists(self::CAMPAIGN_VALUES_MASKED_SETTING_CLASS)) {
            return null;
        }

        return self::CAMPAIGN_VALUES_MASKED_SETTING_CLASS;
    }

    public function isTrackerPlugin()
    {
        return true;
    }

    public function shouldLowerCampaignCase($pluginName, &$shouldLowerCampaignCase)
    {
        $systemSettings = StaticContainer::get(SystemSettings::class);
        $shouldLowerCampaignCase = !$systemSettings->doNotChangeCaseOfUtmParameters->getValue();
    }
}
