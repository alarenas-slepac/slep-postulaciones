<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppDispatch;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppDispatcher;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WhatsAppController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer'];

    public function index()
    {
        return response()->view('admin.whatsapp.index', [
            'enabled' => (bool) config('whatsapp.enabled'),
            'ready' => Schema::hasTable('whatsapp_dispatches'),
            'requestId' => (string) Str::uuid(),
        ])->withHeaders(self::HEADERS);
    }

    public function status(WhatsAppClient $client): JsonResponse
    {
        $data = ['state' => 'disabled', 'qr' => null, 'qr_expires_at' => null];
        if (config('whatsapp.enabled')) {
            try { $data = $client->status(); }
            catch (WhatsAppException $exception) { $data['state'] = 'unavailable'; $data['error'] = $exception->errorCode; }
        }
        $data['recent'] = Schema::hasTable('whatsapp_dispatches')
            ? WhatsAppDispatch::latest()->limit(10)->get(['id', 'status', 'error_code', 'created_at', 'sent_at'])->toArray() : [];
        return response()->json($data)->withHeaders(self::HEADERS);
    }

    public function renewQr(WhatsAppClient $client): JsonResponse
    {
        return $this->action($client, 'session/qr');
    }

    public function unlink(WhatsAppClient $client): JsonResponse
    {
        return $this->action($client, 'session/unlink');
    }

    private function action(WhatsAppClient $client, string $path): JsonResponse
    {
        try {
            $client->request('POST', $path);
            return response()->json(['ok' => true])->withHeaders(self::HEADERS);
        } catch (WhatsAppException $exception) {
            return response()->json(['error' => $exception->errorCode], 409)->withHeaders(self::HEADERS);
        }
    }

    public function test(Request $request, WhatsAppDispatcher $dispatcher): JsonResponse
    {
        // No se guardan estos campos en old()/sesión ni se envían a logs de notificaciones por correo.
        $data = $request->validate([
            'to' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/D'],
            'text' => ['required', 'string', 'max:1000'],
            'request_id' => ['required', 'uuid'],
        ], ['to.regex' => 'Ingresa el número con + y código de país, sin espacios.']);
        try {
            $dispatch = $dispatcher->enqueue($data['to'], $data['text'], 'test:'.$request->user()->id.':'.$data['request_id'],
                'admin.test', $request->user()->id);
            if (!$dispatch) {
                return response()->json(['error' => config('whatsapp.enabled') ? 'queue_unavailable' : 'disabled'], 503)
                    ->withHeaders(self::HEADERS);
            }
            return response()->json(['id' => $dispatch->id, 'status' => $dispatch->status], 202)->withHeaders(self::HEADERS);
        } catch (WhatsAppException $exception) {
            return response()->json(['error' => $exception->errorCode], 409)->withHeaders(self::HEADERS);
        }
    }
}
