<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\BusinessVerticalEnum;
use App\Models\GeneralSetting;

interface VerticalPresetServiceInterface
{
    /**
     * Aplicar un preset de vertical a la configuración general del tenant actual.
     *
     * @param BusinessVerticalEnum|string $vertical
     * @param bool $preserveIdentity Mantener datos fiscales, nombre y branding intactos.
     * @return GeneralSetting
     */
    public function applyPreset(BusinessVerticalEnum|string $vertical, bool $preserveIdentity = true): GeneralSetting;

    /**
     * Obtener la configuración de parámetros asociada a una vertical.
     *
     * @param BusinessVerticalEnum|string $vertical
     * @return array<string, mixed>
     */
    public function getPresetConfig(BusinessVerticalEnum|string $vertical): array;

    /**
     * Obtener la lista de presets disponibles con sus etiquetas y descripciones.
     *
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public function getAvailablePresets(): array;

    /**
     * Registrar una nueva vertical personalizada dinámica.
     *
     * @param string $key Clave única de la vertical (ej: 'veterinaria', 'ferreteria').
     * @param string $label Nombre legible.
     * @param string $description Descripción operativa.
     * @param array<string, mixed> $config Parámetros y banderas operativas.
     * @return array{key: string, label: string, description: string, config: array}
     */
    public function registerCustomPreset(string $key, string $label, string $description, array $config): array;

    /**
     * Obtener las verticales personalizadas registradas dinámicamente.
     *
     * @return array<string, array{key: string, label: string, description: string, config: array}>
     */
    public function getCustomPresets(): array;
}
