<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambia tu contraseña de SIDEANFECA</title>
</head>
<body style="margin:0; padding:0; background:#f3f3f3; font-family:Arial, Helvetica, sans-serif; color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f3f3; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#ffffff; border:1px solid #d9d9d9; border-radius:12px;">
                    <tr>
                        <td style="padding:20px 28px; border-bottom:3px solid #8b0000;">
                            <p style="margin:0; font-size:20px; font-weight:bold; color:#8b0000;">SIDEANFECA</p>
                            <p style="margin:2px 0 0; font-size:14px; color:#575757;">Sistema de Directorios de ANFECA</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 20px; font-size:16px; line-height:1.5;">
                                Recibimos una solicitud para cambiar la contraseña de tu cuenta.
                                Da clic en el botón para crear una contraseña nueva.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                <tr>
                                    <td style="background:#8b0000; border-radius:8px;">
                                        <a href="{{ $url }}" style="display:inline-block; padding:14px 26px; color:#ffffff; font-size:16px; font-weight:bold; text-decoration:none;">Crear contraseña nueva</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.5;">
                                El enlace vence en <strong>{{ $minutos }} minutos</strong> y solo funciona una vez.
                            </p>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.5; color:#575757;">
                                Si el botón no funciona, copia esta dirección y pégala en tu navegador:<br>
                                <a href="{{ $url }}" style="color:#8b0000; word-break:break-all;">{{ $url }}</a>
                            </p>
                            <p style="margin:0; font-size:14px; line-height:1.5; color:#575757;">
                                Si no pediste cambiar tu contraseña, ignora este correo: tu contraseña actual sigue funcionando.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
