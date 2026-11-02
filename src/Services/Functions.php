<?php

namespace App\Services;

use Doctrine\ORM\EntityManagerInterface;

class Functions
{

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

   /* public function dropTableCascade(string $tableName): void
    {
        $conn = $this->em->getConnection();
        $sql = sprintf('DROP TABLE IF EXISTS %s CASCADE', $tableName);
        $conn->executeStatement($sql);
    }*/

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
