tarteaucitron.services.teambrain = {
    "key": "teambrain",
    "type": "analytic",
    "name": "TeamBrain",
    "uri": "https://teambrain.app/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.teambrainUrl === undefined || tarteaucitron.user.teambrainProxyUrl === undefined) {
            return;
        }

        tarteaucitron.addScript(tarteaucitron.user.teambrainUrl, 'tb-ext-app', '', '', 'data-proxy-url', tarteaucitron.user.teambrainProxyUrl);
    }
};
