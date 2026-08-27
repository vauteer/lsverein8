---
paths:
  - config/log-viewer.php
  - config/telescope.php
---

# Config

## Log Viewer: leere Dateiliste kommt meist aus dem localStorage-Filter, nicht vom Server
Symptom: /log-viewer lädt, „Log files on Local" ist aber leer, obwohl `storage/logs/laravel.log` existiert.

Ursache am 2026-08-26: `localStorage.selectedFileTypes` stand auf `["php_fpm"]`. Der Dateityp-Filter der Oberfläche liegt im Browser, nicht auf dem Server, und `laravel.log` hat den Typ `laravel` — also filtert die Oberfläche alles weg. Der Wert stammte aus der Zeit, als `include_files` noch die System-Logs auflistete (der Cache trug noch einen `php-fpm.log`-Eintrag). Das Einschränken auf `laravel*.log` hat php-fpm.log entfernt, der Filter im Browser zeigte aber weiter genau darauf.

Behebung: im Zahnrad-Menü die Dateitypen zurücksetzen, oder `localStorage.removeItem('selectedFileTypes')`. Nichts am Code.

Deshalb zuerst den Browser prüfen, bevor irgendetwas serverseitig gesucht wird. Was hier alles gesund war und Zeit gekostet hat: Datei vorhanden, `LogViewer::getFiles()` findet sie, `GET /log-viewer/api/folders` liefert 200 mit gültigem JSON, Gate und Stateful-Session greifen. Merkmal dieser Fehlerklasse: **die API antwortet 200 mit Daten, die Liste bleibt trotzdem leer** — dann liegt es im Browserzustand (`defaults.use_local_storage` ist true, die Oberfläche merkt sich Filter, Sortierung, Theme).

Zwei Nebenbefunde, beide unkritisch:
- `public/vendor/log-viewer` ist nicht veröffentlicht. Das Paket bettet seine Assets dann inline ein (`LogViewer::css()`/`js()`), die Seite funktioniert also. `php artisan vendor:publish --tag=log-viewer-assets` wäre optional; lswatter4 läuft ebenfalls ohne.
- `tests/Feature/LogViewerTest.php` trifft nur die HTML-Seite und die Facade, **nicht** `GET /log-viewer/api/folders` — also nicht den Endpunkt, der die Dateiliste füllt. Tests dafür waren bei dieser Suche kurz da und wurden bewusst wieder verworfen: sie hätten diesen Fehler nicht gefunden (er lag im Browser), und der Endpunkt gehört dem Paket, nicht uns. Wer ihn doch absichern will, braucht `actingAs` plus einen `Referer`-Header, sonst greift `EnsureFrontendRequestsAreStateful` nicht.

## Telescope: root-only, kein local-Schlupfloch, und die drei Tabellen sind vom Backup ausgenommen
Telescope v5 (2026-08-27 installiert, produktive Abhängigkeit, nicht require-dev). Fünf Abweichungen vom Standard-Setup, alle absichtlich:

**`TELESCOPE_RECORD_EVERYTHING` als Notausgang, aus lscraft5 übernommen.** Der publizierte Filter behält außerhalb von `local` nur Einträge, die auf ein Problem zeigen (Exception, 5xx, fehlgeschlagener Job, geplanter Task, überwachtes Tag) — mit `APP_ENV=production` zeichnet Telescope auf einer gesunden Seite also praktisch nichts auf, was regelmäßig für „Telescope ist kaputt" gehalten wird. `config('telescope.record_everything')` (Default false) schaltet die volle Aufzeichnung dazu. Zwei Details, die nicht wegoptimiert werden dürfen: der Filter liegt in `TelescopeServiceProvider::shouldRecord()` und liest die Config **pro Eintrag**, nicht einmal beim Booten — die Variable wirkt damit ohne Redeploy; und in `.env.example` steht sie auskommentiert (`# TELESCOPE_RECORD_EVERYTHING=true`), sie ist zum Anschalten gedacht, wenn man einen Fehler in Produktion jagt, und danach wieder zum Ausschalten. `telescope:prune` hält die Tabelle solange im Zaum.

**Kein local-Bypass.** `TelescopeApplicationServiceProvider::authorization()` setzt `Telescope::auth(fn () => app()->environment('local') || Gate::check('viewTelescope', ...))`. Auf der Entwicklermaschine (APP_ENV=local) wäre /telescope damit für **jeden** offen — auch für einen Gast, denn die Paket-Routen tragen keine eigene auth-Middleware. `App\Providers\TelescopeServiceProvider::authorization()` überschreibt das komplett: nur noch `$request->user()?->can('viewTelescope')`. Ein Eintrag trägt Request-Payload, Query-Bindings und Model-Attribute aller Vereine — gleiche Begründung wie beim Log-Viewer.

**Das Gate liegt in AppServiceProvider::configureGates()**, neben `viewLogViewer` und `manageBackups`, root-only über `users.admin` mit `(bool)`-Cast (siehe models.md: das Attribut ist auf einer frisch erzeugten Instanz null). Die `gate()`-Methode aus dem publizierten Stub (E-Mail-Whitelist) wurde entfernt, damit es nur eine Definition gibt.

**`auth` steht in `config('telescope.middleware')`** vor `Authorize::class`, sonst bekommt ein Gast ein nacktes 403 statt des Logins. Beim erneuten Publishen von config/telescope.php geht das verloren — `tests/Feature/TelescopeTest.php` pinnt es.

**`telescope_entries`, `telescope_entries_tags`, `telescope_monitoring` stehen in `config('backup.exclude_tables')`.** Debug-Telemetrie, keine Vereinsdaten: eine Zeile pro Request, `telescope:prune --hours=48` wirft sie nachts um 23:00 sowieso weg (vor `app:backup` um 23:15), und die `content`-Spalte enthält Payloads und Bindings im Klartext, also Namen, E-Mails, IBANs. Die Struktur wandert weiterhin über den `--no-data`-Durchlauf in den Dump, wie bei cache/sessions/jobs.

Testfalle: `TELESCOPE_ENABLED` in phpunit.xml kommt als **leerer String** an, nicht als `false` — falsy, also registriert das Paket seine Routen in der Suite gar nicht und `GET /telescope` ist dort ein 404. TelescopeTest kann deshalb nicht wie LogViewerTest über HTTP prüfen und ruft stattdessen `Telescope::check()` mit einem Request samt `setUserResolver()` direkt auf. Nicht versuchen, das über HTTP grün zu bekommen, ohne Telescope in Tests einzuschalten — dann schreibt jeder Test eine Zeile pro Query.

`storage.database.connection` steht auf `env('DB_CONNECTION', 'mariadb')` (Paket-Default war 'mysql', das es hier nicht gibt). `ignore_paths` hat zusätzlich `log-viewer*`, dessen UI im geöffneten Zustand die eigene API pollt.
