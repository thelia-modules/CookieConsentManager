tarteaucitron.services.leadinfo = {
    "key": "leadinfo",
    "type": "analytic",
    "name": "Leadinfo",
    "uri": "https://www.leadinfo.com/en/privacy/",
    "needConsent": true,
    "cookies": ['_li_id', '_li_ses'],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.leadinfoId === undefined) {
            return;
        }

        window.GlobalLeadinfoNamespace = window.GlobalLeadinfoNamespace || [];
        window.GlobalLeadinfoNamespace.push("leadinfo");
        window["leadinfo"] = function() {
            (window["leadinfo"].q = window["leadinfo"].q || []).push(arguments)
        };
        window["leadinfo"].t = window["leadinfo"].t || tarteaucitron.user.leadinfoId;
        window["leadinfo"].q = window["leadinfo"].q || [];

        tarteaucitron.addScript('https://cdn.leadinfo.net/ping.js');
    }
};
