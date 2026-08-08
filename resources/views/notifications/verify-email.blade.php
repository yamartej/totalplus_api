<div class="container">
    <div style="max-width: 500px; margin: 0 auto; font-family: Arial, sans-serif; background: #f9f9f9; border-radius: 8px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="text-align: center; margin-bottom: 24px;">
            <img src="{{ asset('logo.png') }}" alt="Logo" style="max-width: 180px; margin-bottom: 16px;">
        </div>
        <h2 style="color: #333; text-align: center;">¡Bienvenido a TotalPlus!</h2>
        <p style="color: #555; font-size: 16px; text-align: center;">
            Gracias por registrarte, <strong>{{ $user->name ?? 'Usuario' }}</strong>.
        </p>
        <p style="color: #555; font-size: 16px; text-align: center;">
            Por favor, haz clic en el siguiente botón para verificar tu correo electrónico y activar tu cuenta:
        </p>
        <div style="text-align: center; margin: 32px 0;">
            <a href="{{ $url }}" style="background: #72cb10; color: #fff; padding: 12px 32px; border-radius: 5px; text-decoration: none; font-size: 16px;">
                Verificar correo electrónico
            </a>
        </div>
        <p style="color: #888; font-size: 13px; text-align: center;">
            Si no creaste una cuenta, puedes ignorar este mensaje.
        </p>
        <hr style="margin: 32px 0;">
        <p style="color: #aaa; font-size: 12px; text-align: center;">
            &copy; {{ date('Y') }} TotalPlus. Todos los derechos reservados.
        </p>
    </div>
</div>