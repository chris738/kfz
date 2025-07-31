# kfz
Eine Kostenübersicht für KFZ

## Docker Setup

Diese Webanwendung kann einfach mit Docker ausgeführt werden.

### Schnellstart mit Docker Compose

1. Repository klonen:
```bash
git clone https://github.com/chris738/kfz.git
cd kfz
```

2. Mit Docker Compose starten:
```bash
docker compose up -d
```

3. Die Anwendung ist dann unter http://localhost:8080 verfügbar

4. Standard-Benutzer einrichten:
```bash
./setup-default-user.sh
```

**Standard-Login:** admin / admin

### Manueller Docker Build

```bash
# Docker Image bauen
docker build -t kfz-webapp .

# Container starten
docker run -d -p 8080:80 -v kfz_data:/var/www/html/data --name kfz-webapp kfz-webapp

# Standard-Benutzer erstellen
docker exec kfz-webapp php -r "
require '/var/www/html/db.php';
\$hashedPassword = password_hash('admin', PASSWORD_DEFAULT);
\$db->prepare('INSERT INTO users (username, password) VALUES (?, ?)')
    ->execute(['admin', \$hashedPassword]);
echo 'Default user created: admin/admin\n';
"
```

### Automatisches Deployment

Für automatische Updates der Anwendung steht ein Deployment-Skript zur Verfügung:

```bash
# Normale Aktualisierung (behält Daten bei)
./deploy.sh

# Mit Datenbank-Reset (entfernt alle Daten)
./deploy.sh --reset-db

# Hilfe anzeigen
./deploy.sh --help
```

Das Skript führt automatisch folgende Schritte aus:
1. Aktueller Code wird aus dem git Repository geholt
2. Docker Container werden gestoppt und neu gestartet  
3. Datenbank wird neu eingerichtet (optional mit Reset)
4. Standard-Benutzer wird erstellt

### Persistente Daten

Die SQLite-Datenbank wird im Volume `kfz_data` gespeichert, so dass die Daten bei Container-Neustarts erhalten bleiben.

### Funktionen

- Benutzeranmeldung und -verwaltung
- Fahrzeug-CRUD-Operationen (Erstellen, Lesen, Aktualisieren, Löschen)
- Dashboard mit Fahrzeugstatistiken
- Bootstrap-basierte Benutzeroberfläche
