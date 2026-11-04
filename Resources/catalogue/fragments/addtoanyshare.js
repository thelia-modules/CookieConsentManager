tarteaucitron.services.addtoanyshare = {
    "key": "addtoanyshare",
    "type": "social",
    "name": "AddToAny (share)",
    "uri": "https://www.addtoany.com/privacy",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['tac_addtoanyshare'], function (elem) {
            elem.remove();
        }, true);
        tarteaucitron.addScript('//static.addtoany.com/menu/page.js');
    },
    "fallback": function () {
        "use strict";
        var id = 'addtoanyshare';
        tarteaucitron.fallback(['tac_addtoanyshare'], tarteaucitron.engage(id));
    }
};
