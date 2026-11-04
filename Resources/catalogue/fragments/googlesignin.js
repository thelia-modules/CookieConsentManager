tarteaucitron.services.googlesignin = {
    "key": "googlesignin",
    "type": "other",
    "name": "Google Signin",
    "uri": "https://policies.google.com/technologies/cookies#types-of-cookies",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        tarteaucitron.addScript('https://accounts.google.com/gsi/client');
    }
};
