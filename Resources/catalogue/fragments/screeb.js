tarteaucitron.services.screeb = {
    "key": "screeb",
    "type": "support",
    "name": "Screeb",
    "uri": "https://screeb.app/gdpr-privacy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.screebId === undefined) {
            return;
        }

        window['ScreebObject'] = '$screeb';
        window['$screeb'] = window['$screeb'] || function() {
            var d = arguments;
            return new Promise(function(a, b) {
                (window['$screeb'].q = window['$screeb'].q || []).push({
                    v: 1,
                    args: d,
                    ok: a,
                    ko: b
                })
            })
        };

        tarteaucitron.addScript('https://t.screeb.app/tag.js', '$screeb');

        if (tarteaucitron.user.screebDontInit !== true) {
            window.$screeb('init', tarteaucitron.user.screebId);
        }
    }
};
