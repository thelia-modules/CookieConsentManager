tarteaucitron.services.force24 = {
    "key": "force24",
    "type": "analytic",
    "name": "Force24",
    "uri": "https://support.force24.co.uk/support/solutions/articles/79000128057-cookie-policies",
    "needConsent": true,
    "cookies": ['F24_autoID', 'F24_personID'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.force24trackingId === undefined || tarteaucitron.user.force24clientId === undefined) {
            return;
        }

        window.Force24Object = "f24", window["f24"] = window["f24"] || function() {
            window["f24"].q = window["f24"].q || [],
                window["f24"].q.push(arguments)
        }, window["f24"].l = 1 * new Date;

        tarteaucitron.addScript('https://static.websites.data-crypt.com/scripts/activity/v3/inject-v3.min.js');

        f24('config', 'set_tracking_id', tarteaucitron.user.force24trackingId);
        f24('config', 'set_client_id', tarteaucitron.user.force24clientId);
    }
};
