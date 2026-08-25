<?php

/**
 * The login dialog's server-side messages. Without this file Laravel falls
 * back to its built-in English lines, so a failed login answered a German UI
 * with "These credentials do not match our records."
 */
return [
    'failed' => 'Diese Zugangsdaten passen zu keinem Konto.',
    'password' => 'Das eingegebene Passwort ist falsch.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in :seconds Sekunden erneut.',
];
