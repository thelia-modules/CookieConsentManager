tarteaucitron.services.outbrainamplify = {
    "key": "outbrainamplify",
    "type": "ads",
    "name": "Outbrain Amplify",
    "uri": "https://www.outbrain.com/privacy/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.outbrainamplifyId === undefined) {
            return;
        }

        var OB_ADV_ID = tarteaucitron.user.outbrainamplifyId;
        if (window.obApi) {
            var toArray = function(object) {
                return Object.prototype.toString.call(object) === '[object Array]' ? object : [object];
            };
            window.obApi.marketerId = toArray(_window.obApi.marketerId).concat(toArray(OB_ADV_ID));
            return;
        }
        var api = window.obApi = function() {
            api.dispatch ? api.dispatch.apply(api, arguments) : api.queue.push(arguments);
        };
        api.version = '1.1';
        api.loaded = true;
        api.marketerId = OB_ADV_ID;
        api.queue = [];

        tarteaucitron.addScript('https://amplify.outbrain.com/cp/obtp.js', '', function () {
            obApi('track', 'PAGE_VIEW');
        });
    }
};
