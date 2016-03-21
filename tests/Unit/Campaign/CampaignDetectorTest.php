<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\AdvancedCampaignReporting\tests\Unit\Campaign;

use Piwik\Plugins\AdvancedCampaignReporting\Campaign\CampaignDetector;
use Piwik\Plugins\AdvancedCampaignReporting\Tracker;
use Piwik\Tracker\Request;

/**
 * @group AdvancedCampaignReporting
 * @group AdvancedCampaignReporting_Unit
 * @group Plugins
 */
class CampaignDetectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideRequestData
     * @param Request $request
     * @param array $campaignParams
     * @param mixed $expectedOutput
     */
    public function testDetectCampaignFromRequest(Request $request, array $campaignParams, $expectedOutput)
    {
        $detector = new CampaignDetector();
        $dimensions = $detector->detectCampaignFromRequest($request, $campaignParams);

        $this->assertEquals($expectedOutput, $dimensions);
    }

    /**
     * @dataProvider provideVisitData
     * @param array $visitorInfo
     * @param array $campaignParams
     * @param $expectedOutput
     */
    public function testDetectCampaignFromVisit(array $visitorInfo, array $campaignParams, $expectedOutput)
    {
        $detector = new CampaignDetector();
        $dimensions = $detector->detectCampaignFromVisit($visitorInfo, $campaignParams);

        $this->assertEquals($expectedOutput, $dimensions);
    }

    public function provideRequestData()
    {
        return [
            'normal query string' => [
                'request' => $this->createRequestMock(
                    'http://example.com/?pk_campaign=campName&pk_kwd=sdf1'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name' => 'campname',
                    'campaign_keyword' => 'sdf1'
                ]
            ],
            'query string behind hash' => [
                'request' => $this->createRequestMock(
                    'https://whatever.com/#/category/sub/1?pk_campaign=campName2&pk_kwd=sdf2'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name' => 'campname2',
                    'campaign_keyword' => 'sdf2'
                ]
            ],
            'normal query string with google parameters' => [
                'request' => $this->createRequestMock(
                    'http://example.com/?utm_campaign=campName&utm_term=sdf1'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name' => 'campname',
                    'campaign_keyword' => 'sdf1'
                ]
            ],
            'query string behind hash with google params' => [
                'request' => $this->createRequestMock(
                    'https://whatever.com/#/category/sub/1?utm_campaign=campName2&utm_term=sdf2'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name' => 'campname2',
                    'campaign_keyword' => 'sdf2'
                ]
            ],
            'no query string' => [
                'request' => $this->createRequestMock(
                    'https://whatever.com/cat1/cat2'
                ),
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => false
            ]
        ];
    }

    public function provideVisitData()
    {
        return [
            [
                'visitorInfo' => [
                    'campaign_name' => 'camapaign1',
                    'campaign_keyword' => 'kwd1',
                ],
                'campaignParams' => $this->getCampaignParameters(),
                'expectedOutput' => [
                    'campaign_name' => 'camapaign1',
                    'campaign_keyword' => 'kwd1'
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function getCampaignParameters()
    {
        return [
            Tracker::CAMPAIGN_NAME_FIELD    => ['pk_campaign', 'piwik_campaign', 'pk_cpn', 'utm_campaign'],
            Tracker::CAMPAIGN_KEYWORD_FIELD => ['pk_keyword', 'piwik_kwd', 'pk_kwd', 'utm_term'],
            Tracker::CAMPAIGN_SOURCE_FIELD  => ['pk_source', 'utm_source'],
            Tracker::CAMPAIGN_MEDIUM_FIELD  => ['pk_medium', 'utm_medium'],
            Tracker::CAMPAIGN_CONTENT_FIELD => ['pk_content', 'utm_content'],
            Tracker::CAMPAIGN_ID_FIELD      => ['pk_cid', 'utm_id'],
        ];
    }

    private function createRequestMock($returnedUrl)
    {
        $mock = $this->getMockBuilder('\Piwik\Tracker\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $mock
            ->expects($this->exactly(1))
            ->method('getParam')
            ->will($this->returnValue($returnedUrl));

        return $mock;
    }

}
