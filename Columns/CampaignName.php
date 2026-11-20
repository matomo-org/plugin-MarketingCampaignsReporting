<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MarketingCampaignsReporting\Columns;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\MarketingCampaignsReporting\Campaign\CampaignDetector;
use Piwik\Plugins\MarketingCampaignsReporting\Campaign\CampaignDetectorInterface;
use Piwik\Plugins\MarketingCampaignsReporting\MarketingCampaignsReporting;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\TrackerConfig;
use Piwik\Tracker\Visitor;

class CampaignName extends Base
{
    protected $columnName = 'campaign_name';
    protected $columnType = 'VARCHAR(255) NULL';
    protected $segmentName = 'campaignName';
    protected $nameSingular = 'MarketingCampaignsReporting_Name';

    /**
     * If we should create a new visit when the campaign changes, check if the campaign info changed and if so
     * force the tracker to create a new visit.i
     *
     * @param Request     $request
     * @param Visitor     $visitor
     * @param Action|null $action
     * @return bool
     */
    public function shouldForceNewVisit(Request $request, Visitor $visitor, ?Action $action = null)
    {
        if (TrackerConfig::getConfigValue('create_new_visit_when_campaign_changes', $request->getIdSiteIfExists()) != 1) {
            return false;
        }

        /** @var CampaignDetector|CampaignDetectorInterface $campaignDetector */
        $campaignDetector   = StaticContainer::get('advanced_campaign_reporting.campaign_detector');
        $campaignParameters = MarketingCampaignsReporting::getCampaignParameters();

        $campaignDimensions = $campaignDetector->detectCampaignFromRequest(
            $request,
            $campaignParameters
        );
        // Never start a new visit, if the visit was detected as AI Assistant by core, unless
        // there are campaign parameters detected, that do not resolve to an AI Assistant.
        // This is a hacky workaround to solves issues where randomly new visits are started when
        // e.g. someone comes from ChatGPT having the `utm_source=chatgpt.com` url parameter.
        // That one will be detected as AI Assistant by core. But if someone reloads that page
        // and the utm source is still present, the check here might otherwise force a new visit,
        // if the `utm_source` parameter is configured.
        if ((int)$visitor->getVisitorColumn('referer_type') === 8 && !empty($campaignDimensions) && count($campaignDimensions) === 1) {
            $paramValue = reset($campaignDimensions);
            if (class_exists('Piwik\Plugins\Referrers\AIAssistant') && \Piwik\Plugins\Referrers\AIAssistant::getInstance()->getAIAssistantFromDomain($paramValue)) {
                return false;
            }
        }

        // Normalise only after the AI Assistant check, as masking replaces the raw values needed there
        $campaignDimensions = $this->normalizeDetectedCampaignDimensionsForConsent(
            $campaignDimensions,
            (int) $request->getIdSiteIfExists(),
            $this->hasVisitorConsented($request, $visitor)
        );

        // we force a new visit if the referrer is a campaign and it's different than the currently recorded referrer.
        // if the current referrer is 'direct entry', however, we assume the referrer information was sent in a later request, and
        // we just update the existing referrer information instead of creating a visit.
        if (
            !empty($campaignDimensions)
            && $this->isCampaignInformationNewForRequest($visitor, $campaignDimensions, $action, $request)
            && !$this->consentRevealsArrival($request, $visitor, $action)
        ) {
            Common::printDebug("Existing visit detected, but creating new visit because campaign information is different than last action.");

            return true;
        }

        return false;
    }

    protected function isCampaignInformationNew(Visitor $visitor, $campaignDimensions)
    {
        foreach (MarketingCampaignsReporting::getAdvancedCampaignFields() as $infoName) {
            if ($this->hasReferrerColumnChanged($visitor, $campaignDimensions, $infoName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * As isCampaignInformationNew(), for a request that may carry the campaign a policy discarded.
     *
     * This is a method of its own so that isCampaignInformationNew() keeps the signature a plugin
     * extending this dimension may already be overriding, as core does for its referrer columns.
     *
     * @param Action|null $action
     */
    protected function isCampaignInformationNewForRequest(Visitor $visitor, $campaignDimensions, $action, Request $request)
    {
        foreach (MarketingCampaignsReporting::getAdvancedCampaignFields() as $infoName) {
            if ($this->hasReferrerColumnChangedForRequest($visitor, $campaignDimensions, $infoName, $action, $request)) {
                return true;
            }
        }
        return false;
    }

    protected function hasReferrerColumnChanged(Visitor $visitor, $information, $infoName)
    {
        $existing = Common::mb_strtolower($visitor->getVisitorColumn($infoName) ?? '');
        $new      = isset($information[$infoName]) ? Common::mb_strtolower($information[$infoName]) : false;

        $result = $existing != $new;
        if ($result) {
            Common::printDebug("Referrers\Base::isReferrerInformationNew: detected change in $infoName ('$existing' != '$new').");
        }

        return $result;
    }

    /**
     * As hasReferrerColumnChanged(), telling a discarded campaign apart from a different one.
     *
     * @param Action|null $action
     */
    protected function hasReferrerColumnChangedForRequest(Visitor $visitor, $information, $infoName, $action, Request $request)
    {
        $existing = Common::mb_strtolower($visitor->getVisitorColumn($infoName) ?? '');
        $new      = isset($information[$infoName]) ? Common::mb_strtolower($information[$infoName]) : false;

        // the incoming campaign was discarded as well, so it stands for the stored one rather than
        // for a different campaign, and must not count as a change
        if ($this->isDiscardedValue($new)) {
            return false;
        }

        // a discarded campaign kept nothing to compare an incoming one against, so the two can
        // only be told apart while the visit is still on the page it arrived on
        if ($this->isDiscardedValue($existing)) {
            return false === $this->isRequestOnEntryPage($visitor, $action, $request);
        }

        return $this->hasReferrerColumnChanged($visitor, $information, $infoName);
    }
}
