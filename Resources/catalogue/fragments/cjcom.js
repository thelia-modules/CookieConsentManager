tarteaucitron.services.cjcom = {
    "key": "cjcom",
    "type": "ads",
    "name": "CJ.com",
    "uri": "https://www.cj.com/legal/privacy-policy-services",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.cjUserId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://www.mczbf.com/tags/'+tarteaucitron.user.cjUserId+'/tag.js', 'cjapitag');
    }
};
