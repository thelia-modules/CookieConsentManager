tarteaucitron.services.crisp = {
    "key": "crisp",
    "type": "support",
    "name": "Crisp Chat",
    "uri": "https://help.crisp.chat/en/article/crisp-chatbox-cookie-ip-policy-1147xor/",
    "needConsent": true,
    "cookies": ['crisp-client'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.crispID === undefined) {
            return;
        }

        window.$crisp = [];
        window.CRISP_WEBSITE_ID = tarteaucitron.user.crispID;

        tarteaucitron.addScript('https://client.crisp.chat/l.js');

        var theCookies = document.cookie.split(';');
        for (var i = 1; i <= theCookies.length; i++) {
            var cookie = theCookies[i - 1].split('=');
            var cookieName = cookie[0].trim();

            if (cookieName.indexOf('crisp-client') === 0) {
                tarteaucitron.services.crisp.cookies.push(cookieName);
            }
        }
    }
};
