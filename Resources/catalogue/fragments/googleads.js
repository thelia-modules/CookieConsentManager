tarteaucitron.services.googleads = {
    "key": "googleads",
    "type": "ads",
    "name": "Google Ads",
    "uri": "https://policies.google.com/privacy",
    "needConsent": true,
    "cookies": (function () {
        var googleIdentifier = tarteaucitron.user.googleadsId,
            tagUaCookie = '_gat_gtag_' + googleIdentifier,
            tagGCookie = '_ga_' + googleIdentifier;

        tagUaCookie = tagUaCookie.replace(/-/g, '_');
        tagGCookie = tagGCookie.replace(/G-/g, '');

        return ['_ga', '_gat', '_gid', '__utma', '__utmb', '__utmc', '__utmt', '__utmz', tagUaCookie, tagGCookie, '_gcl_au'];
    })(),
    "js": function () {
        "use strict";

        if (tarteaucitron.user.googleadsId === undefined) {
            return;
        }

        var customDomain = "www.googletagmanager.com";
        if (tarteaucitron.user.googleadsCustomDomain !== undefined && tarteaucitron.user.googleadsCustomDomain !== "") {
            customDomain = tarteaucitron.user.googleadsCustomDomain;
        }

        window.dataLayer = window.dataLayer || [];
        tarteaucitron.addScript('https://' + customDomain + '/gtag/js?id=' + tarteaucitron.user.googleadsId, '', function () {
            window.gtag = function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            var additional_config_info = (timeExpire !== undefined) ? {'anonymize_ip': true, 'cookie_expires': timeExpire / 1000} : {'anonymize_ip': true};

            gtag('config', tarteaucitron.user.googleadsId, additional_config_info);

            if (typeof tarteaucitron.user.googleadsMore === 'function') {
                tarteaucitron.user.googleadsMore();
            }
        });
    },
    "fallback": function () {
        if (tarteaucitron.parameters.googleConsentMode === true) {
            if (tarteaucitron.parameters.softConsentMode === false) {
                this.js();
            }
        }
    }
};
