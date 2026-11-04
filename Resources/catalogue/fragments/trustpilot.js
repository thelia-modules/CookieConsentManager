tarteaucitron.services.trustpilot = {
    "key": "trustpilot",
    "type": "other",
    "name": "Trustpilot",
    "uri": "https://fr.legal.trustpilot.com/for-reviewers/end-user-privacy-terms",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['trustpilot-widget'], '');
        tarteaucitron.addScript('https://widget.trustpilot.com/bootstrap/v5/tp.widget.sync.bootstrap.min.js');
    },
    "fallback": function () {
        "use strict";
        var id = 'trustpilot';
        tarteaucitron.fallback(['trustpilot-widget'], function (elem) {
            elem.style.width = tarteaucitron.getStyleSize(tarteaucitron.getElemAttr(elem, 'data-style-width'));
            elem.style.height = tarteaucitron.getStyleSize(tarteaucitron.getElemAttr(elem, 'data-style-height'));
            return tarteaucitron.engage(id);
        });
    }
};
