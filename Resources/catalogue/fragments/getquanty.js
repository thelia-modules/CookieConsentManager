tarteaucitron.services.getquanty = {
    "key": "getquanty",
    "type": "analytic",
    "name": "GetQuanty",
    "uri": "https://www.getquanty.com/mentions-legales/",
    "needConsent": true,
    "cookies": ['_first_pageview', 'eqy_sessionid', 'eqy_siteid', 'cluid', 'eqy_company', 'cluid', 'gq_utm', '_jsuid'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.getguanty === undefined) {
            return;
        }

        if (tarteaucitron.user.getquantyAlreadyLoaded !== undefined) {
            return;
        }

        tarteaucitron.addScript('https://get.smart-data-systems.com/gq?site_id=' + tarteaucitron.user.getguanty + '&consent=1');
    },
    "fallback": function () {
        "use strict";
        if (tarteaucitron.user.getguanty === undefined) {
            return;
        }

        tarteaucitron.user.getquantyAlreadyLoaded = true;

        tarteaucitron.addScript('https://get.smart-data-systems.com/gq?site_id=' + tarteaucitron.user.getguanty + '&notrack=1');
    }
};
