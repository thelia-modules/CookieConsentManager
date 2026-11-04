tarteaucitron.services.googletagmanager = {
    "key": "googletagmanager",
    "type": "api",
    "name": "Google Tag Manager",
    "uri": "https://policies.google.com/privacy",
    "needConsent": true,
    "cookies": ['_ga', '_gat', '__utma', '__utmb', '__utmc', '__utmt', '__utmz', '__gads', '_drt_', 'FLC', 'exchange_uid', 'id', 'fc', 'rrs', 'rds', 'rv', 'uid', 'UIDR', 'UID', 'clid', 'ipinfo', 'acs'],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.googletagmanagerId === undefined) {
            return;
        }
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'gtm.start': new Date().getTime(),
            event: 'gtm.js'
        });

        var customDomain = "www.googletagmanager.com";
        if (tarteaucitron.user.googletagmanagerCustomDomain !== undefined && tarteaucitron.user.googletagmanagerCustomDomain !== "") {
            customDomain = tarteaucitron.user.googletagmanagerCustomDomain;
        }

        tarteaucitron.addScript('https://' + customDomain + '/gtm.js?id=' + tarteaucitron.user.googletagmanagerId);
    },
    "fallback": function () {
        if (tarteaucitron.parameters.googleConsentMode === true) {
            if (tarteaucitron.parameters.softConsentMode === false) {
                this.js();
            }
        }
    }
};
