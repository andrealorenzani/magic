<?php
declare(strict_types=1);

namespace Arcana\Db;

/** Writes one audit record (request row + person rows). Never throws. */
final class AuditLog
{
    /** @param array<string,mixed> $record AuditRecord shape */
    public static function tryWrite(?string $configPath, array $record): bool
    {
        $pdo = null;
        try {
            $cfg = $configPath === null ? null : Config::load($configPath);
            if ($cfg === null) {
                return false; // unconfigured: normal on a dev machine, nothing logged
            }
            $pdo = Connection::open($cfg);
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO magic_audit (created_at, functionality, on_date, format_version, response_yaml) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?)')
                ->execute([$record['functionality'], $record['on_date'], $record['format_version'], $record['response_yaml']]);
            $id = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO magic_audit_person (audit_id, role, name, birth_date, birth_time, place_label, lat, lon, tz) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($record['persons'] as $p) {
                $stmt->execute([$id, $p['role'], $p['name'], $p['birth_date'], $p['birth_time'], $p['place_label'], $p['lat'], $p['lon'], $p['tz']]);
            }
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            try {
                if ($pdo !== null && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Throwable) {
            }
            // Class and code only: PDO messages can contain host and user names.
            error_log('audit: write failed ' . get_class($e) . ' ' . (string) $e->getCode());
            return false;
        }
    }
}
