tarteaucitron.services.plausible = {
    "key": "plausible",
    "type": "analytic",
    "name": "Plausible",
    "uri": "https://plausible.io/privacy",
    "needConsent": false,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.plausibleDomain === undefined) {
            return;
        }

        if (tarteaucitron.user.plausibleEndpoint === undefined) {
            tarteaucitron.user.plausibleEndpoint = 'plausible.io';
        }

        tarteaucitron.addScript('https://' + tarteaucitron.user.plausibleEndpoint + '/js/script.js', '', '', '', 'data-domain', tarteaucitron.user.plausibleDomain);
    }
};
