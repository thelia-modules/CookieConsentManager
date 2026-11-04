tarteaucitron.services.tidycal = {
    "key": "tidycal",
    "type": "support",
    "name": "TidyCal",
    "uri": "https://tidycal.com/privacy-policy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        tarteaucitron.addScript('https://asset-tidycal.b-cdn.net/js/embed.js');
    },
    "fallback": function () {
        "use strict";
        var id = 'tidycal';
        tarteaucitron.fallback(['tidycal-embed'], function (elem) {
            return tarteaucitron.engage(id);
        });
    }
};
