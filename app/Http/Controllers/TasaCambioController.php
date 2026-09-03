<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\TasaCambio;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TasaCambioController extends Controller
{
    private const TASA_REFERENCIA_CLAVE = 'tasa_referencia';

    public function index()
    {
        $tasas = TasaCambio::ultimasPorTipo()->sortBy(
            fn ($t) => [! $t->activo, $t->tipo]
        );
        $tasaReferencia = Configuracion::obtener(self::TASA_REFERENCIA_CLAVE, 'bcv');

        return view('tasas_cambio.index', compact('tasas', 'tasaReferencia'));
    }

    public function historial(Request $request)
    {
        $tipos = TasaCambio::ultimasPorTipo();

        $query = TasaCambio::with('user')->orderByDesc('created_at')->orderByDesc('id');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $historial = $query->paginate(20);

        $filas = $historial->items();
        foreach ($filas as $i => $fila) {
            $fila->variacion = null;
            for ($j = $i + 1; $j < count($filas); $j++) {
                if ($filas[$j]->tipo !== $fila->tipo) {
                    continue;
                }

                $anterior = (float) $filas[$j]->monto;
                if ($anterior <= 0) {
                    $fila->variacion = null;
                    break;
                }
                $fila->variacion = round((($fila->monto - $anterior) / $anterior) * 100, 2);
                break;
            }
        }

        $tipoFiltro = $request->get('tipo', '');

        return view('tasas_cambio.historial', compact('historial', 'tipos', 'tipoFiltro'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'nombre' => 'required|string|max:255',
            'monto' => 'required|numeric|gt:0',
        ]);

        $tipo = $request->input('tipo') ?: $this->generarTipo($data['nombre']);

        TasaCambio::create([
            'tipo' => $tipo,
            'nombre' => $data['nombre'],
            'monto' => $data['monto'],
            'fecha' => now()->toDateString(),
            'activo' => true,
            'user_id' => auth()->id(),
            'origen' => 'manual',
        ]);

        return redirect()->route('tasas-cambio.index')
            ->with('success', 'Tasa "'.$data['nombre'].'" creada correctamente.');
    }

    private function generarTipo(string $nombre): string
    {
        $base = Str::lower(Str::slug($nombre, '_'));
        $base = preg_replace('/[^a-z0-9_]/', '', $base);

        if ($base === '') {
            $base = 'tasa';
        }

        $tipo = $base;
        $i = 2;
        while (TasaCambio::where('tipo', $tipo)->exists()) {
            $tipo = $base.'_'.$i;
            $i++;
        }

        return $tipo;
    }

    public function actualizar(Request $request)
    {
        $data = $request->validate([
            'tipo' => 'required|exists:tasa_cambios,tipo',
            'monto' => 'required|numeric|gt:0',
        ]);

        $vigente = TasaCambio::ultimaDe($data['tipo']);

        TasaCambio::create([
            'tipo' => $data['tipo'],
            'nombre' => $vigente->nombre,
            'monto' => $data['monto'],
            'fecha' => now()->toDateString(),
            'activo' => $vigente->activo,
            'user_id' => auth()->id(),
            'origen' => 'manual',
        ]);

        return redirect()->route('tasas-cambio.index')
            ->with('success', 'Tasa '.$vigente->nombre.' actualizada a '.number_format($data['monto'], 2).' USD.');
    }

    public function toggleEstado(TasaCambio $tasa)
    {
        $tasaReferencia = Configuracion::obtener(self::TASA_REFERENCIA_CLAVE, 'bcv');
        $vigente = TasaCambio::ultimaDe($tasa->tipo);

        if ($vigente->activo && $vigente->tipo === $tasaReferencia) {
            return back()->withErrors(['error' => 'No se puede desactivar la tasa de referencia. Elige otra tasa como referencia primero.']);
        }

        TasaCambio::where('tipo', $tasa->tipo)->update(['activo' => ! $vigente->activo]);

        $accion = $vigente->activo ? 'desactivada' : 'activada';

        return redirect()->route('tasas-cambio.index')
            ->with('success', 'Tasa "'.$vigente->nombre.'" '.$accion.' correctamente.');
    }

    public function fijarReferencia(Request $request)
    {
        $request->validate([
            'referencia' => ['required', 'string', Rule::exists('tasa_cambios', 'tipo')->where('activo', true)],
        ]);

        Configuracion::updateOrCreate(
            ['clave' => self::TASA_REFERENCIA_CLAVE],
            ['valor' => $request->referencia]
        );
        Configuracion::olvidar(self::TASA_REFERENCIA_CLAVE);

        $tasa = TasaCambio::ultimaDe($request->referencia);

        return redirect()->route('tasas-cambio.index')
            ->with('success', 'La tasa "'.$tasa->nombre.'" ahora es la referencia para los cálculos de venta.');
    }
}
