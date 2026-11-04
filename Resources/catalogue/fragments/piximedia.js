tarteaucitron.services.piximedia = {
    "key": "piximedia",
    "type": "ads",
    "name": "Piximedia",
    "uri": "https://piximedia.com/privacy/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.piximediaName === undefined || tarteaucitron.user.piximediaTag === undefined || tarteaucitron.user.piximediaType === undefined || tarteaucitron.user.piximediaId === undefined) {
            return;
        }

        tarteaucitron.addScript('https://ad.piximedia.com/tools/activity/?' + tarteaucitron.user.piximediaName + '||'+ tarteaucitron.user.piximediaTag + '|' + tarteaucitron.user.piximediaType + '|' + tarteaucitron.user.piximediaId + '|||||');
    }
};
