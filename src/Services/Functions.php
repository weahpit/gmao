<?php

namespace App\Services;

class Functions
{

    function generateRandomCode($length = 8): string {
        // Caractères autorisés
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charsLength = strlen($chars);
        $code = '';

        // Génération sécurisée
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, $charsLength - 1)];
        }

        return $code;
    }

}
