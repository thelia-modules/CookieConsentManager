tarteaucitron.services.sharpspring = {
    "key": "sharpspring",
    "type": "analytic",
    "name": "SharpSpring",
    "uri": "https://sharpspring.com/legal/sharpspring-cookie-policy/",
    "needConsent": true,
    "cookies": ['koitk', '__ss', '__ss_tk', '__ss_referrer'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.ssId === undefined || tarteaucitron.user.ssAccount === undefined) {
            return;
        }

        window._ss = window._ss || [];
        window._ss.push(['_setDomain', 'https://' + tarteaucitron.user.ssId + '.marketingautomation.services/net']);
        window._ss.push(['_setAccount', tarteaucitron.user.ssAccount]);
        window._ss.push(['_trackPageView']);

        window._pa = window._pa || {};

        tarteaucitron.addScript('https://' + tarteaucitron.user.ssId + '.marketingautomation.services/client/ss.js');
    }
};
