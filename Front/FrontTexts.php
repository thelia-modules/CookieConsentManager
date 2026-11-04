<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Every text the visitor reads that the administrator does not type: fixed labels of the templates,
 * messages of the engine, and the default text of the editable fields and purposes. The English
 * sentence is the message id, so that a language without catalogue shows English.
 */
final readonly class FrontTexts
{
    public const DOMAIN = 'cookieconsentmanager.fo.flexy';

    /** Languages the module ships a catalogue for, with the locale of that catalogue. */
    public const LOCALES = [
        'fr' => 'fr_FR',
        'en' => 'en_US',
        'de' => 'de_DE',
        'nl' => 'nl_NL',
        'es' => 'es_ES',
    ];

    /** @var array<string, string> name used by the templates => English message */
    private const LABELS = [
        'colon' => ': ',
        'save' => 'Save my choices',
        'close_panel' => 'Close',
        'panel_title' => 'Cookie preferences',
        'purposes_intro' => 'Purposes for which trackers may be used:',
        'third_parties' => 'Third-party services may set trackers. The list of these controllers is in your preferences.',
        'how_to_refuse' => 'Refusing is as easy as accepting, and you can withdraw your choice at any time through "Manage my cookies".',
        'policy' => 'Cookie policy',
        'essential' => 'Essential',
        'essential_description' => 'Required for the site to work (cart, session, security). Always on.',
        'always_on' => 'Always on',
        'services' => 'Services',
        'controller' => 'Controller',
        'cookies' => 'Cookies',
        'none' => 'none',
        'remembered' => 'Your choice is remembered on this device for %duration%.',
        'consent_id' => 'Consent identifier',
        'measurement' => 'Audience measurement exempt from consent',
        'measurement_help' => 'This measurement is active. Turn the switch off to object to it.',
        'blocked' => 'Blocked content: %name%',
        'purpose' => 'Purpose',
        'preferences_link' => 'Preferences',
        'allow_service' => 'Allow %name%',
        'toggle' => 'Turn on or off',
        'page_intro' => 'This site uses trackers only with your consent. Here are the purposes and the services involved.',
        'page_no_js' => 'Without JavaScript, no tracker that needs consent runs on this site: your choices cannot be changed from this page.',
        'page_howto' => 'To choose or withdraw your consent: enable JavaScript and use the button below, or the "Manage my cookies" link at the bottom of every page. Withdrawing is as easy as giving consent. You can also delete cookies in your browser settings.',
        'page_open' => 'Change my choices',
        'page_back' => 'Back to the site',
        'measurement_notice' => 'An audience measurement exempt from consent is active on this site (%names%).',
        'measurement_object' => 'Object to it',
        'withdrawn_notice' => 'Cookies already set by third-party services (videos, social networks, advertising) stay in your browser: you can delete them from its settings.',
        'duration_minute' => '1 minute',
        'duration_minutes' => '%count% minutes',
        'duration_day' => '1 day',
        'duration_days' => '%count% days',
        'duration_month' => '1 month',
        'duration_months' => '%count% months',
        'duration_year' => '1 year',
        'duration_years' => '%count% years',
        'duration_session' => 'session',
    ];

    /** @var array<string, string> name used by the generated model of the cookie policy page => English message */
    private const POLICY = [
        'policy_intro' => 'This page explains which cookies and similar trackers this site uses, who is responsible for them, what they are for and how long they last. Except those strictly necessary to run the site, none is set before you choose.',
        'policy_publisher_title' => 'Who publishes this site',
        'policy_publisher' => 'This site is published by %identity%.',
        'policy_publisher_contact' => 'To contact the publisher: %email%.',
        'policy_responsible_title' => 'Who is responsible for the trackers',
        'policy_responsible' => 'The publisher of this site decides which third-party services it loads, and only does so after your choice. Each of these services is responsible for the processing it carries out for its own purposes; its privacy policy is linked in the list of services.',
        'policy_responsible_joint' => 'For some services, notably those of social networks and their advertising tools, the publisher of this site and the provider of the service are jointly responsible for collecting data on this site and transmitting it to the provider. The provider alone is responsible for what it does with this data afterwards.',
        'policy_services_title' => 'Services, cookies and lifetimes',
        'policy_list' => 'The services in use, the cookies each one sets, their lifetimes and the entity responsible for each are shown on this page, always up to date: %link%.',
        'policy_list_link' => 'Up-to-date list of services and cookies',
        'policy_choice_title' => 'How long your choice is kept',
        'policy_choice' => 'Your choice is kept on your device and asked again after %duration%. You can change or withdraw it at any time with the link "%link%" at the bottom of every page: withdrawing is as easy as accepting.',
        'policy_proof_title' => 'Proof of your choice',
        'policy_proof' => 'To be able to prove your choice, we keep a record of it (date, choice made, version of the texts shown, language, page, browser family) for %duration%.',
        'policy_proof_no_ip' => 'The record does not contain your IP address.',
        'policy_proof_ip' => 'The record contains your IP address, truncated.',
        'policy_rights_title' => 'Your rights',
        'policy_rights' => 'You have the right to access, rectify and erase your data and to object to its processing. To exercise them, contact the publisher of this site. You can also lodge a complaint with your data protection authority.',
    ];

    /** @var array<string, string> name read by the engine => English message */
    private const MESSAGES = [
        'saved' => 'Your choices have been saved.',
        'allowed' => 'Content unlocked.',
        'allow' => 'Allow %name%',
        'prefs' => 'Preferences',
        'blocked' => 'Blocked content: %name%',
        'preview' => 'Draft preview: simulation, no service is run.',
        'compat_allow' => 'Allow',
        'compat_fallback' => 'is disabled.',
    ];

    /** @var array<string, string> key of an editable text => English default */
    public const DEFAULT_TEXTS = [
        'title' => 'Your cookie choices',
        'body' => 'We use trackers to measure audience, personalise your experience and serve third-party content. You can accept, refuse or choose in detail, and change your mind at any time.',
        'accept' => 'Accept all',
        'reject' => 'Reject all',
        'customize' => 'Customize',
        'close' => 'Continue without accepting',
        'allow' => 'Allow',
        'preferences' => 'Manage my cookies',
        'free_text' => '',
    ];

    /** @var array<string, array{label: string, description: string}> purpose code => English default */
    public const DEFAULT_PURPOSES = [
        'analytics' => ['label' => 'Audience measurement', 'description' => 'Measure site traffic to improve the site.'],
        'ads' => ['label' => 'Advertising', 'description' => 'Serve and measure personalised advertising.'],
        'social' => ['label' => 'Social networks', 'description' => 'Share content and interact with social networks.'],
        'media' => ['label' => 'Third-party content', 'description' => 'Display videos, maps and content hosted by third parties.'],
        'support' => ['label' => 'Customer support', 'description' => 'Talk to support (chat).'],
        'personalization' => ['label' => 'Preferences and personalisation', 'description' => 'Remember your preferences and personalise content.'],
    ];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    public static function supports(string $language): bool
    {
        return isset(self::LOCALES[$language]);
    }

    /** @return array<string, string> */
    public function forLanguage(string $language): array
    {
        return array_map(fn (string $message): string => $this->say($message, $language), self::LABELS);
    }

    /**
     * Messages of the engine; `%name%` becomes the `{{name}}` marker the engine replaces itself.
     *
     * @return array<string, string>
     */
    public function messages(string $language): array
    {
        return array_map(fn (string $message): string => $this->say($message, $language, ['%name%' => '{{name}}']), self::MESSAGES);
    }

    /** @return array<string, string> */
    public function policyPage(string $language): array
    {
        return array_map(fn (string $message): string => $this->say($message, $language), self::POLICY);
    }

    /** @return array<string, string> */
    public function defaultTexts(string $language): array
    {
        return array_map(fn (string $message): string => '' === $message ? '' : $this->say($message, $language), self::DEFAULT_TEXTS);
    }

    /** @return array{label: string, description: string}|null */
    public function defaultPurpose(string $code, string $language): ?array
    {
        if (!isset(self::DEFAULT_PURPOSES[$code])) {
            return null;
        }

        return array_map(fn (string $message): string => $this->say($message, $language), self::DEFAULT_PURPOSES[$code]);
    }

    /** @param array<string, string> $parameters */
    private function say(string $message, string $language, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, self::DOMAIN, self::LOCALES[$language] ?? self::LOCALES['en']);
    }
}
