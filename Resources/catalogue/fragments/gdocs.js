tarteaucitron.services.gdocs = {
    "key": "gdocs",
    "type": "other",
    "name": "Google Docs",
    "needConsent": true,
    "cookies": ["CONSENT", "NID"],
    "uri": "https://policies.google.com/privacy",
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['gdocs_embed'], function (x) {
            var frame_title = (tarteaucitron.getElemAttr(x,"title")) ? tarteaucitron.getElemAttr(x,"title") : 'Google docs iframe',
                id = tarteaucitron.getElemAttr(x, 'id'),
                width = tarteaucitron.getElemAttr(x, 'width'),
                height = tarteaucitron.getElemAttr(x, 'height');

            var styleAttr = (width !== "" ? "width:" + tarteaucitron.getStyleSize(width) + ";" : "") + (height !== "" ? "height:" + tarteaucitron.getStyleSize(height) + ";" : "");

            return "<iframe title=\"" + frame_title + "\" style=\"" + styleAttr + "\" src=\"https://docs.google.com/document/d/e/" + id + "/pub?embedded=true\"></iframe>";
        });
    },
    "fallback": function () {
        "use strict";
        var id = "gdocs";
        tarteaucitron.fallback(["gdocs_embed"], tarteaucitron.engage(id));
    }
};
