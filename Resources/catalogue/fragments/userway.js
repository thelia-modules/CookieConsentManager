tarteaucitron.services.userway = {
    "key": "userway",
    "type": "support",
    "name": "Userway",
    "uri": "https://userway.org",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.userwayId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://cdn.userway.org/widget.js', '', '', '', 'data-account', tarteaucitron.user.userwayId);
    }
};
