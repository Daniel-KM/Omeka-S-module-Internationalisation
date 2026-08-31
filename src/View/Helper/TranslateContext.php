<?php declare(strict_types=1);

namespace Internationalisation\View\Helper;

use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\View\Helper\AbstractHelper;

/**
 * View helper to translate a string that depends on a context.
 *
 * The same string may need two translations according to where it is displayed,
 * for example "Home" as a menu entry or as a button. Gettext manages that with
 * the keyword "msgctxt", but laminas-i18n has no api for it, even if its loader
 * keeps the contexts: it stores them in the key, prefixed to the message and
 * separated by the character "end of transmission" (\x04).
 *
 * So the context is prepended here, and the message alone is used as a fallback
 * when the pair message/context is not translated.
 */
class TranslateContext extends AbstractHelper
{
    /**
     * The separator used by gettext between a context and a message.
     *
     * @var string
     */
    const CONTEXT_SEPARATOR = "\x04";

    /**
     * @var \Laminas\I18n\Translator\TranslatorInterface
     */
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Translate a message according to a context.
     *
     * @param string $message
     * @param string $context The context of the message, for example "menu".
     * @param string $textDomain
     * @param string $locale
     * @return string The message itself when there is no translation.
     */
    public function __invoke($message, $context, $textDomain = 'default', $locale = null): string
    {
        $message = (string) $message;
        $context = (string) $context;

        if ($context === '') {
            return $this->translator->translate($message, $textDomain, $locale);
        }

        $key = $context . self::CONTEXT_SEPARATOR . $message;
        $translation = $this->translator->translate($key, $textDomain, $locale);

        // The translator returns the key when there is no translation for it.
        return $translation === $key
            ? $this->translator->translate($message, $textDomain, $locale)
            : $translation;
    }
}
