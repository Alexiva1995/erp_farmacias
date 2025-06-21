<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LoginLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter; // <-- Importante
use Illuminate\Support\Str; // <-- Importante
use Illuminate\Validation\ValidationException; // <-- Importante
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Writer as BaconQrCodeWriter;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

class LoginController extends Controller
{
    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';
    /**
     * Create a new controller instance.
     */

    /**
     * Maneja la solicitud de login desde el frontend Vue.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $this->ensureIsNotRateLimited($request);

        $user = User::where('email', $request->login)
            // ->orWhere('username', $request->login)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($this->throttleKey($request));
            throw ValidationException::withMessages([
                'login' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        // --- INICIO DE CAMBIO IMPORTANTE ---
        $google2fa = new Google2FA();
        $needsQrSetup = empty($user->token_login);

        // Guardamos el ID del usuario en sesión para el siguiente paso (verificación 2FA)
        $request->session()->put('2fa_user_id', $user->id);

        $urlQR = null;
        if ($needsQrSetup) {
            // Generamos un secreto temporal solo para mostrar el QR
            $tempSecret = $google2fa->generateSecretKey();
            $request->session()->put('2fa_temp_secret', $tempSecret);
            $urlQR = $this->createUserUrlQR($user->email, $tempSecret);
        }

        // CAMBIO 1: Devolver datos JSON en lugar de HTML
        // Esto es lo que el componente de Vue espera recibir.
        return response()->json([
            'two_factor' => true,
            'needs_qr_setup' => $needsQrSetup,
            'qr_code_url' => $urlQR,
        ]);
        // --- FIN DE CAMBIO IMPORTANTE ---
    }

    public function verify2FA(Request $request)
    {
        // CAMBIO 2: Usar validación estándar de Laravel para errores
        $request->validate([
            // Laravel Fortify usa 'code' en lugar de 'code_verification'
            // Ajustamos a 'code' para coincidir con el componente Vue que te di.
            'code' => 'required|numeric',
        ]);

        $userId = $request->session()->get('2fa_user_id');
        if (!$userId || !$user = User::find($userId)) {
            // Este error es de sesión, no de validación. Un 401/403 es apropiado.
            return response()->json(['message' => 'Sesión inválida. Por favor, inicia sesión de nuevo.'], 401);
        }

        $google2fa = new Google2FA();
        // El secreto es el temporal (si es la primera vez) o el guardado en el usuario
        $secretKey = $request->session()->get('2fa_temp_secret', $user->token_login);

        if ($google2fa->verifyKey($secretKey, $request->code)) {
            // Si es la primera vez, guardamos el secreto en el usuario
            if ($request->session()->has('2fa_temp_secret')) {
                $user->token_login = $secretKey;
                $user->save();
                $request->session()->forget('2fa_temp_secret');
            }

            // Iniciamos sesión y regeneramos la sesión
            Auth::login($user, $request->filled('remember'));
            $request->session()->regenerate();
            $request->session()->forget('2fa_user_id');

            // CAMBIO 3: Devolver una respuesta de éxito simple.
            // Vue sabe qué hacer (redirigir). No es necesario que el backend le diga a dónde ir.
            // Un 204 "No Content" es perfecto para indicar "Todo salió bien, no hay nada más que decir".
            return response()->noContent();
        }

        // CAMBIO 4: Lanzar un error de validación estándar si el código es incorrecto.
        // Esto es más limpio y consistente para que Vue lo maneje.
        throw ValidationException::withMessages([
            'code' => ['El código de verificación proporcionado no es válido.'],
        ]);
    }

    /**
     * Asegura que el intento de login no esté limitado por tasa.
     * (Reemplazo de hasTooManyLoginAttempts y sendLockoutResponse)
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(Request $request)
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) { // 5 intentos
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Obtiene la clave para el limitador de tasa.
     * (Reemplazo de throttleKey)
     */
    public function throttleKey(Request $request): string
    {
        return Str::lower($request->input('login')) . '|' . $request->ip();
    }


    /**
     * Verifica el código 2FA enviado desde el modal.
     * (Este método no cambia)
     */

    /**
     * Desconecta al usuario y devuelve una respuesta JSON.
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * Genera la URL del QR code como una imagen en base64.
     * (Este método no cambia)
     */
    private function createUserUrlQR($email, $secretKey)
    {
        $bacon = new BaconQrCodeWriter(new ImageRenderer(
            new RendererStyle(200),
            new ImagickImageBackEnd()
        ));

        $qrCodeUrl = (new Google2FA())->getQRCodeUrl(
            config('app.name'),
            $email,
            $secretKey
        );

        $data = $bacon->writeString($qrCodeUrl, 'utf-8');

        return 'data:image/png;base64,' . base64_encode($data);
    }
}
