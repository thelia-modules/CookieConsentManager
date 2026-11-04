tarteaucitron.services.onesignal = {
    "key": "onesignal",
    "type": "api",
    "name": "OneSignal",
    "uri": "https://onesignal.com/privacy_policy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.onesignalAppId === undefined) {
            return;
        }
        window.OneSignal = window.OneSignal || [];

        window.OneSignal.push(function () {
            window.OneSignal.init({
                appId: tarteaucitron.user.onesignalAppId,
            });
        });

        tarteaucitron.addScript('https://cdn.onesignal.com/sdks/OneSignalSDK.js');
    }
};
