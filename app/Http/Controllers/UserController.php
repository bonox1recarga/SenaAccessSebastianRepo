<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Ingreso;
use App\Models\IngresoEquipo;
use App\Models\Novedad;
use App\Models\Notificacion;
use App\Models\TokenRecovery;
use App\Models\TwoFactorChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'ingreso_equipos'])->get();
        return response()->json($users); //CONSULTA DE TODOS LOS USUARIOS CON ROLES Y EQUIPOS
    }

    public function getIngresos(Request $request)
    {
        $query = Ingreso::with('user')->orderBy('ingreso_datetime', 'desc');
        // Filtros opcionales por fecha (el móvil los envía); sin ellos va todo.
        if ($request->filled('desde')) {
            $query->whereDate('ingreso_datetime', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('ingreso_datetime', '<=', $request->hasta);
        }
        $ingresos = $query->get();
        // El móvil siempre envía per_page y espera {data:[...]}; el web pide
        // sin parámetros y espera el array directo. Se sirven ambas formas.
        if ($request->has('per_page')) {
            return response()->json(['data' => $ingresos]);
        }
        return response()->json($ingresos); //CONSULTA DE TODOS LOS USUARIOS ORDENADO POR FECHA DESCENDENTE
    }

    public function createUser(Request $request)
    { //VALIDACIONES PARA EL REGISTRO DE USUARIOS
        $request->validate([
            'user_identification' => 'required|string|max:20|unique:usuarios', //VALIDA QUE EL USUARIO EXISTA
            'user_name' => 'required', //VALIDA QUE EL NOMBRE EXISTA
            'user_lastname' => 'required', //VALIDA QUE EL APELLIDO EXISTA
            'user_email' => 'required|email|unique:usuarios', //VALIDA QUE EL CORREO EXISTA
            'user_password' => 'required', //VALIDA QUE LA CONTRASEÑA EXISTA
            'user_coursenumber' => 'nullable|integer',
            'user_program' => 'nullable|string|max:100',
            'user_documento_tipo' => 'nullable|in:CC,CE,TI,PAS',
            'user_telefono' => 'nullable|string|max:20',
            'fk_id_rol' => 'required|exists:roles,id_rol',
            'image' => 'nullable|image|max:2048',
        ]);

        $profile_photo_path = null; //VALIDA QUE LA IMAGEN NO SE REPITAN
        if ($request->hasFile('image')) { //VALIDA QUE LA IMAGEN EXISTA
            $profile_photo_path = $request->file('image')->storeOnCloudinary('avatars')->getSecurePath(); //VALIDA QUE LA IMAGEN NO SE REPITAN
        }

        $user = User::create([ //CREA EL USUARIO
            'user_identification' => $request->user_identification,
            'user_name' => $request->user_name,
            'user_lastname' => $request->user_lastname,
            'user_email' => $request->user_email,
            'user_password' => Hash::make($request->user_password),
            'user_coursenumber' => $request->user_coursenumber,
            'user_program' => $request->user_program,
            'user_documento_tipo' => $request->user_documento_tipo ?? 'CC',
            'user_telefono' => $request->user_telefono,
            'fk_id_rol' => $request->fk_id_rol,
            'profile_photo_path' => $profile_photo_path,
        ]);

        return response()->json($user->load(['role', 'ingreso_equipos']), 201);
    }

    public function deleteUser(Request $request, $id) //FUNCION PARA ELIMINAR USUARIO POR ID (borrado total: primero su rastro, luego el usuario)
    {
        if ((int) $request->user()->id_usuario === (int) $id) { //NADIE PUEDE BORRARSE A SI MISMO
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 403);
        }
        $user = User::findOrFail($id);
        DB::transaction(function () use ($user) { //TODO JUNTO O NADA: si algo falla no queda medio borrado
            $uid = $user->id_usuario;
            $user->tokens()->delete(); //SESIONES SANCTUM
            TwoFactorChallenge::where('fk_id_usuario', $uid)->delete(); //RETOS 2FA
            TokenRecovery::where('fk_id_usuario', $uid)->delete(); //TOKENS DE RECUPERACION
            Ingreso::where('fk_id_user', $uid)->delete(); //HISTORIAL DE ACCESOS
            IngresoEquipo::where('fk_id_usuario', $uid)->delete(); //EQUIPOS
            Novedad::where('fk_id_usuario', $uid)->delete(); //NOVEDADES
            Notificacion::where('fk_id_usuario', $uid)->delete(); //NOTIFICACIONES
            $user->delete(); //ELIMINA EL USUARIO
        });
        return response()->json(['message' => 'Usuario eliminado']); //RETORNA MENSAJE DE ELIMINACION
    }

    public function updateUser(Request $request, $id) //FUNCION PARA ACTUALIZAR USUARIO
    {
        $user = User::findOrFail($id); //BUSCA EL USUARIO POR ID
        
        $request->validate([
            'user_identification' => 'required|string|max:20|unique:usuarios,user_identification,' . $id . ',id_usuario',
            'user_name' => 'required',
            'user_lastname' => 'required',
            'user_email' => 'required|email|unique:usuarios,user_email,' . $id . ',id_usuario',
            'user_password' => 'nullable|min:6',
            'user_coursenumber' => 'nullable|integer',
            'user_program' => 'nullable|string|max:100',
            'user_documento_tipo' => 'nullable|in:CC,CE,TI,PAS',
            'user_telefono' => 'nullable|string|max:20',
            'fk_id_rol' => 'required|exists:roles,id_rol',
            'image' => 'nullable|image|max:2048',
        ]);
        //ACTUALIZA LA IMAGEN DEL USUARIO
        if ($request->hasFile('image')) { //VALIDA QUE LA IMAGEN EXISTA
            $profile_photo_path = $request->file('image')->storeOnCloudinary('avatars')->getSecurePath();
            $user->profile_photo_path = $profile_photo_path; //ACTUALIZA LA IMAGEN DEL USUARIO
        }

        $user->user_identification = $request->user_identification; //ACTUALIZA LA IDENTIFICACION DEL USUARIO
        $user->user_name = $request->user_name; //ACTUALIZA EL NOMBRE DEL USUARIO
        $user->user_lastname = $request->user_lastname; //ACTUALIZA EL APELLIDO DEL USUARIO
        $user->user_email = $request->user_email; //ACTUALIZA EL CORREO DEL USUARIO
        $user->user_coursenumber = $request->user_coursenumber; //ACTUALIZA EL NUMERO DE CURSO DEL USUARIO
        $user->user_program = $request->user_program; //ACTUALIZA EL PROGRAMA DEL USUARIO
        $user->user_documento_tipo = $request->user_documento_tipo ?? $user->user_documento_tipo ?? 'CC';
        $user->user_telefono = $request->user_telefono;
        $user->fk_id_rol = $request->fk_id_rol; //ACTUALIZA EL ROL DEL USUARIO

        if ($request->filled('user_password')) { //VALIDA QUE LA CONTRASEÑA EXISTA
            $user->user_password = Hash::make($request->user_password); //ACTUALIZA LA CONTRASEÑA DEL USUARIO
        }

        $user->save(); //GUARDA LOS CAMBIOS EN LA BASE DE DATOS

        return response()->json($user->load(['role', 'ingreso_equipos'])); //RETORNA EL USUARIO ACTUALIZADO CON SUS EQUIPOS
    }

    public function getRoles() //FUNCION PARA OBTENER TODOS LOS ROLES
    {
        return response()->json(Role::all()); //RETORNA TODOS LOS ROLES
    }

    public function getMyIngresos(Request $request) //FUNCION PARA OBTENER MIS INGRESOS
    {
        $ingresos = Ingreso::where('fk_id_user', $request->user()->id_usuario) //BUSCA LOS INGRESOS DEL USUARIO
            ->with('user') //RELACIONA CON EL USUARIO
            ->orderBy('ingreso_datetime', 'desc') //ORDENA POR FECHA DESCENDENTE
            ->get(); //OBTIENE TODOS LOS INGRESOS
        return response()->json($ingresos); //RETORNA LOS INGRESOS
    }

    public function verificarPassword(Request $request) //VERIFICA LA CONTRASEÑA ACTUAL CON LA SESION (para registrar la huella sin disparar 2FA ni enviar correos)
    {
        $request->validate(['user_password' => 'required|string']); //SOLO PIDE LA CONTRASEÑA ACTUAL
        $user = $request->user(); //USUARIO AUTENTICADO POR TOKEN
        if (!Hash::check($request->input('user_password'), $user->user_password)) { //COMPARA SIN CREAR RETOS NI TOKENS
            return response()->json(['message' => 'Contraseña incorrecta.'], 422);
        }
        return response()->json(['message' => 'Contraseña verificada.']); //LISTO PARA ABRIR EL SENSOR DE HUELLA
    }

    public function updateMyProfile(Request $request) //FUNCION PARA ACTUALIZAR MI PERFIL
    {
        $user = $request->user(); //OBTIENE EL USUARIO ACTUAL
        $user->loadMissing('role'); //ASEGURA LA RELACION CON EL ROL
        // Solo el Aprendiz tiene ficha y programa; el resto los deja en null.
        $esAprendiz = strcasecmp($user->role->rol_name ?? '', 'Aprendiz') === 0; //VERIFICA SI ES APRENDIZ

        $request->validate([ //VALIDA QUE LA INFORMACION SEA CORRECTA
            'user_identification' => 'required|string|max:20|unique:usuarios,user_identification,' . $user->id_usuario . ',id_usuario', //VALIDA QUE LA IDENTIFICACION NO SE REPITA
            'user_name' => 'required', //VALIDA QUE EL NOMBRE EXISTA
            'user_lastname' => 'required', //VALIDA QUE EL APELLIDO EXISTA
            'user_email' => [
                'required', 'email', 'unique:usuarios,user_email,' . $user->id_usuario . ',id_usuario',
                // Mismo whitelist de dominios que el registro.
                function ($attribute, $value, $fail) {
                    $dominios = ['@gmail.com', '@hotmail.com', '@outlook.com', '@soy.sena.edu.co'];
                    foreach ($dominios as $d) {
                        if (str_ends_with(strtolower($value), $d)) return;
                    }
                    $fail('El correo debe ser @gmail.com, @hotmail.com, @outlook.com o @soy.sena.edu.co.');
                },
            ], //VALIDA QUE EL CORREO EXISTA
            'user_password' => 'nullable|min:6', //VALIDA QUE LA CONTRASEÑA EXISTA
            'two_factor_code' => 'nullable|string|size:6', //CODIGO 2FA PARA CAMBIO DE CLAVE
            'user_coursenumber' => $esAprendiz ? 'required' : 'nullable', //FICHA SOLO OBLIGATORIA PARA APRENDIZ
            'user_program' => $esAprendiz ? 'required' : 'nullable', //PROGRAMA SOLO OBLIGATORIO PARA APRENDIZ
            'image' => 'nullable|image|max:5120', //VALIDA QUE LA IMAGEN EXISTA
        ]);

        // Puerta 2FA al cambiar la clave desde el perfil: con verificación activa
        // se exige el código de 6 dígitos enviado al correo.
        if ($request->filled('user_password') && $user->two_factor_enabled) {
            $claveCache = '2fa_clave_' . $user->id_usuario;
            if (!$request->filled('two_factor_code')) {
                $codigo = (string) random_int(100000, 999999);
                Cache::put($claveCache, Hash::make($codigo), now()->addMinutes(10));
                try {
                    Mail::to($user->user_email)->send(new \App\Mail\TwoFactorCodeMail($codigo, '#', '#'));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Error enviando codigo cambio clave a {$user->user_email}: " . $e->getMessage());
                }
                return response()->json(['message' => 'Te enviamos un código de 6 dígitos a tu correo. Escríbelo para autorizar la nueva contraseña.', 'errors' => ['two_factor_code' => ['código enviado']]], 422);
            }
            $hash = Cache::get($claveCache);
            if (!$hash || !Hash::check($request->input('two_factor_code'), $hash)) {
                return response()->json(['message' => 'El código es incorrecto o venció. Revisa tu correo o guarda otra vez para recibir uno nuevo.', 'errors' => ['two_factor_code' => ['código inválido']]], 422);
            }
            Cache::forget($claveCache);
        }

        if ($request->hasFile('image')) { //VALIDA QUE LA IMAGEN EXISTA
            $profile_photo_path = $request->file('image')->storeOnCloudinary('avatars')->getSecurePath(); //VALIDA QUE LA IMAGEN NO SE REPITAN
            $user->profile_photo_path = $profile_photo_path; //ACTUALIZA LA IMAGEN DEL USUARIO
        }

        $user->user_identification = $request->user_identification; //ACTUALIZA LA IDENTIFICACION DEL USUARIO
        $user->user_name = $request->user_name; //ACTUALIZA EL NOMBRE DEL USUARIO
        $user->user_lastname = $request->user_lastname; //ACTUALIZA EL APELLIDO DEL USUARIO
        // Si cambia el correo, se invalida su verificación hasta que lo confirme de nuevo.
        if ($user->user_email !== $request->user_email) {
            $user->email_verified_at = null;
        }
        $user->user_email = $request->user_email; //ACTUALIZA EL CORREO DEL USUARIO
        $user->user_coursenumber = $request->filled('user_coursenumber') ? $request->user_coursenumber : null; //FICHA (NULL PARA NO-APRENDIZ)
        $user->user_program = $request->filled('user_program') ? $request->user_program : null; //PROGRAMA (NULL PARA NO-APRENDIZ)

        if ($request->filled('user_password')) { //VALIDA QUE LA CONTRASEÑA EXISTA
            $user->user_password = Hash::make($request->user_password); //ACTUALIZA LA CONTRASEÑA DEL USUARIO
        }

        $user->save(); //GUARDA LOS CAMBIOS EN LA BASE DE DATOS

        return response()->json($user->load('role')); //RETORNA EL USUARIO ACTUALIZADO
    }
}
