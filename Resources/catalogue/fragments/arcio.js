tarteaucitron.services.arcio = {
    "key": "arcio",
    "type": "api",
    "name": "Arc.io",
    "uri": "https://arc.io/about",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.arcId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://arc.io/widget.min.js#'+tarteaucitron.user.arcId);
    }
};
