tarteaucitron.services.stonly = {
    "key": "stonly",
    "type": "api",
    "name": "Stonly (privacy by design)",
    "uri": "https://trust.stonly.com/",
    "needConsent": false,
    "cookies": [],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.stonlyId === undefined) {
            return;
        }

        window.STONLY_WID = tarteaucitron.user.stonlyId;
        window.StonlyWidget || ((window.w = window.StonlyWidget = function () {
            window.w._api ? window.w._api.apply(window.w, arguments) : window.w.queue.push(arguments)
        }).queue = []);

        tarteaucitron.addScript('https://stonly.com/js/widget/v2/stonly-widget.js?v=' + Date.now());
    }
};
