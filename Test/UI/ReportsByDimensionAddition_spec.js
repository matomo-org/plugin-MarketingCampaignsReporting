/*!
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
describe("AdvancedCampaignReporting_ReportsByDimensionAddition", function () {
    this.timeout(0);

    this.fixture = 'Piwik\\Plugins\\AdvancedCampaignReporting\\tests\\fixtures\\TrackAdvancedCampaigns';

    var withVisitsParams = "&idSite=1&date=2013-01-23&period=week",
        withoutVisitsParams = "&idSite=1&date=2013-02-02&period=day",
        urlPrefix = "?module=CoreHome&action=index&",
        goalsUrl = urlPrefix + withVisitsParams + "#/module=Goals&action=index",
        goalsNoConversionsUrl = urlPrefix + withoutVisitsParams + "#/module=Goals&action=index",
        ecommerceUrl = urlPrefix + withVisitsParams + "#/module=Ecommerce&action=sales&idGoal=ecommerceOrder",
        ecommerceNoConversionsUrl = urlPrefix + withVisitsParams + "#/module=Ecommerce&action=sales&idGoal=ecommerceOrder";

    it("should load correctly within the Goals page", function (done) {
        expect.screenshot("loaded_goals").to.be.capture(function (page) {
            page.load(goalsUrl);
            page.evaluate(function () {
                $('.dimensionCategory:contains(by Campaign) .reportDimension:eq(0)').click();

                // capture selector doesn't work for some reason, so instead hide all other elements
                $('#content>*:not(.reportsByDimensionView)').hide();
                $('#root>*:not(.pageWrap)').hide();
                $('.pageWrap>*:not(#content)').hide();
            });
        }, done);
    });

    it("should not display in the Goals page when there are no conversions", function (done) {
        expect.page(goalsNoConversionsUrl).to.not.contains('.dimensionCategory:contains("View goals by Campaigns")', function (page) {
            // empty
        }, done);
    });

    it("should load correctly within the Ecommerce page", function (done) {
        expect.screenshot("loaded_ecommerce").to.be.capture(function (page) {
            page.load(ecommerceUrl);
            page.evaluate(function () {
                $('.dimensionCategory:contains(by Campaign) .reportDimension:eq(0)').click();

                // capture selector doesn't work for some reason, so instead hide all other elements
                $('#content>*:not(.reportsByDimensionView)').hide();
                $('#root>*:not(.pageWrap)').hide();
                $('.pageWrap>*:not(#content)').hide();
            });
        }, done);
    });

    it("should not display in the Ecommerce page when there are no conversions", function (done) {
        expect.page(ecommerceNoConversionsUrl).to.not.contains('.dimensionCategory:contains("View goals by Campaigns")', function (page) {
            // empty
        }, done);
    });
});