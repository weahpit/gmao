<?php

/*
 * Fuseau horaire de l'application : GMT+0 (UTC).
 *
 * Toutes les dates generees par PHP (y compris celles enregistrees en base)
 * sont exprimees en temps universel. Definir le fuseau ici, avant meme le
 * chargement du noyau, garantit qu'il s'applique en toutes circonstances.
 */
date_default_timezone_set('UTC');

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
