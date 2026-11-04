tarteaucitron.services.usercom = {
    "key": "usercom",
    "type": "analytic",
    "name": "User.com",
    "uri": "https://user.com/security/privacy-policy",
    "needConsent": true,
    "cookies": ['_ca_chat'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.userId === undefined || tarteaucitron.user.userApiKey === undefined) {
            return;
        }

        window.civchat = {
            apiKey: tarteaucitron.user.userApiKey,
        };

        tarteaucitron.addScript('https://' + tarteaucitron.user.userId + '.user.com/widget.js');
    }
};
