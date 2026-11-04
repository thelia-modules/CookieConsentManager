tarteaucitron.services.fillout = {
    "key": "fillout",
    "type": "other",
    "name": "Fillout",
    "uri": "https://www.fillout.com/privacy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['tac_fillout'], '');
        tarteaucitron.addScript('https://server.fillout.com/embed/v1/');
    },
    "fallback": function () {
        "use strict";
        var id = 'fillout';
        tarteaucitron.fallback(['tac_fillout'], function (elem) {
            return tarteaucitron.engage(id);
        });
    }
};
