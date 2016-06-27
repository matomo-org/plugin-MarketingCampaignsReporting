<?php
/**
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\AdvancedCampaignReporting\tests\Integration;

use Piwik\Common;
use Piwik\Plugins\AdvancedCampaignReporting\Tracker;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group AdvancedCampaignReporting
 * @group AdvancedCampaignReporting_Unit
 * @group Plugins
 */
class TrackerTest extends IntegrationTestCase
{

    /**
     * @dataProvider getConversionData
     * @param array $goal
     * @param array $visitorInfo
     * @param array $expected
     */
    public function testUpdateNewConversionWithCampaign(array $goal, array $visitorInfo, array $expected)
    {
        $tracker = new Tracker($this->createRequestMock());
        $tracker->updateNewConversionWithCampaign($goal, $visitorInfo);

        $this->assertEquals($expected, $goal);
    }

    /**
     * @dataProvider getVisitorData
     * @param array $visitorInfo
     * @param string $requestUrl
     * @param array $expected
     */
    public function testUpdateNewVisitWithCampaign(array $visitorInfo, $requestUrl, array $expected)
    {
        $tracker = new Tracker($this->createRequestMock($requestUrl));
        $tracker->updateNewVisitWithCampaign($visitorInfo);

        $this->assertEquals($expected, $visitorInfo);
    }

    public function getConversionData()
    {
        return [
            'normal case' => [
                'goal'      => [],
                'visitorInfo' => [
                    'campaign_name' => 'camapaign1',
                    'campaign_keyword' => 'kwd1',
                ],
                'expected' => [
                    'campaign_name'     => 'camapaign1',
                    'campaign_keyword'  => 'kwd1',
                    'referer_type'      => Common::REFERRER_TYPE_CAMPAIGN,
                    'referer_name'      => 'camapaign1',
                    'referer_keyword'   => 'kwd1'
                ],
            ],
            'parameters to truncate' => [
                'goal'      => [],
                'visitorInfo' => [
                    'campaign_name' => 'Campaign name with longer than 255 chars. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras ligula dolor, pulvinar in aliquet eget, accumsan suscipit mauris. Maecenas rhoncus rhoncus tortor in egestas. Praesent condimentum neque nec dapibus blandit. Etiam consequat metus velit, non viverra ante fringilla quis. Curabitur volutpat nec tortor vulputate egestas. Etiam fermentum dui sed eros fringilla, feugiat pharetra velit tincidunt. Donec eu tincidunt libero, a pretium erat. Praesent viverra ullamcorper scelerisque.',
                    'campaign_keyword' => 'Keyword with longer than 255 chars. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras ligula dolor, pulvinar in aliquet eget, accumsan suscipit mauris. Maecenas rhoncus rhoncus tortor in egestas. Praesent condimentum neque nec dapibus blandit. Etiam consequat metus velit, non viverra ante fringilla quis. Curabitur volutpat nec tortor vulputate egestas. Etiam fermentum dui sed eros fringilla, feugiat pharetra velit tincidunt. Donec eu tincidunt libero, a pretium erat. Praesent viverra ullamcorper scelerisque.',
                ],
                'expected' => [
                    'campaign_name'     => 'Campaign name with longer than 255 chars. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras ligula dolor, pulvinar in aliquet eget, accumsan suscipit mauris. Maecenas rhoncus rhoncus tortor in egestas. Praesent condimentum neque nec dapibus bl',
                    'campaign_keyword'  => 'Keyword with longer than 255 chars. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras ligula dolor, pulvinar in aliquet eget, accumsan suscipit mauris. Maecenas rhoncus rhoncus tortor in egestas. Praesent condimentum neque nec dapibus blandit.',
                    'referer_type'      => Common::REFERRER_TYPE_CAMPAIGN,
                    'referer_name'      => 'Campaign name with longer than 255 chars. Lorem ipsum dolor sit amet, ',
                    'referer_keyword'   => 'Keyword with longer than 255 chars. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras ligula dolor, pulvinar in aliquet eget, accumsan suscipit mauris. Maecenas rhoncus rhoncus tortor in egestas. Praesent condimentum neque nec dapibus blandit.'
                ],
            ]
        ];
    }

    public function getVisitorData()
    {
        return [
            'normal url' => [
                'visitorInfo' => [],
                'requestUrl' => 'http://example.com/?pk_campaign=camapaign1&pk_kwd=kwd1',
                'expected' => [
                    'campaign_name'     => 'camapaign1',
                    'campaign_keyword'  => 'kwd1',
                    'referer_type'      => Common::REFERRER_TYPE_CAMPAIGN,
                    'referer_name'      => 'camapaign1',
                    'referer_keyword'   => 'kwd1'
                ],
            ],
            'url with params behind hash' => [
                'visitorInfo' => [],
                'requestUrl' => 'https://whatever.com/#/category/sub/1?pk_campaign=campName2&pk_kwd=sdf2',
                'expected' => [
                    'campaign_name'     => 'campname2',
                    'campaign_keyword'  => 'sdf2',
                    'referer_type'      => Common::REFERRER_TYPE_CAMPAIGN,
                    'referer_name'      => 'campname2',
                    'referer_keyword'   => 'sdf2'
                ],
            ],
            'no campaign parameters in url' => [
                'visitorInfo' => [
                    'referer_type'      => Common::REFERRER_TYPE_CAMPAIGN,
                    'referer_name'      => 'campname2',
                    'referer_keyword'   => 'sdf2'
                ],
                'requestUrl' => 'https://whatever.com/category/sub/1',
                'expected' => [
                    'campaign_name'     => 'campname2',
                    'campaign_keyword'  => 'sdf2',
                    'referer_type'      => Common::REFERRER_TYPE_CAMPAIGN,
                    'referer_name'      => 'campname2',
                    'referer_keyword'   => 'sdf2'
                ],
            ],
            'campaign without parameters in url' => [
                'visitorInfo' => [
                    'referer_type'      => Common::REFERRER_TYPE_CAMPAIGN,
                    'referer_name'      => 'campname2',
                    'referer_keyword'   => 'sdf2'
                ],
                'requestUrl' => 'https://whatever.com/#/category/sub/1',
                'expected' => [
                    'campaign_name'     => 'campname2',
                    'campaign_keyword'  => 'sdf2',
                    'referer_type'      => Common::REFERRER_TYPE_CAMPAIGN,
                    'referer_name'      => 'campname2',
                    'referer_keyword'   => 'sdf2'
                ],
            ]
        ];
    }

    /**
     * @param string $requestUrl
     * @return \Piwik\Tracker\Request
     */
    private function createRequestMock($requestUrl = null)
    {
        $mock = $this->getMockBuilder('\Piwik\Tracker\Request')
            ->disableOriginalConstructor()
            ->getMock();

        if ($requestUrl) {
            $mock
                ->expects($this->exactly(1))
                ->method('getParam')
                ->will($this->returnValue($requestUrl));
        }

        return $mock;
    }
}
