/*!
 * Piwik PRO - cloud hosting and enterprise analytics consultancy
 * from the creators of Piwik.org
 *
 * @link http://piwik.pro
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
describe("AdvancedCampaignReporting_Reports", function () {
    this.timeout(0);

    this.fixture = 'Piwik\\Plugins\\AdvancedCampaignReporting\\tests\\Fixtures\\TrackAdvancedCampaigns';

    var url = "?module=CoreHome&action=index&idSite=1&date=2013-01-23&period=month#/module=Referrers&action=menuGetCampaigns";

    it("should load correctly when user goes to normal campaigns page", function (done) {
        expect.screenshot("loaded").to.be.captureSelector('.pageWrap,.expandDataTableFooterDrawer', function (page) {
            page.load(url);

            // phantomjs screws up w/ % widths in CSS if a child element has a super long width. since one of the entries
            // in the row will be a very long campaign name, a datatable row will have a much longer length, messing up
            // the screenshot.
            page.evaluate(function () {
                $('#leftcolumn').each(function () {
                    if ($(this).width() > 650) {
                        $(this).width(400);
                        return false;
                    }
                });

                $('body').hide().show(0); // force redraw (the 0 is important, won't work otherwise)
            });
        }, done);
    });
});