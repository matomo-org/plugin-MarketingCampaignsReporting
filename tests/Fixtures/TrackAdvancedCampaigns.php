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

namespace Piwik\Plugins\MarketingCampaignsReporting\tests\Fixtures;

use Piwik;
use Piwik\Date;
use Piwik\Tests\Framework\Fixture;

class TrackAdvancedCampaigns extends Fixture
{
    public $dateTime = '2013-01-22 01:23:45';
    public $dateTimeWithPluginEnabled = '2013-01-23 01:23:45';
    public $idSite = 1;

    private $orderIndex;

    const THIS_PAGE_VIEW_IS_GOAL_CONVERSION = 'this is a goal conversion';

    public function setUp(): void
    {
        Piwik\Plugin\Manager::getInstance()->activatePlugin('MarketingCampaignsReporting');

        $this->orderIndex = 0;

        $this->setUpWebsite();

        $this->trackCampaignVisits($disablePlugin = true, $this->dateTime);
        $this->trackCampaignVisits($disablePlugin = false, $this->dateTimeWithPluginEnabled);
    }

    public function trackCampaignVisits($disablePlugin, $dateTime)
    {
        // since we're changing the list of activated plugins, we have to make sure file caches are reset
        Piwik\Cache::flushAll();

        $testVars = new Piwik\Tests\Framework\TestingEnvironmentVariables();
        if ($disablePlugin) {
            $testVars->_disableMarketingCampaignsReporting = true;
        } else {
            $testVars->_disableMarketingCampaignsReporting = false;
        }
        $testVars->save();

        // Track one visitor, with returning visit with advanced campaign use cases
        $t = self::getTracker($this->idSite, $dateTime, $defaultInit = true, $useLocal = false);

        $this->trackFirstVisit_withGoogleAnalyticsParameters($t, $dateTime);
        $this->trackSecondVisit_withPiwikCampaignParameters($t, $dateTime);
        $this->trackThirdVisit_withStandardCampaignOnly($t, $dateTime);
        $this->trackFourthVisit_withDimensionsInUrlHash($t, $dateTime);
        $this->trackFifthVisit_withCampaignNameOnly($t, $dateTime);
        $this->trackSixthVisit_withSuperLongLabels($t, $dateTime);
        $this->trackSeventhVisit_withGoalConversion($t, $dateTime);
        $this->trackEigthVisit_withEcommerceAbandonedCart($t, $dateTime);
        $this->trackNinthVisit_withEcommerceOrder($t, $dateTime);
        $this->trackTenthVisit_withCampaignInformationInSecondAction($t, $dateTime);
    }

    public function tearDown(): void
    {
        // empty
    }

    /**
     * @param $name
     * @param $keyword
     * @param $source
     * @param $medium
     * @param $content
     * @param $campaignId
     * @param $campaignGroup
     * @param $campaignPlacement
     * @return string
     */
    protected function getLandingUrlWithCampaignParams($name, $keyword, $source, $medium, $content, $campaignId, $campaignGroup, $campaignPlacement)
    {
        return sprintf('http://example.com/?utm_campaign=%s&utm_term=%s&utm_source=%s&utm_medium=%s&utm_content=%s&utm_id=%s&mtm_group=%s&mtm_placement=%s',
            $name, $keyword, $source, $medium, $content, $campaignId, $campaignGroup, $campaignPlacement);
    }

    private function setUpWebsite()
    {
        $idSite = self::createWebsite($this->dateTime, $ecommerce = 1, Fixture::DEFAULT_SITE_NAME, 'http://example.com/');
        $this->assertTrue($idSite === $this->idSite);

        $this->idGoal1 = \Piwik\Plugins\Goals\API::getInstance()->addGoal(
            $this->idSite, 'title match', 'title', self::THIS_PAGE_VIEW_IS_GOAL_CONVERSION, 'contains',
            $caseSensitive = false, $revenue = 10, $allowMultipleConversions = true
        );

        $this->idGoal2 = \Piwik\Plugins\Goals\API::getInstance()->addGoal(
            $this->idSite, 'title match', 'manually', '', 'contains'
        );
    }

    /**
     * @param \MatomoTracker $t
     * @param                $dateTime
     * @throws \Exception
     */
    protected function trackFirstVisit_withGoogleAnalyticsParameters($t, $dateTime)
    {
        $this->moveTimeForward($t, 0.1, $dateTime);
        $t->setUrl('http://example.com/?utm_campaign=November_Offer&utm_term=Mot_clé_PÉPÈRE&utm_source=newsletter_7&utm_content=contains personalized campaigns for client&utm_medium=email&utm_id=CAMPAIGN_ID_KABOOM&mtm_group=Audience%20Group%201&mtm_placement=Google%20Search');
        self::checkResponse($t->doTrackPageView('Viewing homepage, will be recorded as a visit from Campaign'));

        // Same visit, check campaign is not overwritten and new visit created (only if plugin is activated, as param does not work with core)
        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl('http://example.com/sub/page?my_campaign=SHOULD_BE_NEW_VISIT');
        self::checkResponse($t->doTrackPageView('Second page view, should not overwrite existing campaign'));
    }

    /**
     * @param \MatomoTracker $t
     * @param                $dateTime
     * @throws \Exception
     */
    protected function trackSecondVisit_withPiwikCampaignParameters($t, $dateTime)
    {
        $this->moveTimeForward($t, 2, $dateTime);
        $url = $this->getLandingUrlWithCampaignParams(
            $name = 'October_Offer',
            $keyword = 'Mot_clé_PÉPÈRE',
            $source = 'newsletter_6',
            $medium = 'email',
            $content = 'none',
            $campaignId = 'CAMPAIGN_ID_KABOOM',
            $campaignGroup = 'Group 1',
            $campaignPlacement = 'Google Ads'
        );
        $t->setUrl($url);
        self::checkResponse($t->doTrackPageView('Coming back with another campaign'));
    }

    /**
     * @param \MatomoTracker $t
     * @param                $dateTime
     * @throws \Exception
     */
    protected function trackThirdVisit_withStandardCampaignOnly($t, $dateTime)
    {
        $this->moveTimeForward($t, 4, $dateTime);
        $t->setUrl('http://example.com/?matomo_campaign=Default_Offer&matomo_kwd=Not_An_Advanced_Campaign_At_first');
        self::checkResponse($t->doTrackPageView('Coming back with a basic non advanced campaign which will be counted as advanced anyway. Kaboom.'));
    }

    /**
     * @param \MatomoTracker $t
     * @param                $dateTime
     * @throws \Exception
     */
    protected function trackFourthVisit_withDimensionsInUrlHash($t, $dateTime)
    {
        // using matomo_campaign and matomo_keyword will not be detected as advanced campaign
        // this will help us verify that when a "basic campaign" is detected, it is copied over the advanced campaign
        $this->moveTimeForward($t, 5, $dateTime);
        $baseUrl = 'http://example.com/homepage?utm_content=THIS_CAMPAIGN_CONTENT_SHOULD_NOT_BE_TRACKED';
        $urlHash = '#mtm_campaign=Campaign_Hashed&mtm_keyword=' . urlencode('Keyword from #hash tag parameter');
        $t->setUrl($baseUrl . $urlHash);
        self::checkResponse($t->doTrackPageView('Campaign dimensions are found in the landing page #hash tag'));
    }

    /**
     * @param \MatomoTracker $t
     * @param                $dateTime
     * @throws \Exception
     */
    protected function trackFifthVisit_withCampaignNameOnly($t, $dateTime)
    {
        $this->moveTimeForward($t, 6, $dateTime);
        $t->setUrl('http://example.com/homepage?mtm_campaign=CampaignNameDimension - No Other Dimension for this visit');
        self::checkResponse($t->doTrackPageView('Campaign dimensions are found in the landing page #hash tag'));
    }

    /**
     * @param \MatomoTracker $t
     * @param                $dateTime
     * @throws \Exception
     */
    protected function trackSixthVisit_withSuperLongLabels($t, $dateTime)
    {
        $this->moveTimeForward($t, 7, $dateTime);

        $multiplier = 20;
        $name       = urlencode(str_repeat('Lenghty "NAME"...', $multiplier));
        $keyword    = urlencode(str_repeat('Lenghty "KEYWORD"...', $multiplier));
        $source     = urlencode(str_repeat('Lenghty "SOURCE"...', $multiplier));
        $medium     = urlencode(str_repeat('Lenghty "MEDIUM"...', $multiplier));
        $content    = urlencode(str_repeat('Lenghty "CONTENT"...', $multiplier));
        $campaignId = urlencode(str_repeat('Lenghty "CAMPAIGN_ID"...', $multiplier));
        $campaignGroup = urlencode(str_repeat('Lenghty "CAMPAIGN_GROUP"...', $multiplier));
        $campaignPlacement = urlencode(str_repeat('Lenghty "CAMPAIGN_TARGET"...', $multiplier));
        $url        = $this->getLandingUrlWithCampaignParams($name, $keyword, $source, $medium, $content, $campaignId, $campaignGroup, $campaignPlacement);
        $t->setUrl($url);
        self::checkResponse($t->doTrackPageView('Verrrrry long Campaign Dimensions, check they are truncated'));
    }


    /**
     * @param \MatomoTracker $t
     * @param                $dateTime
     * @throws \Exception
     */
    protected function trackSeventhVisit_withGoalConversion($t, $dateTime)
    {
        $this->moveTimeForward($t, 8, $dateTime);
        $t->setUrl('http://example.com/homepage?mtm_campaign=Campaign_with_two_goals_conversions');
        self::checkResponse($t->doTrackPageView(self::THIS_PAGE_VIEW_IS_GOAL_CONVERSION . ' <-- goal conversion'));

        // This should be attributed to the same campaign  Campaign_with_two_goals_conversions
        $this->moveTimeForward($t, 8.1, $dateTime);
        $t->setUrl('http://example.com/anotherpage');
        self::checkResponse($t->doTrackGoal($this->idGoal1, 1101));

        // This should be attributed to the same campaign  Campaign_with_two_goals_conversions
        $this->moveTimeForward($t, 8.2, $dateTime);
        $t->setUrl('http://example.com/anotherpage');
        self::checkResponse($t->doTrackGoal($this->idGoal2, 3333));
    }

    /**
     * @param \MatomoTracker $t
     * @param                $dateTime
     * @throws \Exception
     */
    protected function trackEigthVisit_withEcommerceAbandonedCart($t, $dateTime)
    {
        $hourOffset = 9;
        $this->track_ecommerceCartUpdate($t, $hourOffset, $dateTime);
    }

    /**
     * @param \MatomoTracker $t
     * @param                $dateTime
     * @throws \Exception
     */
    protected function trackNinthVisit_withEcommerceOrder($t, $dateTime)
    {
        $hourOffset = 10;
        $this->track_ecommerceCartUpdate($t, $hourOffset, $dateTime);

        $this->moveTimeForward($t, $hourOffset + 0.3, $dateTime);
        $t->setUrl('http://example.com/cart');
        $t->addEcommerceItem('item SKU', 'item name', 'item category', $price = 111, $qty = 5);
        self::checkResponse($t->doTrackEcommerceOrder('Ecommerce_ORDER_ID_' . $this->orderIndex++, '555'));
    }

    private function trackTenthVisit_withCampaignInformationInSecondAction(\MatomoTracker $t, $dateTime)
    {
        $this->getTestEnvironment()->overrideConfig('Tracker', 'create_new_visit_when_campaign_changes', '0');
        $this->getTestEnvironment()->save();

        $this->moveTimeForward($t, 12.0, $dateTime);
        $t->setUrl('http://example.com/');
        self::checkResponse($t->doTrackPageView('Viewing homepage without campaign, recorded as a visit from Campaign, but...'));

        $this->moveTimeForward($t, 12.1, $dateTime);
        $t->setUrl('http://example.com/sub/page?mtm_campaign=SHOULD_BE_NEW_VISIT2');
        $t->setCustomTrackingParameter('abc', '1');
        self::checkResponse($t->doTrackPageView('Second page view, with campaign, should overwrite referrer information from previous visit'));

        $this->getTestEnvironment()->overrideConfig('Tracker', 'create_new_visit_when_campaign_changes', '1');
    }

    /**
     * @param \MatomoTracker $t
     * @param                $hourOffset
     * @param                $dateTime
     * @throws \Exception
     */
    protected function track_ecommerceCartUpdate($t, $hourOffset, $dateTime)
    {
        $this->moveTimeForward($t, $hourOffset, $dateTime);
        $url = $this->getLandingUrlWithCampaignParams(
            $name = 'Ecommerce_campaign',
            $keyword = 'Ecommerce_keyword',
            $source = 'Ecommerce_source',
            $medium = 'Ecommerce_medium',
            $content = 'Ecommerce_content',
            $campaignId = 'Ecommmerce_CampaignId',
            $campaignGroup = 'Ecommmerce_CampaignGroup',
            $campaignPlacement = 'Ecommmerce_CampaignPlacement'
        );
        $t->setUrl($url);
        self::checkResponse($t->doTrackPageView('Homepage'));

        $this->moveTimeForward($t, $hourOffset + 0.1, $dateTime);
        $t->setUrl('http://example.com/cart');
        $t->addEcommerceItem('item SKU', 'item name', 'item category', $price = 111, $qty = 5);
        self::checkResponse($t->doTrackEcommerceCartUpdate('555'));
    }

    /**
     * @param \MatomoTracker $t
     * @param                $hourForward
     * @param                $dateTime
     * @throws \Exception
     */
    protected function moveTimeForward($t, $hourForward, $dateTime)
    {
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour($hourForward)->getDatetime());
    }

    public function provideContainerConfig()
    {
        $testVars = new Piwik\Tests\Framework\TestingEnvironmentVariables();
        return array(

            'observers.global' => \DI\add(array(
                array(
                    'Environment.bootstrapped', \DI\value(function () use ($testVars) {
                    $plugins = Piwik\Config::getInstance()->Plugins['Plugins'];
                    $index   = array_search('MarketingCampaignsReporting', $plugins);

                    if ($testVars->_disableMarketingCampaignsReporting) {
                        if ($index !== false) {
                            unset($plugins[$index]);
                        }
                    } else {
                        if ($index === false) {
                            $plugins[] = 'MarketingCampaignsReporting';
                        }
                    }

                    Piwik\Config::getInstance()->Plugins['Plugins'] = $plugins;
                }
                )),
            )),

            'advanced_campaign_reporting.uri_parameters.campaign_name' => \DI\value([(new Piwik\Plugins\MarketingCampaignsReporting\Columns\CampaignName())->getColumnName() => ['mtm_campaign', 'matomo_campaign', 'mtm_cpn', 'pk_campaign', 'piwik_campaign', 'pk_cpn', 'utm_campaign', 'my_campaign']])
        );
    }
}

