tarteaucitron.services.calendly = {
    "key": "calendly",
    "type": "other",
    "name": "Calendly",
    "uri": "https://calendly.com/privacy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['calendly-inline-widget'], '');
        tarteaucitron.addScript('https://assets.calendly.com/assets/external/widget.js');
    },
    "fallback": function () {
        "use strict";
        var id = 'calendly';
        tarteaucitron.fallback(['calendly-inline-widget'], function (elem) {
            return tarteaucitron.engage(id);
        });
    }
};
