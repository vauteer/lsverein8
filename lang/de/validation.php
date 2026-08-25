<?php

/**
 * Only the rules the translated screens can actually surface. Laravel resolves
 * a missing key against the fallback locale, so every other rule keeps its
 * built-in English line until a screen needs it.
 *
 * Form requests that declare their own messages() — the user and section CRUD —
 * take precedence over this file and stay keyed by their English source string
 * in lang/de.json.
 */
return [
    'confirmed' => ':attribute stimmt nicht mit der Bestätigung überein.',
    'current_password' => 'Das Passwort ist falsch.',
    'email' => ':attribute ist ungültig.',
    'max' => [
        'string' => ':attribute darf nicht länger als :max Zeichen sein.',
    ],
    'min' => [
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Ziffer enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':attribute ist in einem Datenleck aufgetaucht. Bitte wählen Sie ein anderes.',
    ],
    'required' => ':attribute ist erforderlich.',
    'string' => ':attribute muss ein Text sein.',
    'unique' => ':attribute wird bereits verwendet.',

    'attributes' => [
        'current_password' => 'Das aktuelle Passwort',
        'email' => 'Die E-Mail-Adresse',
        'name' => 'Der Name',
        'password' => 'Das Passwort',
        'password_confirmation' => 'Die Passwortbestätigung',
    ],
];
