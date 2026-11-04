tarteaucitron.services.dynatrace = {
    "key": "dynatrace",
    "type": "api",
    "name": "Dynatrace",
    "uri": "https://www.dynatrace.com/company/trust-center/privacy/",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        if (tarteaucitron.user.dynatraceJSPath === undefined || tarteaucitron.user.dynatraceConfig === undefined) {
            return;
        }

        tarteaucitron.addScript(tarteaucitron.user.dynatraceJSPath, '', '', '', 'data-dtconfig', tarteaucitron.user.dynatraceConfig);
    }
};
