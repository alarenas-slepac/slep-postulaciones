<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FuncionarioApiIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FuncionarioApiIntegrationController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer'];

    public function index()
    {
        return response()->view('admin.funcionarios-api.index', [
            'integrations' => FuncionarioApiIntegration::with(['tokens' => fn ($query) => $query
                ->select(['id', 'tokenable_id', 'tokenable_type', 'expires_at', 'last_used_at', 'created_at'])->latest()])
                ->orderBy('name')->get(),
            'audits' => DB::table('funcionario_consulta_audits')->latest('id')->paginate(25),
        ])->withHeaders(self::HEADERS);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:funcionario_api_integrations,name'],
            'requests_per_minute' => ['required', 'integer', 'min:1', 'max:'.config('funcionario_consulta.max_requests_per_minute')],
            'token_days' => $this->daysRules(),
        ]);
        [$integration, $token] = DB::transaction(function () use ($data) {
            $integration = FuncionarioApiIntegration::create([
                'name' => $data['name'], 'enabled' => true, 'requests_per_minute' => $data['requests_per_minute'],
            ]);
            return [$integration, $integration->createToken('consulta', [FuncionarioApiIntegration::ABILITY], now()->addDays((int) $data['token_days']))];
        });
        return $this->tokenResponse($integration, $token);
    }

    public function update(Request $request, FuncionarioApiIntegration $integration)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('funcionario_api_integrations', 'name')->ignore($integration->id)],
            'enabled' => ['required', 'boolean'],
            'requests_per_minute' => ['required', 'integer', 'min:1', 'max:'.config('funcionario_consulta.max_requests_per_minute')],
        ]);
        $integration->update($data);
        return redirect()->route('admin.funcionarios-api.index')->with('success', 'Integración actualizada.')->withHeaders(self::HEADERS);
    }

    public function renew(Request $request, FuncionarioApiIntegration $integration)
    {
        $data = $request->validate(['token_days' => $this->daysRules()]);
        $token = DB::transaction(function () use ($integration, $data) {
            $locked = FuncionarioApiIntegration::lockForUpdate()->findOrFail($integration->id);
            $locked->tokens()->delete();
            return $locked->createToken('consulta', [FuncionarioApiIntegration::ABILITY], now()->addDays((int) $data['token_days']));
        });
        return $this->tokenResponse($integration, $token);
    }

    public function revoke(FuncionarioApiIntegration $integration)
    {
        DB::transaction(function () use ($integration) {
            FuncionarioApiIntegration::lockForUpdate()->findOrFail($integration->id)->tokens()->delete();
        });
        return redirect()->route('admin.funcionarios-api.index')->with('success', 'Token revocado. La integración necesita un nuevo token para consultar.')->withHeaders(self::HEADERS);
    }

    private function tokenResponse($integration, $token)
    {
        // Entrega única en la respuesta POST; nunca flash/sesión, URL, logs ni almacenamiento en claro.
        return response()->view('admin.funcionarios-api.token', [
            'integration' => $integration, 'plainToken' => $token->plainTextToken, 'expiresAt' => $token->accessToken->expires_at,
        ])->withHeaders(self::HEADERS);
    }

    private function daysRules(): array
    {
        return ['required', 'integer', 'min:1', 'max:'.config('funcionario_consulta.max_token_days')];
    }
}
