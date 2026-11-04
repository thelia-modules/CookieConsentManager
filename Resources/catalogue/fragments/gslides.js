tarteaucitron.services.gslides = {
    "key": "gslides",
    "type": "other",
    "name": "Google Slides",
    "needConsent": true,
    "cookies": ["CONSENT", "NID"],
    "uri": "https://policies.google.com/privacy",
    "js": function () {
        "use strict";
        tarteaucitron.fallback(['gslides_embed'], function (x) {
            var frame_title = (tarteaucitron.getElemAttr(x,"title")) ? tarteaucitron.getElemAttr(x,"title") : 'Google slides iframe',
                id = tarteaucitron.getElemAttr(x, 'id'),
                width = tarteaucitron.getElemAttr(x, 'width'),
                height = tarteaucitron.getElemAttr(x, 'height'),
                autostart = tarteaucitron.getElemAttr(x, 'autostart'),
                loop = tarteaucitron.getElemAttr(x, 'loop'),
                delay = tarteaucitron.getElemAttr(x, 'delay');

            var styleAttr = (width !== "" ? "width:" + tarteaucitron.getStyleSize(width) + ";" : "") + (height !== "" ? "height:" + tarteaucitron.getStyleSize(height) + ";" : "");

            return "<iframe title=\"" + frame_title + "\" style=\"" + styleAttr + "\" src=\"https://docs.google.com/presentation/d/e/" + id + "/embed?start=" + autostart + "&loop=" + loop + "&delayms=" + delay + "\" allowfullscreen mozallowfullscreen webkitallowfullscreen></iframe>";
        });
    },
    "fallback": function () {
        "use strict";
        var id = "gslides";
        tarteaucitron.fallback(["gslides_embed"], tarteaucitron.engage(id));
    }
};
