// Independent oracle for verified services: user values and the URL each one must load.
const KEY_AIza = `AIza${'a'.repeat(35)}`;

export const FIXTURES = {
  gtag: { user: { gtagUa: 'G-TEST123456' }, script: 'https://www.googletagmanager.com/gtag/js?id=G-TEST123456' },
  multiplegtag: { user: { multiplegtagUa: ['G-TEST123456'] }, script: 'https://www.googletagmanager.com/gtag/js?id=G-TEST123456' },
  googletagmanager: { user: { googletagmanagerId: 'GTM-TEST12' }, script: 'https://www.googletagmanager.com/gtm.js?id=GTM-TEST12' },
  googleads: { user: { googleadsId: 'AW-1234567890' }, script: 'https://www.googletagmanager.com/gtag/js?id=AW-1234567890' },
  googleadwordsconversion: { user: { adwordsconversionId: '123456789' }, script: '//www.googleadservices.com/pagead/conversion_async.js' },
  googleadwordsremarketing: { user: { adwordsremarketingId: '123456789' }, script: '//www.googleadservices.com/pagead/conversion_async.js' },
  facebookpixel: { user: { facebookpixelId: '123456789012345' }, script: 'https://connect.facebook.net/en_US/fbevents.js' },
  bingads: { user: { bingadsID: '12345678' }, script: 'https://bat.bing.com/bat.js' },
  linkedininsighttag: { user: { linkedininsighttag: '1234567' }, script: 'https://snap.licdn.com/li.lms-analytics/insight.min.js' },
  tiktok: { user: { tiktokId: 'C4ABCDEF1234567890AB' }, domScript: 'https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=C4ABCDEF1234567890AB&lib=ttq' },
  pinterestpixel: { user: { pinterestpixelId: '2612345678901' }, script: 'https://s.pinimg.com/ct/core.js' },
  matomo: { user: { matomoHost: 'https://stats.example.test/', matomoId: '3' }, script: 'https://stats.example.test/piwik.js' },
  matomotm: { user: { matomotmUrl: 'https://stats.example.test/js/container_AbCd1234.js' }, script: 'https://stats.example.test/js/container_AbCd1234.js' },
  hotjar: { user: { hotjarId: '123456', HotjarSv: '6' }, script: 'https://static.hotjar.com/c/hotjar-123456.js?sv=6' },
  clarity: { user: { clarity: 'abcde12345' }, script: 'https://www.clarity.ms/tag/abcde12345' },
  crisp: { user: { crispID: '12345678-1234-1234-1234-123456789abc' }, script: 'https://client.crisp.chat/l.js' },
  zopim: { user: { zopimID: 'abcdefghij0123456789abcdefghij01' }, script: '//v2.zopim.com/?abcdefghij0123456789abcdefghij01' },
  sendinblue: { user: { sendinblueKey: 'sibkey0123456789abcdef' }, script: 'https://sibautomation.com/sa.js?key=sibkey0123456789abcdef' },
  googlemaps: { user: { googlemapsKey: KEY_AIza }, script: `https://maps.googleapis.com/maps/api/js?loading=async&v=3.exp&key=${KEY_AIza}&callback=tac_googlemaps_callback` },
  recaptcha: { user: {}, script: 'https://www.google.com/recaptcha/api.js?onload=tacRecaptchaOnLoad' },
  multiplegoogletagmanager: { user: { multiplegoogletagmanagerId: ['GTM-TEST12'] } },
  // media embeds: class marking the element, substring expected in the rendered iframe src
  youtube: { user: {}, media: { cls: 'youtube_player', iframeSrc: 'youtube-nocookie.com/embed/abc123' } },
  vimeo: { user: {}, media: { cls: 'vimeo_player', iframeSrc: 'player.vimeo.com/video/abc123' } },
  dailymotion: { user: {}, media: { cls: 'dailymotion_player', iframeSrc: 'dailymotion.com/embed/video/abc123' } },
  maps_noapi: { user: {}, media: { cls: 'googlemaps_embed', iframeSrc: 'https://www.google.com/maps/embed?pb=PB123' } },
};

export function defaultUser(entry) {
  return Object.fromEntries(entry.params.map((p) => [p.name, 'ccm-test']));
}
