tarteaucitron.services.gforms = {
    "key": "gforms",
    "type": "other",
    "name": "Google Forms",
    "needConsent": true,
    "cookies": ["CONSENT", "NID"],
    "uri": "https://policies.google.com/privacy",
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['gforms_embed'], function (x) {
            var frame_title = (tarteaucitron.getElemAttr(x,"title")) ? tarteaucitron.getElemAttr(x,"title") : 'Google forms iframe',
                id = tarteaucitron.getElemAttr(x, 'id'),
                width = tarteaucitron.getElemAttr(x, 'width'),
                height = tarteaucitron.getElemAttr(x, 'height');

            var styleAttr = (width !== "" ? "width:" + tarteaucitron.getStyleSize(width) + ";" : "") + (height !== "" ? "height:" + tarteaucitron.getStyleSize(height) + ";" : "");

            return "<iframe title=\"" + frame_title + "\" style=\"" + styleAttr + "\" src=\"https://docs.google.com/forms/d/e/" + id + "/viewform?embedded=true\"></iframe>";
        });
    },
    "fallback": function () {
        "use strict";
        var id = "gforms";
        tarteaucitron.fallback(['gforms_embed'], tarteaucitron.engage(id));
    }
};
