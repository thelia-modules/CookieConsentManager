tarteaucitron.services.userpilot = {
    "key": "userpilot",
    "type": "analytic",
    "name": "UserPilot",
    "uri": "https://userpilot.com/privacy-policy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.userpilotToken === undefined) {
            return;
        }

        window.userpilotSettings = {token: tarteaucitron.user.userpilotToken};
        tarteaucitron.addScript('https://js.userpilot.io/sdk/latest.js');
    }
};
