tarteaucitron.services.tawkto = {
    "key": "tawkto",
    "type": "support",
    "name": "Tawk.to chat",
    "uri": "https://www.tawk.to/data-protection/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.tawktoId === undefined) {
            return;
        }

        tarteaucitron.user.tawktoWidgetId = tarteaucitron.user.tawktoWidgetId || 'default';

        window.Tawk_API = window.Tawk_API || {};
        window.Tawk_LoadStart = new Date();

        tarteaucitron.addScript('https://embed.tawk.to/' + tarteaucitron.user.tawktoId + '/' + tarteaucitron.user.tawktoWidgetId);
    }

};
