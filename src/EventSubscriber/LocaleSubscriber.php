<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Determine la langue de l'interface a chaque requete.
 *
 * Ordre de priorite :
 *   1. la langue choisie par l'utilisateur (memorisee en session)
 *   2. la langue preferee du navigateur / des parametres regionaux (fr ou en)
 *   3. la langue par defaut de l'application
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    /** Langues supportees par l'application. */
    public const LANGUES = ['fr', 'en'];

    public const LANGUE_DEFAUT = 'fr';

    public function __construct(private string $defaultLocale = 'fr')
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        // 1) Langue explicitement choisie par l'utilisateur
        $locale = $session->get('_locale');

        // 2) Sinon, langue preferee du navigateur (parametres regionaux)
        if (!$this->estSupportee($locale)) {
            $locale = $this->depuisNavigateur($request);
        }

        // 3) Sinon, langue par defaut
        if (!$this->estSupportee($locale)) {
            $locale = $this->estSupportee($this->defaultLocale) ? $this->defaultLocale : self::LANGUE_DEFAUT;
        }

        $request->setLocale($locale);
        $request->attributes->set('_locale', $locale);
    }

    /**
     * Lit l'en-tete Accept-Language du navigateur et retient la premiere
     * langue supportee (fr ou en).
     */
    private function depuisNavigateur($request): ?string
    {
        try {
            $preferee = $request->getPreferredLanguage(self::LANGUES);
        } catch (\Throwable) {
            return null;
        }

        return $this->estSupportee($preferee) ? $preferee : null;
    }

    private function estSupportee(?string $locale): bool
    {
        return $locale !== null && in_array(strtolower(substr($locale, 0, 2)), self::LANGUES, true);
    }

    public static function getSubscribedEvents(): array
    {
        // Priorite elevee : doit passer avant les autres abonnes qui lisent la locale
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
