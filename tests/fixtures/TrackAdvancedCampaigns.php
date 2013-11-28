<?php
/**
 * Piwik - Open source web analytics
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\AdvancedCampaignReporting\tests\fixtures;

use Piwik\Date;
use Piwik;

class TrackAdvancedCampaigns extends \Test_Piwik_BaseFixture
{
    public $dateTime = '2013-01-23 01:23:45';
    public $idSite = 1;

    const THIS_PAGE_VIEW_IS_GOAL_CONVERSION = 'this is a goal conversion';

    public function setUp()
    {
        $this->setUpWebsite();

        // Track one visitor, with returning visit with advanced campaign use cases
        $t = self::getTracker($this->idSite, $this->dateTime, $defaultInit = true, $useLocal = false);

        $this->trackFirstVisit_withGoogleAnalyticsParameters($t);
        $this->trackSecondVisit_withPiwikCampaignParameters($t);
        $this->trackThirdVisit_withStandardCampaignOnly($t);
        $this->trackFourthVisit_withDimensionsInUrlHash($t);
        $this->trackFifthVisit_withCampaignNameOnly($t);
        $this->trackSixthVisit_withSuperLongLabels($t);
        $this->trackSeventhVisit_withGoalConversion($t);
        $this->trackEigthVisit_withEcommerceAbandonedCart($t);
        $this->trackNinthVisit_withEcommerceOrder($t);

//        exit;
    }

    public function tearDown()
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
     * @return string
     */
    protected function getLandingUrlWithCampaignParams($name, $keyword, $source, $medium, $content, $campaignId)
    {
        return sprintf('http://example.com/?utm_campaign=%s&utm_term=%s&utm_source=%s&utm_medium=%s&utm_content=%s&utm_id=%s',
            $name, $keyword, $source, $medium, $content, $campaignId);
    }

    private function setUpWebsite()
    {
        $idSite = self::createWebsite($this->dateTime, $ecommerce = 1);
        $this->assertTrue($idSite === $this->idSite);

        \Piwik\Plugins\Goals\API::getInstance()->addGoal(
            $this->idSite, 'title match', 'title', self::THIS_PAGE_VIEW_IS_GOAL_CONVERSION, 'contains',
            $caseSensitive = false, $revenue = 10, $allowMultipleConversions = true
        );

    }

    protected function trackFirstVisit_withGoogleAnalyticsParameters(\PiwikTracker $t)
    {
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.1)->getDatetime());
        $t->setUrl('http://example.com/?utm_campaign=November_Offer&utm_term=Mot_clé_PÉPÈRE&utm_source=newsletter_7&utm_content=contains personalized campaigns for client&utm_medium=email&utm_id=CAMPAIGN_ID_KABOOM');
        self::checkResponse($t->doTrackPageView('Viewing homepage, will be recorded as a visit from Campaign'));

        // Same visit, check campaign is not overwritten
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(0.3)->getDatetime());
        $t->setUrl('http://example.com/sub/page?utm_campaign=SHOULD_NOT_BE_TRACKED');
        self::checkResponse($t->doTrackPageView('Second page view, should not overwrite existing campaign'));
    }

    protected function trackSecondVisit_withPiwikCampaignParameters(\PiwikTracker $t)
    {
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(2)->getDatetime());
        $url = $this->getLandingUrlWithCampaignParams(
            $name = 'October_Offer',
            $keyword = 'Mot_clé_PÉPÈRE',
            $source = 'newsletter_6',
            $medium = 'email',
            $content = 'none',
            $campaignId = 'CAMPAIGN_ID_KABOOM'
        );
        $t->setUrl($url);
        self::checkResponse($t->doTrackPageView('Coming back with another campaign'));
    }

    protected function trackThirdVisit_withStandardCampaignOnly(\PiwikTracker $t)
    {
        // using piwik_campaign and piwik_keyword will not be detected as advanced campaign
        // this will help us verify that when a "basic campaign" is detected, it is copied over the advanced campaign
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(4)->getDatetime());
        $t->setUrl('http://example.com/?piwik_campaign=Default_Offer&piwik_kwd=Not_An_Advanced_Campaign_At_first');
        self::checkResponse($t->doTrackPageView('Coming back with a basic non advanced campaign which will be counted as advanced anyway. Kaboom.'));
    }

    protected function trackFourthVisit_withDimensionsInUrlHash(\PiwikTracker $t)
    {
        // using piwik_campaign and piwik_keyword will not be detected as advanced campaign
        // this will help us verify that when a "basic campaign" is detected, it is copied over the advanced campaign
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(5)->getDatetime());
        $baseUrl = 'http://example.com/homepage?utm_content=THIS_CAMPAIGN_CONTENT_SHOULD_NOT_BE_TRACKED';
        $urlHash = '#pk_campaign=Campaign_Hashed&pk_keyword=' . urlencode('Keyword from #hash tag parameter');
        $t->setUrl($baseUrl .$urlHash);
        self::checkResponse($t->doTrackPageView('Campaign dimensions are found in the landing page #hash tag'));
    }

    protected function trackFifthVisit_withCampaignNameOnly(\PiwikTracker $t)
    {
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(6)->getDatetime());
        $t->setUrl('http://example.com/homepage?pk_campaign=CampaignNameDimension - No Other Dimension for this visit' );
        self::checkResponse($t->doTrackPageView('Campaign dimensions are found in the landing page #hash tag'));
    }

    protected function trackSixthVisit_withSuperLongLabels(\PiwikTracker $t)
    {
        $multiplier = 20;
        $name = urlencode(str_repeat('Lenghty "NAME"...', $multiplier));
        $keyword = urlencode(str_repeat('Lenghty "KEYWORD"...', $multiplier));
        $source = urlencode(str_repeat('Lenghty "SOURCE"...', $multiplier));
        $medium = urlencode(str_repeat('Lenghty "MEDIUM"...', $multiplier));
        $content = urlencode(str_repeat('Lenghty "CONTENT"...', $multiplier));
        $campaignId = urlencode(str_repeat('Lenghty "CAMPAIGN_ID"...', $multiplier));
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(7)->getDatetime());
        $url = $this->getLandingUrlWithCampaignParams($name, $keyword, $source, $medium, $content, $campaignId);
        $t->setUrl($url);
        self::checkResponse($t->doTrackPageView('Verrrrry long Campaign Dimensions, check they are truncated'));
    }


    protected function trackSeventhVisit_withGoalConversion(\PiwikTracker $t)
    {
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(7)->getDatetime());
        $t->setUrl('http://example.com/homepage?pk_campaign=Campaign_with_two_goals_conversions' );
        self::checkResponse($t->doTrackPageView(self::THIS_PAGE_VIEW_IS_GOAL_CONVERSION . ' <-- goal conversion'));

        // This should be attributed to the same campaign  Campaign_with_two_goals_conversions
        $t->setForceVisitDateTime(Date::factory($this->dateTime)->addHour(7.2)->getDatetime());
        $t->setUrl('http://example.com/anotherpage' );
        self::checkResponse($t->doTrackGoal(1, 3333));

    }

    protected function trackEigthVisit_withEcommerceAbandonedCart(\PiwikTracker $t)
    {
    }

    protected function trackNinthVisit_withEcommerceOrder(\PiwikTracker $t)
    {
    }
}

