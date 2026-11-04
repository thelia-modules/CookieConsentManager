tarteaucitron.services.jsapi = {
    "key": "jsapi",
    "type": "api",
    "name": "Google jsapi",
    "uri": "https://policies.google.com/privacy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.addScript('//www.google.com/jsapi');
    }
};
