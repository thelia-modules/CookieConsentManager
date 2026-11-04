tarteaucitron.services.weborama = {
    "key": "weborama",
    "type": "analytic",
    "name": "Weborama",
    "uri": "https://weborama.com/faq-cnil-avril-2021/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.addScript('https://cstatic.weborama.fr/js/advertiserv2/adperf_conversion.js');
    }
};
