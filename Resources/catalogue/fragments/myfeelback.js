tarteaucitron.services.myfeelback = {
    "key": "myfeelback",
    "type": "api",
    "name": "MyFeelBack (Skeepers)",
    "uri": "https://help.myfeelback.com/fr/quels-sont-les-cookies-d%C3%A9pos%C3%A9s-par-un-dispositif-de-collecte-myfeelback",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.myfeelbackId === undefined) {
            return;
        }

        window._Mfb_useCookie = true;
        window._Mfb_ud = {
            var1: undefined,
            var2: undefined,
            varN: undefined,
            _context: {
                lang: undefined,
                privacyMode: false,
                _page: {
                    url: location.pathname,
                    storageDuration: 30
                }
            }
        };
        tarteaucitron.addScript('https://actorssl-5637.kxcdn.com/actor/'+tarteaucitron.user.myfeelbackId+'/action', 'MFBActor');
    }
};
