<?php

declare(strict_types=1);

namespace DiviToElementor\Mapper;

class IdGenerator
{
    /** @var array<string,true> Registry des IDs déjà émis dans cette instance */
    private array $emitted = [];

    /**
     * Génère un ID unique sur 8 caractères hexadécimaux.
     * Boucle jusqu'à unicité.
     * Utilise random_bytes() — cryptographiquement sûr.
     *
     * @return string  8 caractères hex — ex: "a1b2c3d4"
     */
    public function generate(): string
    {
        do {
            $id = bin2hex(random_bytes(4));
        } while (isset($this->emitted[$id]));

        $this->emitted[$id] = true;

        return $id;
    }

    /**
     * Réinitialise le registry (usage test uniquement).
     */
    public function reset(): void
    {
        $this->emitted = [];
    }
}
