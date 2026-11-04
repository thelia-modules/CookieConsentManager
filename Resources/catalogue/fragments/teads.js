tarteaucitron.services.teads = {
    "key": "teads",
    "type": "ads",
    "name": "Teads",
    "uri": "https://privacy-policy.teads.com",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.teadsBuyerPixelId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://p.teads.tv/teads-fellow.js');

        window.teads_e = window.teads_e || [];
        window.teads_buyer_pixel_id = tarteaucitron.user.teadsBuyerPixelId;
    }
};
