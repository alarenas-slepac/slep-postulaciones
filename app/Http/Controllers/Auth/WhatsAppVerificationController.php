<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\WhatsAppAccountVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WhatsAppVerificationController extends Controller
{
    public function notice(Request $request, WhatsAppAccountVerification $verification)
    {
        if ($request->user()->hasVerifiedEmail()) { return redirect()->route('dashboard'); }
        return response()->view('auth.verify-email', ['whatsapp' => $verification->state($request->user())])
            ->header('Cache-Control', 'no-store, private');
    }

    public function send(Request $request, WhatsAppAccountVerification $verification)
    {
        if ($request->user()->hasVerifiedAccount()) { return redirect()->route('dashboard'); }
        try {
            $data = Validator::make($request->only('whatsapp_phone'), ['whatsapp_phone' => ['required', 'string', 'max:64']],
                ['whatsapp_phone.required' => 'Ingresa tu número de celular.'])->validate();
            $verification->request($request->user(), $data['whatsapp_phone']);
        } catch (ValidationException $e) {
            return redirect()->route('verification.notice', ['channel' => 'whatsapp'])->withErrors($e->errors())
                ->withInput($request->only('whatsapp_phone'));
        }
        return redirect()->route('verification.notice', ['channel' => 'whatsapp'])->with('status', 'whatsapp-code-requested');
    }

    public function confirm(Request $request, WhatsAppAccountVerification $verification)
    {
        if ($request->user()->hasVerifiedAccount()) { return redirect()->route('dashboard'); }
        try {
            $data = Validator::make($request->only(['challenge_id', 'whatsapp_code']), [
                'challenge_id' => ['required', 'uuid'], 'whatsapp_code' => ['required', 'string', 'regex:/^[0-9]{6}$/D'],
            ], ['whatsapp_code.required' => 'Ingresa el código que recibiste por WhatsApp.',
                'whatsapp_code.regex' => 'El código debe contener exactamente 6 dígitos.',
                'challenge_id.required' => 'Solicita un código nuevo.', 'challenge_id.uuid' => 'Solicita un código nuevo.'])->validate();
            $verification->confirm($request->user(), $data['challenge_id'], $data['whatsapp_code']);
        } catch (ValidationException $e) {
            // El código nunca se copia a la sesión ni se vuelve a mostrar en el HTML.
            return redirect()->route('verification.notice', ['channel' => 'whatsapp'])->withErrors($e->errors());
        }
        return redirect()->route('dashboard')->with('status', 'Cuenta validada correctamente por WhatsApp.');
    }

    public function status(Request $request, WhatsAppAccountVerification $verification)
    {
        return response()->json($verification->state($request->user()))->header('Cache-Control', 'no-store, private');
    }
}
