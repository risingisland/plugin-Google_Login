<?php

$i18n = [

# Basics
	'lang_Menu_Title'			=>	'Inicio de sesión de Google',
	'lang_Page_Title'			=>	'Inicio de sesión de Google',
	'lang_Description'			=>	'Agrega la opción "Iniciar sesión con Google" a la página de acceso del administrador, vinculada a cuentas de usuario existentes específicas.',

# Tabs
	'lang_Settings_Tab'		=>	'Configuración de OAuth',
	'lang_Users_Tab'			=>	'Asignación de usuarios',

# Settings view
	'lang_Client_ID'			=>	'ID de cliente de Google',
	'lang_Client_Secret'		=>	'Secreto de cliente de Google',
	'lang_Enabled'				=>	'Habilitar "Iniciar sesión con Google" en la página de acceso',
	'lang_Redirect_URI_Note'	=>	'Agrega este URI de redirección exacto en tu cliente OAuth de Google Cloud Console:',
	'lang_Click_To_Select'		=>	'Haz clic para seleccionar',
	'lang_Copy'					=>	'Copiar',
	'lang_Copied'				=>	'¡Copiado!',
	'lang_Select_Fallback'		=>	'Seleccionado - presiona Ctrl/Cmd+C para copiar',
	'lang_Save'					=>	'Guardar',
	'lang_Saved'				=>	'Guardado.',

# Help tab
	'lang_Help_Tab'				=>	'Ayuda',
	'lang_Help_Intro'			=>	'Sigue estos pasos en Google Cloud Console para obtener un ID de cliente y un secreto de cliente para la pestaña de Configuración.',
	'lang_Help_Console_Link'	=>	'Abrir credenciales de Google Cloud Console',
	'lang_Help_Step1'			=>	'Crea o selecciona un proyecto y luego ve a <strong>APIs y servicios &rarr; Pantalla de consentimiento de OAuth</strong> para configurar el nombre de la app, el correo de soporte y el dominio autorizado (puede aparecer como <strong>Google Auth Platform &rarr; Marca</strong>). El modo "Prueba" está bien aquí, ya que solo las cuentas que vincules abajo podrán iniciar sesión de todos modos.',
	'lang_Help_Step2'			=>	'Ve a <strong>APIs y servicios &rarr; Credenciales &rarr; Crear credenciales &rarr; ID de cliente de OAuth</strong> y elige <strong>Aplicación web</strong> como tipo.',
	'lang_Help_Step3'			=>	'En <strong>URIs de redireccionamiento autorizados</strong>, agrega este URI exacto y luego haz clic en Crear:',
	'lang_Help_Step4'			=>	'Copia el ID de cliente y el secreto de cliente que Google te muestra en la pestaña de Configuración.',
	'lang_Help_Https_Note'		=>	'Google requiere HTTPS para este URI de redirección en cualquier dominio real. Un entorno local Laragon/HTTP necesitará su propio cliente OAuth separado usando <code>http://localhost</code> para pruebas.',

# Users view
	'lang_Users_Info'			=>	'Vincula cada cuenta de usuario de GetSimple con la cuenta de Google con la que se le permitirá iniciar sesión. Deja en blanco para deshabilitar el inicio de sesión de Google para ese usuario.',
	'lang_Username'				=>	'Usuario de GetSimple',
	'lang_Google_Email'			=>	'Correo de Google vinculado',
	'lang_No_account_linked'	=>	'Sin vincular',

# Login page UI
	'lang_Sign_in_button'		=>	'Iniciar sesión con Google',
	'lang_Or_divider'			=>	'o inicia sesión con tu contraseña',

# Errors shown on the login page
	'lang_Err_not_configured'	=>	'El inicio de sesión con Google aún no está configurado.',
	'lang_Err_denied'			=>	'Se canceló el inicio de sesión con Google.',
	'lang_Err_unverified'		=>	'Tu correo de Google no está verificado.',
	'lang_Err_no_match'			=>	'Ninguna cuenta de GetSimple está vinculada a esa cuenta de Google.',
	'lang_Err_generic'			=>	'El inicio de sesión con Google falló. Inténtalo de nuevo o usa tu contraseña.',

];
