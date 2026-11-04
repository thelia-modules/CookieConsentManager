tarteaucitron.services.actistat = {
    "key": "actistat",
    "type": "analytic",
    "name": "ActiSTAT",
    "uri": "https://actigraph.com/actistat",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.actistatId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://actistat.fr/umami.js', '', '', '', 'data-website-id', tarteaucitron.user.actistatId);
    }
};
