tarteaucitron.services.xiti_smarttag = {
    "key": "xiti_smarttag",
    "type": "analytic",
    "name": "Xiti (SmartTag)",
    "uri": "https://www.atinternet.com/rgpd-et-vie-privee/",
    "needConsent": true,
    "cookies": ["atidvisitor", "atreman", "atredir", "atsession", "attvtreman", "attvtsession"],
    "js": function () {
        "use strict";
        if (tarteaucitron.user.xiti_smarttagLocalPath !== undefined) {
            tarteaucitron.addScript(tarteaucitron.user.xiti_smarttagLocalPath, 'smarttag', null, null, "onload", "addTracker();");
        } else {
            var xitiSmarttagId = tarteaucitron.user.xiti_smarttagSiteId;
            if (xitiSmarttagId === undefined) {
                return;
            }

            tarteaucitron.addScript('//tag.aticdn.net/' + xitiSmarttagId + '/smarttag.js', 'smarttag', null, null, "onload", "addTracker();");
        }
    }
};
