tarteaucitron.services.klaviyo = {
    "key": "klaviyo",
    "type": "ads",
    "name": "Klaviyo",
    "uri": "https://help.klaviyo.com/hc/en-us/articles/360034666712-About-Cookies-in-Klaviyo",
    "needConsent": true,
    "cookies": ['__kla_id'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.klaviyoCompanyId === undefined) {
            return;
        }
        tarteaucitron.addScript('//static.klaviyo.com/onsite/js/klaviyo.js?company_id=' + tarteaucitron.user.klaviyoCompanyId);
    }
};
