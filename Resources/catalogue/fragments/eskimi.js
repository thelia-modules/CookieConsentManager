tarteaucitron.services.eskimi = {
    "key": "eskimi",
    "type": "ads",
    "name": "Eskimi",
    "uri": "https://fr.eskimi.com/privacy-policy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.eskimiInit === undefined) {
            return;
        }

        window.___esk = window.esk = function () {
            window.___esk.callMethod ? window.___esk.callMethod.apply(window.___esk, arguments) : window.___esk.queue.push(arguments);
        };
        window.___esk.push = window.___esk;
        window.___esk.loaded = true;
        window.___esk.queue = [];

        tarteaucitron.addScript("https://dsp-media.eskimi.com/assets/js/e/gtr.min.js", '', function () {
            esk('init', tarteaucitron.user.eskimiInit);
        });
    }
};
