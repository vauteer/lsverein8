---
paths:
  - 'app/Http/Controllers/Settings/**'
---

# Settings

## Profilbild-Upload: safe()->only(), nicht validated()
Der Upload folgt lswatter4. Drei Stellen, an denen es leicht schiefgeht:

`ProfileController::update()` füllt mit `$request->safe()->only(['name', 'email'])`, nicht mit `$request->validated()`. `profile_image` steht in `#[Fillable]` von User, und `validated()` enthält das hochgeladene `UploadedFile`-Objekt — ein `fill($request->validated())` würde also das Objekt in die Spalte schreiben statt des Dateinamens. Das Bild wird bewusst separat behandelt.

`remove_profile_image` gewinnt gegen eine gleichzeitig gesendete Datei (elseif, nicht zwei ifs). Dafür gibt es einen Test.

`User::removeOrphanProfileImages()` nach dem Speichern ist das Einzige, was die ersetzte oder entfernte Datei von der Platte räumt — nichts löscht sie sonst. Am 2026-08-25 gegengeprüft: nimmt man den Aufruf heraus, werden zwei Tests rot. Der Sweep geht über alle Dateien in `profile/` und löscht jede, auf die keine `users.profile_image`-Zeile mehr zeigt.

Der Rest lag schon im Model bereit (`profileDisk()`, `profileStoragePath()`, `profileURL()` mit Gravatar-Fallback, das `avatar`-Attribut). `public/storage` ist verlinkt, und `storage/app/public/.gitignore` hält die Uploads aus dem Repo.

Nicht getestet ist der Client-Pfad: Inertias `<Form>` serialisiert die Datei und spooft PATCH über POST. Die Feature-Tests posten direkt auf die Route, prüfen also nur die Serverseite. Club-Logos haben dieselbe Infrastruktur im Model (`Club::logoDisk()` usw.), aber noch kein Upload-Formular.

Zwei Fallen, die am 2026-08-25 zusammen zugeschlagen haben:

`removeOrphanProfileImages()` löscht jede Datei in `profile/`, auf die keine `users.profile_image`-Zeile zeigt — das schloss `.gitignore` ein, also genau die Datei, die das Verzeichnis im Repo hält. Beide Sweeps (auch `Club::removeOrphanLogos()`) überspringen jetzt Dotfiles. Ohne das löschte ein einziger Profil-Test das `.gitignore` von der echten Platte.

Möglich war das, weil `ProfileUpdateTest` kein `Storage::fake('public')` hatte. Jeder Test in dieser Datei braucht es, nicht nur die Upload-Tests: `ProfileController::update()` ruft den Sweep bei jeder Änderung auf, also greift auch eine reine Namensänderung auf `storage/app/public` durch. Das `beforeEach` steht deshalb ganz oben in der Datei.
