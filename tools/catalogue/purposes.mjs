export const TYPE_TO_PURPOSE = {
  analytic: 'analytics',
  ads: 'ads',
  social: 'social',
  comment: 'social',
  video: 'media',
  api: 'media',
  support: 'support',
};

// Upstream category "other": explicit per-service mapping. The build fails on an unmapped "other" service.
export const OTHER_PURPOSE = {
  iframe: 'media',
  fillout: 'media',
  activecampaignvgo: 'ads',
  sendinblue: 'ads',
  collectchat: 'support',
  posthog: 'analytics',
  googlesignin: 'personalization',
  calendly: 'media',
  tolkai: 'support',
  trustpilot: 'media',
  issuu: 'media',
  matterport: 'media',
  faciliti: 'personalization',
  maps_noapi: 'media',
  hcaptcha: 'media',
  gagenda: 'media',
  gdocs: 'media',
  gsheets: 'media',
  gslides: 'media',
  gforms: 'media',
  goptimize: 'analytics',
  gallica: 'media',
};

export const GOOGLE_TAG_KEYS = new Set(['gtag', 'googletagmanager', 'googleads', 'multiplegtag']);

/** Services dropped by hand, whatever their code: key => reason (traced in REPORT.md). */
export const EXCLUDED_BY_KEY = {
  // Universal Analytics was shut down by Google; its fragment also hands `timeExpire` (milliseconds) to `cookieExpires`, which expects seconds.
  analytics: 'universal-analytics',
};
