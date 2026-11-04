tarteaucitron.services.thetradedesk = {
    "key": "thetradedesk",
    "type": "ads",
    "name": "TheTradeDesk",
    "uri": "https://www.thetradedesk.com/fr/privacy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.thetradedeskAdvertiserId === undefined || tarteaucitron.user.thetradedeskUpixelId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://js.adsrvr.org/up_loader.1.1.0.js', '', function() {
            ttd_dom_ready( function() {
                if (typeof TTDUniversalPixelApi === 'function') {
                    var universalPixelApi = new TTDUniversalPixelApi();
                    universalPixelApi.init(tarteaucitron.user.thetradedeskAdvertiserId, [tarteaucitron.user.thetradedeskUpixelId], "https://insight.adsrvr.org/track/up");
                }
            });
        });
    }
};
