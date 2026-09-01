<?php

namespace App\Services\Notificaciones\Tipos;

use App\Models\Configuracion;
use App\Models\TasaCambio;
use App\Services\Notificaciones\Contracts\Notificacion;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class RecordatorioTasa implements Notificacion
{
    private ?CarbonInterface $ventanaCache = null;

    private bool $ventanaCalculada = false;

    public function tipo(): string
    {
        return 'recordatorio_tasa';
    }

    public function permisoRequerido(): ?string
    {
        return 'gestionar-tasas';
    }

    public function debeMostrar(CarbonInterface $ahora): bool
    {
        return $this->ventanaVencida($ahora) !== null;
    }

    public function titulo(): string
    {
        return 'Actualizar tasa de cambio';
    }

    public function mensaje(): string
    {
        $ventana = $this->ventanaVencida(now());

        return 'La tasa de referencia está pendiente desde las '.$ventana?->format('H:i').'. Actualízala para mantener los precios correctos.';
    }

    public function accionUrl(): ?string
    {
        return route('tasas-cambio.index');
    }

    public function textoAccion(): ?string
    {
        return 'Actualizar tasa';
    }

    public function posponerHasta(CarbonInterface $ahora): CarbonInterface
    {
        foreach ($this->horas() as $hora) {
            if ($hora->gt($ahora)) {
                return $hora;
            }
        }

        // No quedan ventanas hoy: primera hora de mañana.
        return $this->horas()[0]->copy()->addDay();
    }

    /**
     * Primera ventana del día cuya hora pasó sin que la tasa de referencia
     * se haya actualizado después de su inicio; null si todo está al día.
     */
    private function ventanaVencida(CarbonInterface $ahora): ?CarbonInterface
    {
        if ($this->ventanaCalculada) {
            return $this->ventanaCache;
        }

        $resultado = null;

        if ($this->estaHabilitado()) {
            $tasa = TasaCambio::ultimaDe(Configuracion::obtener('tasa_referencia', 'bcv'));
            $ultimaActualizacion = $tasa?->created_at;

            foreach ($this->horas() as $hora) {
                if ($ahora->lt($hora)) {
                    continue;
                }

                if (! $ultimaActualizacion || $ultimaActualizacion->lt($hora)) {
                    $resultado = $hora;
                    break;
                }
            }
        }

        $this->ventanaCalculada = true;
        $this->ventanaCache = $resultado;

        return $resultado;
    }

    private function estaHabilitado(): bool
    {
        return Configuracion::obtener('recordatorio_tasa_activo', '1') === '1';
    }

    /**
     * @return CarbonInterface[]
     */
    private function horas(): array
    {
        return collect([
            Configuracion::obtener('recordatorio_tasa_hora1', '09:00'),
            Configuracion::obtener('recordatorio_tasa_hora2', '14:00'),
        ])
            ->map(fn ($hora) => Carbon::createFromFormat('H:i', (string) $hora))
            ->filter(fn ($carbon) => $carbon instanceof CarbonInterface)
            ->sort()
            ->values()
            ->all();
    }
}
