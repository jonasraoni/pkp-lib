<?php

declare(strict_types=1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/translation/IsoCodesTranslationDriver.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IsoCodesTranslationDriver
 * @ingroup i18n
 *
 * @brief Translation provider for the IsoCodes package, faster and optimized to not keep items in memory
 */

namespace PKP\i18n\translation;

use DomainException;
use Gettext\Loader\MoLoader;
use PKP\i18n\interfaces\LocaleInterface;
use PKP\i18n\translation\Translator;
use Sokil\IsoCodes\TranslationDriver\TranslationDriverInterface;

class IsoCodesTranslationDriver implements TranslationDriverInterface
{
    protected ?Translator $translator = null;
    protected string $locale;

    public function __construct(string $locale)
    {
        $this->setLocale($locale);
    }

    /**
     * Setups the translator
     */
    public function configureDirectory(string $isoNumber, string $directory): void
    {
        if (!preg_match(LocaleInterface::LOCALE_EXPRESSION, $this->locale, $matches)) {
            throw new DomainException("Invalid locale \"{$this->locale}\"");
        }
        $locale = (object) [
            'language' => $matches['language'],
            'country' => $matches['country'] ?? null,
            'script' => $matches['script'] ?? null
        ];
        $locales = [$this->locale, ...($locale->script ? [$locale->language . '@' . $locale->script] : []), $locale->language];
        // Attempts to find the best locale
        foreach ($locales as $locale) {
            $path = "${directory}/${locale}/LC_MESSAGES/${isoNumber}.mo";
            if (file_exists($path)) {
                $this->translator = Translator::createFromTranslations((new MoLoader())->loadFile($path));
                break;
            }
        }
    }

    /**
     * Setup the driver locale
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Attempts to translate an entry
     */
    public function translate(string $isoNumber, string $message): string
    {
        return ($this->translator ? $this->translator->getSingular($message) : $message) ?: $message;
    }
}
