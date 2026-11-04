tarteaucitron.services.aduptech_ads = {
    "key": "aduptech_ads",
    "type": "ads",
    "name": "Ad Up Technology (ads)",
    "uri": "https://www.adup-tech.com/datenschutz",
    "needConsent": true,
    "cookies": [],
    "js": function () {
        "use strict";

        var IDENTIFIER = "aduptech_ads",
            API_URL = "https://s.d.adup-tech.com/jsapi";

        var elements = document.getElementsByClassName(IDENTIFIER);
        if (!elements || elements.length === 0) {
            return;
        }

        tarteaucitron.fallback([IDENTIFIER], "");

        tarteaucitron.addScript(API_URL, "", function () {
            for (var i = 0; i < elements.length; i++) {
                var element = elements[i];

                if (!tarteaucitron.getElemAttr(element, "id")) {
                    element.setAttribute("id", IDENTIFIER + Math.random().toString(36).substr(2, 9));
                }

                window.uAd.embed(tarteaucitron.getElemAttr(element, "id"), {
                    placementKey: tarteaucitron.getElemAttr(element, "placementKey"),
                    responsive: Boolean(tarteaucitron.getElemAttr(element, "responsive")),
                    lazy: Boolean(tarteaucitron.getElemAttr(element, "lazy")),
                    adtest: Boolean(tarteaucitron.getElemAttr(element, "test")),
                    query: tarteaucitron.getElemAttr(element, "query") || "",
                    minCpc: tarteaucitron.getElemAttr(element, "minCpc") || "",
                    pageUrl: tarteaucitron.getElemAttr(element, "pageUrl") || "",
                    skip: tarteaucitron.getElemAttr(element, "skip") || ""
                });
            }
        });

    },
    "fallback": function () {
        "use strict";
        tarteaucitron.fallback(["aduptech_ads"], tarteaucitron.engage("aduptech_ads"));
    }
};
